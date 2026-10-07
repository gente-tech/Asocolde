<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\asocolderma_data_core\Service\RecordActionLogger;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles bulk activation, deactivation and definitive deletion.
 */
class DataBulkActionController extends ControllerBase
{

	/**
	 * Database connection.
	 */
	protected Connection $database;

	/**
	 * Record action logger.
	 */
	protected RecordActionLogger $recordActionLogger;

	/**
	 * Constructor.
	 */
	public function __construct(Connection $database, AccountProxyInterface $current_user, RecordActionLogger $record_action_logger)
	{
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->recordActionLogger = $record_action_logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): self
	{
		return new static(
			$container->get('database'),
			$container->get('current_user'),
			$container->get('asocolderma_data_core.record_action_logger')
		);
	}

	/**
	 * Activates or deactivates selected records.
	 */
	public function bulkStatus(Request $request): JsonResponse
	{
		$payload = $this->getJsonPayload($request);

		$table_key = (string) ($payload['table'] ?? '');
		$operation = (string) ($payload['operation'] ?? '');
		$ids = $this->normalizeIds($payload['ids'] ?? []);

		if (!in_array($operation, ['activate', 'deactivate'], TRUE)) {
			return $this->errorResponse('La operación enviada no es válida.', 400);
		}

		if (!$ids) {
			return $this->errorResponse('Debe seleccionar al menos un registro.', 400);
		}

		$config = $this->getTableConfig($table_key);

		if (!$config) {
			return $this->errorResponse('La tabla enviada no está permitida.', 400);
		}

		$table_name = $config['table'];
		$is_active = $operation === 'activate' ? 1 : 0;
		$notes = $operation === 'activate'
			? 'Registro activado desde acciones masivas'
			: 'Registro desactivado desde acciones masivas';

		$processed = 0;
		$not_found = [];

		$transaction = $this->database->startTransaction();

		try {
			foreach ($ids as $id) {
				$record = $this->loadRecord($table_name, $id);

				if (!$record) {
					$not_found[] = $id;
					continue;
				}

				$this->database->update($table_name)
					->fields([
						'is_active' => $is_active,
						'status_changed' => time(),
						'status_changed_by' => (int) $this->currentUser->id(),
						'status_change_reason' => $notes,
					])
					->condition('id', $id)
					->execute();

				$this->recordActionLogger->logAction($operation, $table_name, $record, $notes);
				$processed++;
			}
		} catch (\Throwable $e) {
			$transaction->rollBack();
			$this->getLogger('asocolderma_data_core')->error('Bulk status action failed: @message', [
				'@message' => $e->getMessage(),
			]);

			return $this->errorResponse('No fue posible ejecutar la acción. Revise el log del sistema.', 500);
		}

		$message = $operation === 'activate'
			? 'Registros activados correctamente.'
			: 'Registros desactivados correctamente.';

		return new JsonResponse([
			'success' => TRUE,
			'processed' => $processed,
			'not_found' => $not_found,
			'message' => $message,
		]);
	}

	/**
	 * Definitively deletes selected records.
	 */
	public function bulkDelete(Request $request): JsonResponse
	{
		$payload = $this->getJsonPayload($request);

		$table_key = (string) ($payload['table'] ?? '');
		$ids = $this->normalizeIds($payload['ids'] ?? []);

		if (!$ids) {
			return $this->errorResponse('Debe seleccionar al menos un registro.', 400);
		}

		$config = $this->getTableConfig($table_key);

		if (!$config) {
			return $this->errorResponse('La tabla enviada no está permitida.', 400);
		}

		$table_name = $config['table'];
		$notes = 'Registro eliminado definitivamente desde acciones masivas';

		$processed = 0;
		$not_found = [];

		$transaction = $this->database->startTransaction();

		try {
			foreach ($ids as $id) {
				$record = $this->loadRecord($table_name, $id);

				if (!$record) {
					$not_found[] = $id;
					continue;
				}

				$this->recordActionLogger->logAction('delete', $table_name, $record, $notes);

				$this->database->delete($table_name)
					->condition('id', $id)
					->execute();

				$processed++;
			}
		} catch (\Throwable $e) {
			$transaction->rollBack();
			$this->getLogger('asocolderma_data_core')->error('Bulk delete action failed: @message', [
				'@message' => $e->getMessage(),
			]);

			return $this->errorResponse('No fue posible eliminar los registros. Revise el log del sistema.', 500);
		}

		return new JsonResponse([
			'success' => TRUE,
			'processed' => $processed,
			'not_found' => $not_found,
			'message' => 'Registros eliminados definitivamente.',
		]);
	}

	/**
	 * Returns decoded JSON payload.
	 */
	protected function getJsonPayload(Request $request): array
	{
		$content = (string) $request->getContent();

		if ($content === '') {
			return [];
		}

		$payload = json_decode($content, TRUE);

		return is_array($payload) ? $payload : [];
	}

	/**
	 * Normalizes selected IDs.
	 */
	protected function normalizeIds(mixed $ids): array
	{
		if (!is_array($ids)) {
			return [];
		}

		$ids = array_map('intval', $ids);
		$ids = array_filter($ids, static function (int $id): bool {
			return $id > 0;
		});

		return array_values(array_unique($ids));
	}

	/**
	 * Loads one record by ID.
	 */
	protected function loadRecord(string $table_name, int $id): ?array
	{
		$record = $this->database->select($table_name, 't')
			->fields('t')
			->condition('id', $id)
			->execute()
			->fetchAssoc();

		return $record ?: NULL;
	}

	/**
	 * Allowed source tables.
	 */
	protected function getTableConfig(string $table_key): ?array
	{
		$tables = [
			'patrocinadores' => [
				'table' => 'asocolderma_import_patrocinadores',
			],
			'asociados' => [
				'table' => 'asocolderma_import_asociados',
			],
			'residentes' => [
				'table' => 'asocolderma_import_residentes',
			],
			'proveedores' => [
				'table' => 'asocolderma_import_proveedores',
			],
			'empleados' => [
				'table' => 'asocolderma_import_empleados',
			],
		];

		return $tables[$table_key] ?? NULL;
	}

	/**
	 * Returns a normalized JSON error response.
	 */
	protected function errorResponse(string $message, int $status_code): JsonResponse
	{
		return new JsonResponse([
			'success' => FALSE,
			'message' => $message,
		], $status_code);
	}
}
