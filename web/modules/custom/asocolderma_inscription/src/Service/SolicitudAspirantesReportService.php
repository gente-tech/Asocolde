<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Obtiene datos del reporte institucional desde solicitudes e historial.
 * No considera un adjunto cargado como un adjunto verificado.
 */
final class SolicitudAspirantesReportService {

  /** Campos realmente obligatorios en el wizard actual (noviembre 2026). */
  private const REQUIRED_ATTACHMENTS = [
    'adj_carta_1' => 'Carta 1',
    'adj_carta_2' => 'Carta 2',
    'adj_rut' => 'RUT',
    'adj_id' => 'Documento de identidad',
    'adj_carta_ingreso' => 'Carta de ingreso',
    'adj_hv' => 'Hoja de vida',
    'adj_diploma_medico' => 'Diploma médico',
    'adj_diploma_dermatologo' => 'Diploma dermatólogo',
    'adj_rethus' => 'RETHUS',
    'adj_aut_verificacion' => 'Autorización verificación',
    'adj_acta_grado_medico' => 'Acta grado médico',
    'adj_acta_grado_dermatologo' => 'Acta grado dermatólogo',
    'adj_pensum_academico' => 'Pénsum académico',
    'adj_notas_dermatologia' => 'Notas dermatología',
  ];

  private const OPTIONAL_ATTACHMENTS = [
    'adj_cert_publicacion',
    'adj_convalidacion',
  ];

  /** Posición de los siete hitos en el reporte, en orden. */
  private const STAGES = [
    'sg_aprobado',
    'junta_aprobado',
    'asamblea_aprobado',
    'coord_documentos_enviados',
    'coord_pago_ingreso',
    // No existe estado independiente "pago confirmado" en el flujo actual.
    '',
    'coord_miembro_activo',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly SolicitudZohoVariableManager $variableManager,
  ) {}

  /** Filtros controlados y validados; no se aceptan identificadores arbitrarios. */
  public function normalizeFilters(array $input): array {
    $filters = [
      'desde' => '',
      'hasta' => '',
      'estado' => 0,
      'tipo' => 0,
    ];
    foreach (['desde', 'hasta'] as $key) {
      $value = is_scalar($input[$key] ?? NULL) ? trim((string) $input[$key]) : '';
      if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($dt && $dt->format('Y-m-d') === $value) {
          $filters[$key] = $value;
        }
      }
    }
    foreach (['estado', 'tipo'] as $key) {
      $value = is_scalar($input[$key] ?? NULL) ? (string) $input[$key] : '';
      $filters[$key] = ctype_digit($value) ? (int) $value : 0;
    }
    return $filters;
  }

  /** Devuelve el universo filtrado de solicitudes en orden descendente. */
  public function getIds(array $filters): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'solicitud_ingreso')
      ->sort('created', 'DESC');

    if ($filters['desde'] !== '') {
      $query->condition('created', $this->dateBoundary($filters['desde'], '00:00:00'), '>=');
    }
    if ($filters['hasta'] !== '') {
      $query->condition('created', $this->dateBoundary($filters['hasta'], '23:59:59'), '<=');
    }