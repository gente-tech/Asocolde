<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SolicitudStateManager
{

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
    private readonly SolicitudHistorialLogger $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ZohoSignService $zohoSignService,
  ) {}

  public function transitionByTid(
    NodeInterface $node,
    int $to_tid,
    string $origin,
    string $comment = '',
    array $metadata = [],
  ): void {
    if ($node->bundle() !== 'solicitud_ingreso') {
      throw new AccessDeniedHttpException();
    }

    $from_tid = $node->get('field_state')->target_id;
    $from_tid = $from_tid !== NULL ? (int) $from_tid : NULL;

    if ($from_tid !== NULL && $from_tid === (int) $to_tid) {
      return;
    }

    $from_name = $from_tid ? $this->resolveTermNameByTid($from_tid) : NULL;
    $to_name = $this->resolveTermNameByTid((int) $to_tid);

    $tx = $this->db->startTransaction();

    try {
      $node->set('field_state', ['target_id' => $to_tid]);
      $node->save();

      $this->logger->logTransition(
        (int) $node->id(),
        $from_tid,
        (int) $to_tid,
        (int) $this->currentUser->id(),
        $origin,
        $comment,
        $metadata,
      );

      $this->handlePostTransitionActions($node, $from_name, $to_name);
    } catch (\Throwable $e) {
      $tx->rollBack();
      throw $e;
    }
  }

  private function handlePostTransitionActions(
    NodeInterface $node,
    ?string $from_name,
    ?string $to_name,
  ): void {
    if ($to_name !== 'Pendiente firma de documentos') {
      return;
    }

    $existing = $this->zohoSignService->getLatestRequestMappingBySolicitud((int) $node->id());

    if (!empty($existing['zoho_request_id'])) {
      return;
    }

    $recipient_name = $this->resolveRecipientName($node);
    $recipient_email = $this->resolveRecipientEmail($node);

    if ($recipient_name === '' || $recipient_email === '') {
      throw new \RuntimeException('No fue posible preparar la firma porque faltan datos del firmante.');
    }

    $this->zohoSignService->createSignatureRequest([
      'solicitud_nid' => (int) $node->id(),
      'recipient_name' => $recipient_name,
      'recipient_email' => $recipient_email,
      'field_text_data' => $this->buildFieldTextData($node),
      'notes' => 'Solicitud de ingreso Asocolderma #' . $this->getSolicitudCode($node),
    ]);
  }

  private function resolveRecipientName(NodeInterface $node): string
  {
    $parts = [];

    foreach (['field_nombre1', 'field_nombre2', 'field_apellido1', 'field_apellido2'] as $field_name) {
      if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
        $parts[] = trim((string) $node->get($field_name)->value);
      }
    }

    $full_name = trim(implode(' ', array_filter($parts)));

    if ($full_name !== '') {
      return $full_name;
    }

    $owner = $node->getOwner();
    if ($owner) {
      return trim((string) $owner->getDisplayName());
    }

    return '';
  }

  private function resolveRecipientEmail(NodeInterface $node): string
  {
    if ($node->hasField('field_email') && !$node->get('field_email')->isEmpty()) {
      return trim((string) $node->get('field_email')->value);
    }

    $owner = $node->getOwner();
    if ($owner && $owner->getEmail()) {
      return trim((string) $owner->getEmail());
    }

    return '';
  }

  private function buildFieldTextData(NodeInterface $node): array
  {
    return [
      'solicitud_id' => $this->getSolicitudCode($node),
      'nombre_completo' => $this->resolveRecipientName($node),
      'correo' => $this->resolveRecipientEmail($node),
      'documento' => $node->hasField('field_numero_documento') && !$node->get('field_numero_documento')->isEmpty()
        ? (string) $node->get('field_numero_documento')->value
        : '',
      'registro_medico' => $node->hasField('field_registro_medico') && !$node->get('field_registro_medico')->isEmpty()
        ? (string) $node->get('field_registro_medico')->value
        : '',
      'ciudad' => $node->hasField('field_ciudad_ejercicio') && !$node->get('field_ciudad_ejercicio')->isEmpty()
        ? (string) $node->get('field_ciudad_ejercicio')->value
        : '',
    ];
  }

  private function getSolicitudCode(NodeInterface $node): string
  {
    if ($node->hasField('field_solicitud_id') && !$node->get('field_solicitud_id')->isEmpty()) {
      return (string) $node->get('field_solicitud_id')->value;
    }

    return 'NID-' . $node->id();
  }

  private function resolveTermNameByTid(int $tid): ?string
  {
    if ($tid <= 0) {
      return NULL;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    return $term ? (string) $term->getName() : NULL;
  }
}
