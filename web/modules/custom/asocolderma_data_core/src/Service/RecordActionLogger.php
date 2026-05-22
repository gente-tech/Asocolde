<?php

namespace Drupal\asocolderma_data_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores simple logs for activation, deactivation and definitive deletion.
 */
class RecordActionLogger
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
	 * Constructor.
	 */
	public function __construct(Connection $database, AccountProxyInterface $current_user, RequestStack $request_stack)
	{
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->requestStack = $request_stack;
	}

	/**
	 * Logs a record action.
	 *
	 * @param string $action
	 *   Allowed values: activate, deactivate, delete.
	 * @param string $table_name
	 *   Real database table name.
	 * @param array $record
	 *   Affected record data.
	 * @param string $notes
	 *   Human-readable action description.
	 */
	public function logAction(string $action, string $table_name, array $record, string $notes): void
	{
		if (!in_array($action, ['activate', 'deactivate', 'delete'], TRUE)) {
			throw new \InvalidArgumentException(sprintf('Invalid record action "%s".', $action));
		}

		if (empty($record['id'])) {
			throw new \InvalidArgumentException('The record ID is required to create the action log.');
		}

		$metadata = $this->buildRecordMetadata($table_name, $record);
		$request = $this->requestStack->getCurrentRequest();

		$this->database->insert('asocolderma_record_action_log')
			->fields([
				'action' => $action,
				'table_name' => $table_name,
				'record_id' => (int) $record['id'],
				'identifier_type' => $metadata['identifier_type'],
				'identifier_value' => $metadata['identifier_value'],
				'record_label' => $metadata['record_label'],
				'record_summary' => $metadata['record_summary'],
				'uid' => (int) $this->currentUser->id(),
				'username' => $this->truncate((string) $this->currentUser->getAccountName(), 150),
				'ip_address' => $request ? $this->truncate((string) $request->getClientIp(), 45) : NULL,
				'request_uri' => $request ? $this->truncate((string) $request->getRequestUri(), 500) : NULL,
				'created' => time(),
				'notes' => $notes,
			])
			->execute();
	}

	/**
	 * Builds identifier and label metadata depending on the affected table.
	 */
	protected function buildRecordMetadata(string $table_name, array $record): array
	{
		switch ($table_name) {
			case 'asocolderma_import_patrocinadores':
			case 'asocolderma_import_proveedores':
				$identifier_type = 'nit';
				$identifier_value = $this->getFirstAvailableValue($record, ['nit', 'id_asocolderma']);
				$record_label = $this->getFirstAvailableValue($record, ['razon_social', 'nombre_comercial']);
				break;

			case 'asocolderma_import_asociados':
			case 'asocolderma_import_residentes':
			case 'asocolderma_import_empleados':
				$identifier_type = 'documento';
				$identifier_value = $this->getFirstAvailableValue($record, ['numero_documento', 'id_asocolderma']);
				$record_label = $this->buildFullName($record);
				break;

			default:
				$identifier_type = 'id';
				$identifier_value = (string) ($record['id'] ?? '');
				$record_label = $this->getFirstAvailableValue($record, ['nombre_comercial', 'razon_social', 'primer_nombre', 'id_asocolderma']);
				break;
		}

		if ($record_label === '') {
			$record_label = 'Registro ID ' . (string) ($record['id'] ?? '');
		}

		$prefix = $identifier_type === 'nit' ? 'NIT' : 'Documento';

		if ($identifier_value === '') {
			$record_summary = $record_label;
		} else {
			$record_summary = $prefix . ' ' . $identifier_value . ' - ' . $record_label;
		}

		return [
			'identifier_type' => $this->truncate($identifier_type, 50),
			'identifier_value' => $this->truncate($identifier_value, 100),
			'record_label' => $this->truncate($record_label, 255),
			'record_summary' => $record_summary,
		];
	}

	/**
	 * Builds a full name from common name fields.
	 */
	protected function buildFullName(array $record): string
	{
		$parts = [
			$record['primer_nombre'] ?? '',
			$record['segundo_nombre'] ?? '',
			$record['primer_apellido'] ?? '',
			$record['segundo_apellido'] ?? '',
		];

		$parts = array_filter(array_map(static function ($value) {
			return trim((string) $value);
		}, $parts));

		return trim(implode(' ', $parts));
	}

	/**
	 * Returns the first non-empty value from a list of fields.
	 */
	protected function getFirstAvailableValue(array $record, array $fields): string
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
