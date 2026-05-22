<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base controller for institutional data exports.
 */
abstract class DataExportBaseController extends ControllerBase {

	/**
	 * Returns export configuration for each module.
	 */
	abstract protected function getExportConfig(): array;

	/**
	 * Exports data as CSV file compatible with Excel.
	 */
	public function exportExcel() {
		$config = $this->getExportConfig();
		$request = \Drupal::request();

		$ids = (string) $request->query->get('ids', '');
		$ids_array = array_filter(array_map('intval', explode(',', $ids)));

		$export_columns = $config['columns'];
		$filter_values = $this->getAppliedFilterValues($request->query->all(), array_keys($export_columns));

		$database = \Drupal::database();
		$alias = $config['alias'];

		$query = $database
			->select($config['table_name'], $alias)
			->fields($alias, array_keys($export_columns))
			->orderBy($alias . '.id', 'DESC');

		if (!empty($ids_array)) {
			$query->condition($alias . '.id', $ids_array, 'IN');
		}
		else {
			$this->applyFiltersToQuery($query, $filter_values, $config);
		}

		$records = $query->execute()->fetchAll();
		$record_count = count($records);

		$filename = $config['filename_prefix'] . '_' . date('Y-m-d_H-i-s') . '.csv';

		$export_scope = $this->resolveExportScope($ids_array, $filter_values);
		$filters_applied = $this->buildFiltersAppliedText($export_scope, $filter_values, $config);
		$notes = $this->buildNotes($export_scope, $config);

		\Drupal::service('asocolderma_data_core.export_logger')->logExport(
			$config['module_key'],
			'excel',
			$export_scope,
			$record_count,
			$filters_applied,
			$filename,
			$notes
		);

		$response = new StreamedResponse(function () use ($records, $export_columns) {
			$handle = fopen('php://output', 'w');

			// BOM para que Excel abra correctamente tildes y ñ.
			fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

			fputcsv($handle, array_values($export_columns));

			foreach ($records as $record) {
				$row = [];

				foreach (array_keys($export_columns) as $column) {
					$value = $record->{$column} ?? '';

					if ($column === 'created' && !empty($value)) {
						$value = date('Y-m-d H:i:s', (int) $value);
					}

					$row[] = $value;
				}

				fputcsv($handle, $row);
			}

			fclose($handle);
		});

		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT,
			$filename
		));

		return $response;
	}

	/**
	 * Extracts valid filters from current query string.
	 */
	protected function getAppliedFilterValues(array $query_params, array $allowed_columns): array {
		$ignored_params = [
			'ids',
			'page',
			'sort',
			'order',
			'q',
			'_wrapper_format',
			'ajax_page_state',
		];

		$ignored_values = [
			'all',
			'todos',
			'todas',
			'- any -',
			'any',
			'_none',
			'none',
		];

		$allowed_filters = array_merge($allowed_columns, ['is_active', 'combine']);
		$filters = [];

		foreach ($query_params as $key => $value) {
			if (in_array($key, $ignored_params, TRUE)) {
				continue;
			}

			if (!in_array($key, $allowed_filters, TRUE)) {
				continue;
			}

			if (is_array($value)) {
				$value = implode(', ', array_filter(array_map('trim', $value)));
			}

			$value = trim((string) $value);

			if ($value === '') {
				continue;
			}

			if (in_array(mb_strtolower($value), $ignored_values, TRUE)) {
				continue;
			}

			$filters[$key] = $value;
		}

		return $filters;
	}

	/**
	 * Applies supported filters to the export query.
	 */
	protected function applyFiltersToQuery($query, array $filter_values, array $config): void {
		$database = \Drupal::database();
		$alias = $config['alias'];
		$status_field = $config['status_field'] ?? NULL;
		$numeric_fields = $config['numeric_fields'] ?? [];

		foreach ($filter_values as $field => $value) {
			if ($field === 'created') {
				continue;
			}

			if ($field === 'combine') {
				$or = $query->orConditionGroup();

				foreach ($config['combine_fields'] as $combine_field) {
					$or->condition($alias . '.' . $combine_field, '%' . $database->escapeLike($value) . '%', 'LIKE');
				}

				$query->condition($or);
				continue;
			}

			if ($field === 'is_active' && in_array((string) $value, ['0', '1'], TRUE)) {
				$query->condition($alias . '.is_active', (int) $value);
				continue;
			}

			if ($status_field && $field === $status_field && in_array((string) $value, ['0', '1'], TRUE)) {
				$query->condition($alias . '.is_active', (int) $value);
				continue;
			}

			if ($field === 'validation_status') {
				$query->condition($alias . '.validation_status', $value);
				continue;
			}

			if (in_array($field, $numeric_fields, TRUE) && is_numeric($value)) {
				$query->condition($alias . '.' . $field, $value);
				continue;
			}

			$query->condition($alias . '.' . $field, '%' . $database->escapeLike($value) . '%', 'LIKE');
		}
	}

	/**
	 * Resolves export scope.
	 */
	protected function resolveExportScope(array $ids_array, array $filter_values): string {
		if (!empty($ids_array)) {
			return 'selected';
		}

		if (!empty($filter_values)) {
			return 'filtered';
		}

		return 'all';
	}

	/**
	 * Builds human-readable filter text.
	 */
	protected function buildFiltersAppliedText(string $export_scope, array $filter_values, array $config): string {
		if ($export_scope === 'all') {
			return 'Sin filtros aplicados';
		}

		if ($export_scope === 'selected') {
			if (empty($filter_values)) {
				return 'Registros seleccionados manualmente desde la vista, sin filtros previos aplicados';
			}

			return 'Filtros aplicados antes de la selección: ' . $this->formatFilters($filter_values, $config) . '. Exportación realizada sobre registros seleccionados manualmente';
		}

		if ($export_scope === 'filtered') {
			return 'Filtros aplicados: ' . $this->formatFilters($filter_values, $config);
		}

		return 'Sin filtros aplicados';
	}

	/**
	 * Builds export notes.
	 */
	protected function buildNotes(string $export_scope, array $config): string {
		if ($export_scope === 'selected') {
			return 'Exportación Excel de ' . $config['module_label'] . ' seleccionados manualmente';
		}

		if ($export_scope === 'filtered') {
			return 'Exportación Excel de ' . $config['module_label'] . ' filtrados';
		}

		return 'Exportación Excel completa de ' . $config['module_label'];
	}

	/**
	 * Formats filters as readable plain text.
	 */
	protected function formatFilters(array $filter_values, array $config): string {
		$labels = $config['labels'];
		$parts = [];

		foreach ($filter_values as $field => $value) {
			$label = $labels[$field] ?? $field;
			$parts[] = $label . ' = ' . $value;
		}

		return implode(', ', $parts);
	}

}