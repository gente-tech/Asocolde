<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Gestiona las transiciones de estado de las solicitudes de ingreso.
 */
final class SolicitudStateManager
{

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
    private readonly SolicitudHistorialLogger $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ZohoSignService $zohoSignService,
    private readonly SolicitudNotificationPhaseCatalog $notificationPhaseCatalog,
    private readonly SolicitudNotificationManager $notificationManager,
    private readonly SolicitudMemberActivator $memberActivator,
  ) {}

  /**
   * Indica si la firma de la solicitud fue completada en Zoho Sign.
   */
  public function isSignatureCompleted(
    NodeInterface $node,
    bool $refresh = TRUE,
  ): bool {
    if ($node->bundle() !== 'solicitud_ingreso') {
      return FALSE;
    }

    return $this->zohoSignService
      ->isSignatureCompletedForSolicitud(
        (int) $node->id(),
        $refresh,
      );
  }

  /**
   * Ejecuta una transición de estado utilizando el TID destino.
   */
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

    if ($from_tid !== NULL && $from_tid === $to_tid) {
      return;
    }

    $from_name = $from_tid
      ? $this->resolveTermNameByTid($from_tid)
      : NULL;

    $to_name = $this->resolveTermNameByTid($to_tid);

    $from_functional_key = $from_tid
      ? \asocolderma_inscription_get_state_functional_key_by_tid($from_tid)
      : '';

    $to_functional_key =
      \asocolderma_inscription_get_state_functional_key_by_tid($to_tid);

    $is_payment_transition =
      $from_functional_key === 'coord_documentos_enviados'
      && $to_functional_key === 'coord_pago_ingreso';

    if (
      $is_payment_transition
      && !$this->isSignatureCompleted($node, TRUE)
    ) {
      throw new \DomainException(
        'El aspirante aún no ha firmado los documentos.'
      );
    }

    $tx = $this->db->startTransaction();

    try {
      $node->set('field_state', ['target_id' => $to_tid]);
      $node->save();

      $this->logger->logTransition(
        (int) $node->id(),
        $from_tid,
        $to_tid,
        (int) $this->currentUser->id(),
        $origin,
        $comment,
        $metadata,
      );

      $this->handlePostTransitionActions(
        $node,
        $to_functional_key,
      );

      $this->handlePostTransitionNotifications(
        $node,
        $from_name,
        $to_name,
        $to_functional_key,
        $origin,
        $comment,
        $metadata,
      );
    } catch (\Throwable $e) {
      $tx->rollBack();
      throw $e;
    }
  }

  /**
   * Ejecuta acciones internas posteriores a una transición.
   */
  private function handlePostTransitionActions(
    NodeInterface $node,
    string $to_functional_key,
  ): void {
    if ($to_functional_key !== 'coord_miembro_activo') {
      return;
    }

    $this->memberActivator->activateFromSolicitud($node);
  }

  /**
   * Resuelve el nombre visible de un estado por TID.
   */
  private function resolveTermNameByTid(int $tid): ?string
  {
    if ($tid <= 0) {
      return NULL;
    }

    $term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($tid);

    return $term
      ? (string) $term->getName()
      : NULL;
  }

  /**
   * Ejecuta las notificaciones configuradas después de una transición.
   */
  private function handlePostTransitionNotifications(
    NodeInterface $node,
    ?string $from_name,
    ?string $to_name,
    string $to_functional_key,
    string $origin,
    string $comment,
    array $metadata,
  ): void {
    $phase_key = $this->notificationPhaseCatalog->resolveForTransition(
      $to_functional_key,
      $origin,
    );

    if ($phase_key === NULL) {
      return;
    }

    try {
      $changed_timestamp = \Drupal::time()->getRequestTime();
      $changed_by = $this->currentUser->getDisplayName();

      $this->notificationManager->sendForPhase(
        $node,
        $phase_key,
        [
          'from_state' => $from_name,
          'to_state' => $to_name,
          'origin' => $origin,
          'comment' => $comment,
          'metadata' => $metadata,

          'request_previous_status' => $from_name ?? '',
          'request_new_status' => $to_name ?? '',
          'request_status_changed_timestamp' => $changed_timestamp,
          'request_status_changed_date' => \Drupal::service('date.formatter')
            ->format(
              $changed_timestamp,
              'custom',
              'd/m/Y H:i',
            ),
          'request_status_changed_by' => $changed_by,
          'request_status_change_comment' => $comment,
        ],
      );
    } catch (\Throwable $e) {
      \Drupal::logger('asocolderma_inscription')->error(
        'Error ejecutando notificaciones para solicitud @nid en fase @phase: @message',
        [
          '@nid' => $node->id(),
          '@phase' => $phase_key,
          '@message' => $e->getMessage(),
        ],
      );
    }
  }
}
