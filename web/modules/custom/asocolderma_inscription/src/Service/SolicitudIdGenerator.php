<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;

final class SolicitudIdGenerator {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Genera ID: DDMMAAAA-XXX-NNN
   * - XXX: últimos 3 dígitos del documento
   * - NNN: consecutivo del día (count + 1) protegido por lock
   */
  public function generate(string $document_number): string {
    $date_ddmmyyyy = date('dmY', $this->time->getCurrentTime());

    $digits = preg_replace('/\D+/', '', $document_number) ?: '';
    $last3 = substr($digits, -3);
    $last3 = str_pad($last3, 3, '0', STR_PAD_LEFT);

    // Lock por día para evitar colisiones por concurrencia.
    $lock_name = "asocolderma_solicitud_id_$date_ddmmyyyy";
    if (!$this->lock->acquire($lock_name, 10.0)) {
      throw new \RuntimeException('No se pudo adquirir lock para generar ID de solicitud.');
    }

    try {
      $start = strtotime(date('Y-m-d 00:00:00', $this->time->getCurrentTime()));
      $end = strtotime(date('Y-m-d 23:59:59', $this->time->getCurrentTime()));

      // Conteo de solicitudes creadas hoy.
      $count_today = (int) $this->etm->getStorage('solicitud_ingreso')
        ->getQuery()
        ->condition('created', $start, '>=')
        ->condition('created', $end, '<=')
        ->accessCheck(FALSE)
        ->count()
        ->execute();

      // Consecutivo del día.
      $nnn = str_pad((string) ($count_today + 1), 3, '0', STR_PAD_LEFT);

      return "{$date_ddmmyyyy}-{$last3}-{$nnn}";
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

}