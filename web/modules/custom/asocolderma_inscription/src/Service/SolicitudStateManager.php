<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Drupal\asocolderma_inscription\Service\SolicitudNotificationManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SolicitudStateManager
{

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
    private readonly SolicitudHistorialLogger $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ZohoSignService $zohoSignService,
    private readonly SolicitudNotificationManager $notificationManager,
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
      $this->handlePostTransitionNotifications($node, $from_name, $to_name, $origin, $comment, $metadata);
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

    try {
      $this->zohoSignService->createSignatureRequest([
        'solicitud_nid' => (int) $node->id(),
        'recipient_name' => $recipient_name,
        'recipient_email' => $recipient_email,
        'field_text_data' => $this->buildFieldTextData($node),
        'notes' => 'Solicitud de ingreso Asocolderma #' . $this->getSolicitudCode($node),
      ]);
    } catch (\Throwable $e) {
      \Drupal::logger('asocolderma_inscription')->error(
        'Error creando request de firma para solicitud @nid: @message',
        [
          '@nid' => $node->id(),
          '@message' => $e->getMessage(),
        ]
      );
    }
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
        ? ($node->get('field_ciudad_ejercicio')->entity?->label() ?? '')
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

  /**
   * Sends configured notifications after a state transition.
   */
  private function handlePostTransitionNotifications(
    NodeInterface $node,
    ?string $from_name,
    ?string $to_name,
    string $origin,
    string $comment,
    array $metadata,
  ): void {
    $phase_key = $this->resolveNotificationPhaseKey($from_name, $to_name, $origin);

    if ($phase_key === NULL) {
      return;
    }

    try {
      $changed_timestamp = \Drupal::time()->getRequestTime();
      $changed_by = $this->currentUser->getDisplayName();

      $this->notificationManager->sendForPhase($node, $phase_key, [
        'from_state' => $from_name,
        'to_state' => $to_name,
        'origin' => $origin,
        'comment' => $comment,
        'metadata' => $metadata,

        // Variables institucionales usadas por Mandrill y Twilio.
        'request_previous_status' => $from_name ?? '',
        'request_new_status' => $to_name ?? '',
        'request_status_changed_timestamp' => $changed_timestamp,
        'request_status_changed_date' => \Drupal::service('date.formatter')->format($changed_timestamp, 'custom', 'd/m/Y H:i'),
        'request_status_changed_by' => $changed_by,
        'request_status_change_comment' => $comment,
      ]);
    } catch (\Throwable $e) {
      \Drupal::logger('asocolderma_inscription')->error(
        'Error ejecutando notificaciones para solicitud @nid en fase @phase: @message',
        [
          '@nid' => $node->id(),
          '@phase' => $phase_key,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Resolves the notification phase key from the target state label.
   */
  private function resolveNotificationPhaseKey(?string $from_name, ?string $to_name, string $origin = ''): ?string
  {
    $normalized_to = $this->normalizeStateName($to_name);
    $normalized_origin = $this->normalizeStateName($origin);

    if ($normalized_to === 'en tramite' && $normalized_origin === 'aspirante_ajustes_realizados') {
      return 'ajustes_realizados';
    }

    return match ($normalized_to) {
      'en tramite' => 'solicitud_creada',
      'pendiente aclaracion' => 'pendiente_aclaracion',

      'aprobado',
      'aprobada',
      'aprobado secretaria',
      'aprobada secretaria',
      'aprobado secretaria general',
      'aprobada secretaria general' => 'aprobada_secretaria',

      'rechazado',
      'rechazada' => 'rechazada_secretaria',

      'aprobado junta d',
      'aprobada junta d',
      'aprobado junta directiva',
      'aprobada junta directiva' => 'aprobada_junta_directiva',

      'rechazado junta d',
      'rechazada junta d',
      'rechazado junta directiva',
      'rechazada junta directiva' => 'rechazada_junta_directiva',

      'aprobado asamblea g',
      'aprobada asamblea g',
      'aprobado asamblea general',
      'aprobada asamblea general' => 'aprobada_asamblea_general',

      'rechazado asamblea g',
      'rechazada asamblea g',
      'rechazado asamblea general',
      'rechazada asamblea general' => 'rechazada_asamblea_general',

      'pendiente pago de ingreso',
      'pago de ingreso pendiente' => 'pendiente_pago_ingreso',

      'pendiente firma de documentos' => 'pendiente_firma_documentos',

      'documentos firmados' => 'documentos_firmados',

      'miembro activo',
      'documentos firmados miembro activo' => 'miembro_activo',

      default => NULL,
    };
  }

  /**
   * Normalizes state labels for stable comparison.
   */
  private function normalizeStateName(?string $value): string
  {
    $value = trim((string) $value);

    if ($value === '') {
      return '';
    }

    $value = mb_strtolower($value, 'UTF-8');

    $search = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
    $replace = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];
    $value = str_replace($search, $replace, $value);

    $value = str_replace(['.', ',', ';', ':'], '', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim((string) $value);
  }
}
