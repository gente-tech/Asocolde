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
final class SolicitudAspirantesReportService
{

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
	public function normalizeFilters(array $input): array
	{
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
	public function getIds(array $filters): array
	{
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
		if ($filters['estado'] > 0) {
			$query->condition('field_state.target_id', $filters['estado']);
		}
		if ($filters['tipo'] > 0) {
			$query->condition('field_tipo_asociado.target_id', $filters['tipo']);
		}
		return array_map('intval', array_values($query->execute()));
	}

	/**
	 * Construye filas A:Z a partir de información verificada en Drupal.
	 * La fecha de pago validado queda vacía si no está registrada formalmente.
	 */
	public function buildRows(array $ids): array
	{
		$results = [];
		$totals = ['activos' => 0, 'rechazadas' => 0, 'incompletos' => 0, 'total' => 0];
		$nodeStorage = $this->entityTypeManager->getStorage('node');

		foreach (array_chunk($ids, 200) as $chunk) {
			$nodes = $nodeStorage->loadMultiple($chunk);
			$histories = $this->loadHistory($chunk);
			foreach ($chunk as $nid) {
				$node = $nodes[$nid] ?? NULL;
				if (!$node instanceof NodeInterface) {
					continue;
				}
				$values = $this->variableManager->resolveAll($node);
				$history = $histories[$nid] ?? [];
				$stateTid = (int) ($node->get('field_state')->target_id ?? 0);
				$stateKey = $stateTid > 0
					? asocolderma_inscription_get_state_functional_key_by_tid($stateTid)
					: '';
				$statusLabel = $stateTid > 0
					? asocolderma_inscription_get_state_visual_label_by_tid($stateTid)
					: '';
				$stages = [];
				foreach (self::STAGES as $key) {
					$stages[] = $key !== '' && isset($history[$key])
						? $this->formatDateTime($history[$key]['created'])
						: '';
				}
				// Mostrar la última transición si el histórico fue generado por una
				// versión previa y el estado actual sí coincide con un hito conocido.
				$docCount = 0;
				$missing = [];
				foreach (self::REQUIRED_ATTACHMENTS as $suffix => $label) {
					if ($node->hasField('field_' . $suffix) && !$node->get('field_' . $suffix)->isEmpty()) {
						++$docCount;
					} else {
						$missing[] = $label;
					}
				}
				$optionalCount = 0;
				foreach (self::OPTIONAL_ATTACHMENTS as $suffix) {
					if ($node->hasField('field_' . $suffix) && !$node->get('field_' . $suffix)->isEmpty()) {
						++$optionalCount;
					}
				}
				$isComplete = count($missing) === 0;
				$isActive = $stateKey === 'coord_miembro_activo';
				$isRejected = in_array($stateKey, [
					'sg_rechazado',
					'junta_rechazado',
					'asamblea_rechazado',
				], TRUE);

				$totals['total']++;
				$totals['activos'] += (int) $isActive;
				$totals['rechazadas'] += (int) $isRejected;
				$totals['incompletos'] += (int) !$isComplete;

				$docType = $this->value($values, 'tipo_documento');
				$docNumber = $this->value($values, 'numero_documento');
				$fullDocument = trim($docType . ' ' . $docNumber);
				$subspecialty = $this->value($values, 'subespecialidad_cual');
				$hasSubspecialty = $this->value($values, 'tiene_subespecialidad');
				if ($subspecialty === '' && $hasSubspecialty !== '') {
					$subspecialty = $hasSubspecialty === 'Sí' ? 'Sí, no especificada' : 'No';
				}
				$comment = $node->hasField('field_sg_reason')
					? trim((string) ($node->get('field_sg_reason')->value ?? ''))
					: '';
				// "Pago validado" y fecha de confirmación se dejan vacíos, porque
				// las transiciones actuales no representan ambos datos por separado.
				$payment = $isActive ? 'Miembro activo (pago implícito)' : ($stateKey === 'coord_pago_ingreso' ? '⚠ Pendiente' : ($isRejected ? '✗ No aplica' : 'Sin confirmación registrada'));

				$results[] = [
					$this->value($values, 'solicitud_id') ?: 'NID ' . $nid,
					$this->formatDate((int) $node->getCreatedTime()),
					$this->value($values, 'tipo_asociado'),
					$this->dateFormatter->format((int) $node->getCreatedTime(), 'custom', 'Y'),
					$this->value($values, 'aspirante_nombre_completo'),
					$fullDocument,
					$this->value($values, 'email_principal') ?: $this->value($values, 'aspirante_correo_cuenta'),
					$this->value($values, 'celular'),
					$this->value($values, 'ciudad_ejercicio'),
					$this->value($values, 'universidad_residencia'),
					$this->value($values, 'pais_residencia'),
					$subspecialty,
					$statusLabel ?: $this->resolveCurrentName($stateTid),
					...$stages,
					$isComplete ? '✓ Cargados' : '⚠ Incompletos',
					$isComplete ? '—' : implode(', ', $missing),
					$docCount . ' de ' . count(self::REQUIRED_ATTACHMENTS) . ' obligatorios'
						. ($optionalCount ? ' + ' . $optionalCount . ' opcionales' : ''),
					$comment,
					$payment,
					'',
				];
			}
		}
		return ['rows' => $results, 'totals' => $totals];
	}

	/** @return array<int, array<string, array{created: int}>> */
	private function loadHistory(array $ids): array
	{
		if (!$ids || !$this->database->schema()->tableExists('asocolderma_solicitud_historial')) {
			return [];
		}
		$query = $this->database->select('asocolderma_solicitud_historial', 'h')
			->fields('h', ['solicitud_nid', 'to_tid', 'created', 'id'])
			->condition('solicitud_nid', $ids, 'IN')
			->orderBy('created', 'ASC')
			->orderBy('id', 'ASC');
		$out = [];
		foreach ($query->execute() as $entry) {
			$key = asocolderma_inscription_get_state_functional_key_by_tid((int) $entry->to_tid);
			if ($key !== '' && !isset($out[(int) $entry->solicitud_nid][$key])) {
				$out[(int) $entry->solicitud_nid][$key] = ['created' => (int) $entry->created];
			}
		}
		return $out;
	}

	private function dateBoundary(string $date, string $time): int
	{
		$siteTimezone = (string) (\Drupal::config('system.date')->get('timezone.default') ?: 'America/Bogota');
		$datetime = new \DateTimeImmutable($date . ' ' . $time, new \DateTimeZone($siteTimezone));
		return $datetime->getTimestamp();
	}

	private function value(array $values, string $name): string
	{
		return trim((string) ($values[$name] ?? ''));
	}

	private function formatDate(int $timestamp): string
	{
		return $this->dateFormatter->format($timestamp, 'custom', 'd/m/Y');
	}

	private function formatDateTime(int $timestamp): string
	{
		return $this->dateFormatter->format($timestamp, 'custom', 'd/m/Y h:i A');
	}

	private function resolveCurrentName(int $tid): string
	{
		if (!$tid) {
			return '';
		}
		$term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
		return $term ? $term->label() : '';
	}
}
