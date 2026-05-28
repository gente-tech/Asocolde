<?php

namespace Drupal\asocolderma_data_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logs manual create and update operations for Data Core records.
 */
class RecordCrudLogger
{

	/**
	 * Database connection.
	 */
	protected Connection $database;

	/**
	 * Current user.
	 */
	protected AccountProxyInterface $currentUser;

	/**
	 * Request stack.
	 */
	protected RequestStack $requestStack;

	/**
	 * System/internal fields that should not be logged as business changes.
	 */
	protected const IGNORED_FIELDS = [
		'id',
		'batch_id',
		'row_number',
		'raw_payload',
		'validation_status',
		'validation_errors',
		'created',
		'changed',
		'is_active',
		'status_changed',
		'status_changed_by',
		'status_change_reason',
	];

	/**
	 * Constructor.
	 */
	public function __construct(
		Connection $database,
		AccountProxyInterface $current_user,
		RequestStack $request_stack,
	) {
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->requestStack = $request_stack;
	}

	/**
	 * Logs a manual record creation.
	 */
	public function logCreate(string $table_name, int $record_id, array $new_record, ?string $record_label = NULL): int
	{
		$record_label = $record_label ?: $this->buildRecordLabel($table_name, $new_record);

		$audit_event_id = $this->createAuditEvent(
			'record_create',
			$table_name,
			$record_id,
			$record_label,
			[
				'operation' => 'manual_create',
				'message' => 'Registro creado manualmente desde formulario.',
			],
		);

		$this->createRecordLink($audit_event_id, $table_name, $record_id, 'created');

		foreach ($new_record as $field_name => $new_value) {
			if ($this->shouldIgnoreField($field_name)) {
				continue;
			}

			if ($this->normalizeForCompare($new_value) === '') {
				continue;
			}

			$this->createAuditChange(
				$audit_event_id,
				$field_name,
				'create',
				NULL,
				$new_value,
			);
		}

		return $audit_event_id;
	}

	/**
	 * Logs a manual record update.
	 */
	public function logUpdate(string $table_name, int $record_id, array $old_record, array $new_record, ?string $record_label = NULL): ?int
	{
		$changes = [];

		foreach ($new_record as $field_name => $new_value) {
			if ($this->shouldIgnoreField($field_name)) {
				continue;
			}

			$old_value = $old_record[$field_name] ?? NULL;

			if ($this->normalizeForCompare($old_value) === $this->normalizeForCompare($new_value)) {
				continue;
			}

			$changes[] = [
				'field_name' => $field_name,
				'old_value' => $old_value,
				'new_value' => $new_value,
			];
		}

		if (empty($changes)) {
			return NULL;
		}

		$merged_record = array_merge($old_record, $new_record);
		$record_label = $record_label ?: $this->buildRecordLabel($table_name, $merged_record);

		$audit_event_id = $this->createAuditEvent(
			'record_update',
			$table_name,
			$record_id,
			$record_label,
			[
				'operation' => 'manual_update',
				'message' => 'Registro actualizado manualmente desde formulario.',
				'changed_fields_count' => count($changes),
			],
		);

		$this->createRecordLink($audit_event_id, $table_name, $record_id, 'updated');

		foreach ($changes as $change) {
			$this->createAuditChange(
				$audit_event_id,
				$change['field_name'],
				'update',
				$change['old_value'],
				$change['new_value'],
			);
		}

		return $audit_event_id;
	}

	/**
	 * Creates the audit event master record.
	 */
	protected function createAuditEvent(string $event_type, string $table_name, int $record_id, string $record_label, array $context = []): int
	{
		$request = $this->requestStack->getCurrentRequest();

		$user_email = NULL;
		if (method_exists($this->currentUser, 'getEmail')) {
			$user_email = $this->currentUser->getEmail();
		}

		return (int) $this->database
			->insert('asocolderma_audit_event')
			->fields([
				'event_type' => $event_type,
				'source' => 'manual_form',
				'target_table' => $table_name,
				'target_record_id' => $record_id,
				'target_record_label' => $this->truncate($record_label, 255),
				'user_id' => (int) $this->currentUser->id(),
				'user_name' => $this->truncate((string) $this->currentUser->getAccountName(), 150),
				'user_email' => $this->truncate((string) $user_email, 150),
				'user_roles' => json_encode(array_values($this->currentUser->getRoles()), JSON_UNESCAPED_UNICODE),
				'user_permissions_snapshot' => NULL,
				'ip_address' => $request ? $this->truncate((string) $request->getClientIp(), 45) : NULL,
				'user_agent' => $request ? $request->headers->get('User-Agent') : NULL,
				'request_uri' => $request ? $this->truncate((string) $request->getRequestUri(), 500) : NULL,
				'created' => time(),
				'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
			])
			->execute();
	}

	/**
	 * Creates the relation between audit event and affected record.
	 */
	protected function createRecordLink(int $audit_event_id, string $table_name, int $record_id, string $relation_type): void
	{
		$this->database
			->insert('asocolderma_audit_record_link')
			->fields([
				'audit_event_id' => $audit_event_id,
				'target_table' => $table_name,
				'target_record_id' => $record_id,
				'relation_type' => $relation_type,
				'created' => time(),
			])
			->execute();
	}

	/**
	 * Creates a field-level audit change.
	 */
	protected function createAuditChange(int $audit_event_id, string $field_name, string $change_type, $old_value, $new_value): void
	{
		$this->database
			->insert('asocolderma_audit_change')
			->fields([
				'audit_event_id' => $audit_event_id,
				'field_name' => $this->truncate($field_name, 150),
				'field_label' => $this->buildFieldLabel($field_name),
				'change_type' => $change_type,
				'old_value' => $this->normalizeForStorage($old_value),
				'new_value' => $this->normalizeForStorage($new_value),
				'created' => time(),
			])
			->execute();
	}

	/**
	 * Builds a readable record label depending on the table.
	 */
	protected function buildRecordLabel(string $table_name, array $record): string
	{
		switch ($table_name) {
			case 'asocolderma_import_patrocinadores':
			case 'asocolderma_import_proveedores':
				$identifier = $this->firstNonEmpty($record, ['nit', 'id_asocolderma']);
				$label = $this->firstNonEmpty($record, ['razon_social', 'nombre_comercial']);
				return trim(($identifier ? 'NIT ' . $identifier . ' - ' : '') . $label);

			case 'asocolderma_import_asociados':
			case 'asocolderma_import_residentes':
			case 'asocolderma_import_empleados':
				$identifier = $this->firstNonEmpty($record, ['numero_documento', 'id_asocolderma']);
				$label = trim(implode(' ', array_filter([
					$record['primer_nombre'] ?? '',
					$record['segundo_nombre'] ?? '',
					$record['primer_apellido'] ?? '',
					$record['segundo_apellido'] ?? '',
				])));
				return trim(($identifier ? 'Documento ' . $identifier . ' - ' : '') . $label);

			default:
				return $this->firstNonEmpty($record, ['id_asocolderma', 'razon_social', 'nombre_comercial', 'primer_nombre']) ?: 'Registro ID';
		}
	}

	/**
	 * Builds a readable field label from machine name.
	 */
	protected function buildFieldLabel(string $field_name): string
	{
		return ucfirst(str_replace('_', ' ', $field_name));
	}

	/**
	 * Returns TRUE when field should not be stored in audit changes.
	 */
	protected function shouldIgnoreField(string $field_name): bool
	{
		return in_array($field_name, self::IGNORED_FIELDS, TRUE);
	}

	/**
	 * Normalizes value for comparison.
	 */
	protected function normalizeForCompare($value): string
	{
		if ($value === NULL) {
			return '';
		}

		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		if (is_array($value) || is_object($value)) {
			return json_encode($value, JSON_UNESCAPED_UNICODE);
		}

		return trim((string) $value);
	}

	/**
	 * Normalizes value for DB storage.
	 */
	protected function normalizeForStorage($value): ?string
	{
		if ($value === NULL) {
			return NULL;
		}

		if (is_array($value) || is_object($value)) {
			$value = json_encode($value, JSON_UNESCAPED_UNICODE);
		}

		$value = trim((string) $value);

		return $value === '' ? NULL : $value;
	}

	/**
	 * Returns the first non-empty value from fields.
	 */
	protected function firstNonEmpty(array $record, array $fields): string
	{
		foreach ($fields as $field) {
			if (isset($record[$field]) && trim((string) $record[$field]) !== '') {
				return trim((string) $record[$field]);
			}
		}

		return '';
	}

	/**
	 * Truncates text safely.
	 */
	protected function truncate(?string $value, int $length): ?string
	{
		if ($value === NULL) {
			return NULL;
		}

		$value = trim($value);

		if ($value === '') {
			return NULL;
		}

		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, $length);
		}

		return substr($value, 0, $length);
	}
}
