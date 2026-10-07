<?php

namespace Drupal\asocolderma_data_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores logs for data exports.
 */
class ExportLogger
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
	 * Logs an export event.
	 */
	public function logExport(
		string $module_key,
		string $export_type,
		string $export_scope,
		int $record_count,
		string $filters_applied,
		string $export_filename,
		string $notes
	): void {
		if (!in_array($export_scope, ['selected', 'filtered', 'all'], TRUE)) {
			throw new \InvalidArgumentException(sprintf('Invalid export scope "%s".', $export_scope));
		}

		$request = $this->requestStack->getCurrentRequest();

		$this->database->insert('asocolderma_export_log')
			->fields([
				'module_key' => $this->truncate($module_key, 50),
				'export_type' => $this->truncate($export_type, 30),
				'export_scope' => $this->truncate($export_scope, 30),
				'record_count' => $record_count,
				'filters_applied' => $filters_applied,
				'export_filename' => $this->truncate($export_filename, 255),
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
