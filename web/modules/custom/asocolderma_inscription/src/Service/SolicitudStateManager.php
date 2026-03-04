<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SolicitudStateManager {

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
    private readonly SolicitudHistorialLogger $logger,
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

    // Idempotencia: si ya está en el mismo estado, no hacemos nada.
    if ($from_tid !== NULL && $from_tid === (int) $to_tid) {
      return;
    }

    $tx = $this->db->startTransaction();

    try {
      // 1) Cambiar estado en el nodo.
      $node->set('field_state', ['target_id' => $to_tid]);
      $node->save();

      // 2) Insertar historial (append-only).
      $this->logger->logTransition(
        (int) $node->id(),
        $from_tid,
        (int) $to_tid,
        (int) $this->currentUser->id(),
        $origin,
        $comment,
        $metadata,
      );
    }
    catch (\Throwable $e) {
      $tx->rollBack();
      throw $e;
    }
  }

}