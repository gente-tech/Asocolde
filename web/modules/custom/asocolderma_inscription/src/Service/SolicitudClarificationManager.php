<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;

/**
 * Gestiona solicitudes de aclaración asociadas a solicitudes de ingreso.
 */
final class SolicitudClarificationManager
{

	public const STATUS_OPEN = 'open';
	public const STATUS_ANSWERED = 'answered';
	public const STATUS_SUPERSEDED = 'superseded';

	public function __construct(
		private readonly Connection $database,
		private readonly TimeInterface $time,
	) {}

	/**
	 * Crea una nueva aclaración activa para una solicitud.
	 *
	 * Si ya existe una aclaración abierta para la misma solicitud, la marca como
	 * superada para evitar ambigüedad operativa.
	 */
	public function createClarification(
		NodeInterface $solicitud,
		array $requested_fields,
		string $message,
		int $requested_by_uid,
	): int {
		if ($solicitud->bundle() !== 'solicitud_ingreso') {
			throw new \InvalidArgumentException('La entidad no corresponde al bundle solicitud_ingreso.');
		}

		$nid = (int) $solicitud->id();
		$requested_fields = $this->normalizeRequestedFields($requested_fields);
		$message = trim($message);

		if ($nid <= 0) {
			throw new \InvalidArgumentException('La solicitud no tiene un NID válido.');
		}

		if (empty($requested_fields)) {
			throw new \InvalidArgumentException('Debe seleccionar al menos un campo para aclaración.');
		}

		if ($message === '') {
			throw new \InvalidArgumentException('Debe registrar un mensaje de aclaración.');
		}

		if ($requested_by_uid <= 0) {
			throw new \InvalidArgumentException('El usuario solicitante no es válido.');
		}

		$transaction = $this->database->startTransaction();

		try {
			$now = $this->time->getRequestTime();

			$this->database->update('asocolderma_solicitud_aclaracion')
				->fields([
					'status' => self::STATUS_SUPERSEDED,
					'resolution_metadata' => Json::encode([
						'reason' => 'Nueva solicitud de aclaración creada para la misma solicitud.',
						'superseded_at' => $now,
						'superseded_by_uid' => $requested_by_uid,
					]),
				])
				->condition('solicitud_nid', $nid)
				->condition('status', self::STATUS_OPEN)
				->execute();

			return (int) $this->database->insert('asocolderma_solicitud_aclaracion')
				->fields([
					'solicitud_nid' => $nid,
					'requested_fields' => Json::encode($requested_fields),
					'message' => $message,
					'requested_by_uid' => $requested_by_uid,
					'created' => $now,
					'resolved_by_uid' => NULL,
					'resolved' => NULL,
					'resolution_metadata' => NULL,
					'status' => self::STATUS_OPEN,
				])
				->execute();
		} catch (\Throwable $throwable) {
			$transaction->rollBack();
			throw $throwable;
		}
	}

	/**
	 * Retorna la aclaración abierta más reciente de una solicitud.
	 */
	public function getActiveClarification(int $solicitud_nid): ?array
	{
		if ($solicitud_nid <= 0) {
			return NULL;
		}

		$row = $this->database->select('asocolderma_solicitud_aclaracion', 'a')
			->fields('a')
			->condition('a.solicitud_nid', $solicitud_nid)
			->condition('a.status', self::STATUS_OPEN)
			->orderBy('a.created', 'DESC')
			->orderBy('a.id', 'DESC')
			->range(0, 1)
			->execute()
			->fetchAssoc();

		return $row ? $this->normalizeRow($row) : NULL;
	}

	/**
	 * Retorna los campos solicitados en la aclaración abierta.
	 */
	public function getActiveRequestedFields(int $solicitud_nid): array
	{
		$clarification = $this->getActiveClarification($solicitud_nid);

		if (!$clarification) {
			return [];
		}

		return $clarification['requested_fields'] ?? [];
	}

	/**
	 * Indica si una solicitud tiene una aclaración abierta.
	 */
	public function hasActiveClarification(int $solicitud_nid): bool
	{
		return $this->getActiveClarification($solicitud_nid) !== NULL;
	}

	/**
	 * Marca una aclaración como respondida por el aspirante.
	 */
	public function markAnswered(
		int $clarification_id,
		int $resolved_by_uid,
		array $resolution_metadata = [],
	): void {
		if ($clarification_id <= 0) {
			throw new \InvalidArgumentException('El ID de aclaración no es válido.');
		}

		if ($resolved_by_uid <= 0) {
			throw new \InvalidArgumentException('El usuario que responde no es válido.');
		}

		$this->database->update('asocolderma_solicitud_aclaracion')
			->fields([
				'status' => self::STATUS_ANSWERED,
				'resolved_by_uid' => $resolved_by_uid,
				'resolved' => $this->time->getRequestTime(),
				'resolution_metadata' => !empty($resolution_metadata) ? Json::encode($resolution_metadata) : NULL,
			])
			->condition('id', $clarification_id)
			->condition('status', self::STATUS_OPEN)
			->execute();
	}

	/**
	 * Normaliza los campos solicitados.
	 */
	private function normalizeRequestedFields(array $requested_fields): array
	{
		$normalized = [];

		foreach ($requested_fields as $field_name) {
			$field_name = trim((string) $field_name);

			if ($field_name === '') {
				continue;
			}

			if (!preg_match('/^[a-zA-Z0-9_]+$/', $field_name)) {
				continue;
			}

			$normalized[] = $field_name;
		}

		$normalized = array_values(array_unique($normalized));
		sort($normalized);

		return $normalized;
	}

	/**
	 * Normaliza una fila de base de datos.
	 */
	private function normalizeRow(array $row): array
	{
		$requested_fields = [];

		if (!empty($row['requested_fields'])) {
			$decoded = Json::decode((string) $row['requested_fields']);

			if (is_array($decoded)) {
				$requested_fields = $decoded;
			}
		}

		$resolution_metadata = [];

		if (!empty($row['resolution_metadata'])) {
			$decoded = Json::decode((string) $row['resolution_metadata']);

			if (is_array($decoded)) {
				$resolution_metadata = $decoded;
			}
		}

		$row['id'] = (int) $row['id'];
		$row['solicitud_nid'] = (int) $row['solicitud_nid'];
		$row['requested_by_uid'] = (int) $row['requested_by_uid'];
		$row['created'] = (int) $row['created'];
		$row['resolved_by_uid'] = isset($row['resolved_by_uid']) ? (int) $row['resolved_by_uid'] : NULL;
		$row['resolved'] = isset($row['resolved']) ? (int) $row['resolved'] : NULL;
		$row['requested_fields'] = $requested_fields;
		$row['resolution_metadata'] = $resolution_metadata;

		return $row;
	}
}
