<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Genera identificadores únicos de solicitudes de ingreso.
 *
 * Formato: DDMMAAAA-XXX-NNN.
 */
final class SolicitudIdGenerator
{

  public function __construct(
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Reserva un consecutivo diario persistente.
   */
  public function generate(string $document_number): string
  {
    $digits = preg_replace('/\D+/', '', $document_number) ?? '';

    if ($digits === '') {
      throw new \InvalidArgumentException(
        'El número de documento es obligatorio.'
      );
    }

    $last3 = str_pad(
      substr($digits, -3),
      3,
      '0',
      STR_PAD_LEFT
    );

    $date = (new \DateTimeImmutable(
      '@' . $this->time->getCurrentTime()
    ))->setTimezone(
      new \DateTimeZone('America/Bogota')
    );

    $day = $date->format('Ymd');
    $date_ddmmyyyy = $date->format('dmY');

    $table = 'asocolderma_solicitud_id_sequence';
    $lock_name = 'asocolderma_solicitud_id_' . $day;

    if (!$this->lock->acquire($lock_name, 30.0)) {
      throw new \RuntimeException(
        'No se pudo adquirir el bloqueo para generar el código de solicitud.'
      );
    }

    try {
      $current = $this->database->select($table, 's')
        ->fields('s', ['last_value'])
        ->condition('day', $day)
        ->execute()
        ->fetchField();

      $next = $current === FALSE
        ? 1
        : (int) $current + 1;

      if ($next > 999) {
        throw new \RuntimeException(
          'Se agotó el consecutivo diario de tres dígitos.'
        );
      }

      if ($current === FALSE) {
        $this->database->insert($table)
          ->fields([
            'day' => $day,
            'last_value' => $next,
          ])
          ->execute();
      } else {
        $updated = $this->database->update($table)
          ->fields([
            'last_value' => $next,
          ])
          ->condition('day', $day)
          ->condition('last_value', (int) $current)
          ->execute();

        if ($updated !== 1) {
          throw new \RuntimeException(
            'El consecutivo diario cambió durante la asignación.'
          );
        }
      }

      return sprintf(
        '%s-%s-%03d',
        $date_ddmmyyyy,
        $last3,
        $next
      );
    } finally {
      $this->lock->release($lock_name);
    }
  }
}
