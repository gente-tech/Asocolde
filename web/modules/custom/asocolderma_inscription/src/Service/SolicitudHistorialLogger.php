<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Datetime\TimeInterface;

final class SolicitudHistorialLogger {

  public function __construct(
    private readonly Connection $db,
    private readonly TimeInterface $time,
  ) {}

  public function logTransition(
    int $nid,
    ?int $from_tid,
    int $to_tid,
    int $actor_uid,
    string $origin,
    string $comment = '',
    array $metadata = [],
  ): void {
    $this->db->insert('asocolderma_solicitud_historial')
      ->fields([
        'solicitud_nid' => $nid,
        'from_tid' => $from_tid,
        'to_tid' => $to_tid,
        'actor_uid' => $actor_uid,
        'created' => $this->time->getRequestTime(),
        'origin' => $origin,
        'comment' => $comment ?: NULL,
        'metadata' => !empty($metadata) ? Json::encode($metadata) : NULL,
      ])
      ->execute();
  }

}