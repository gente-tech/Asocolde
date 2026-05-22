<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Exports patrocinadores data and stores export logs.
 */
class PatrocinadoresExportController extends ControllerBase
{

	/**
	 * Exports patrocinadores as CSV file compatible with Excel.
	 */
	public function exportExcel()
	{
		$request = \Drupal::request();

		$ids = (string) $request->query->get('ids', '');
		$ids_array = array_filter(array_map('intval', explode(',', $ids)));

		$export_columns = [
			'id_asocolderma' => 'ID AsoColDerma',
			'estado_patrocinador' => 'Estado',
			'razon_social' => 'Razón social',
			'nombre_comercial' => 'Nombre comercial',
			'nit' => 'NIT',
			'pais' => 'País',
			'ciudad_sede_principal' => 'Ciudad sede principal',
			'nombre_contacto_principal' => 'Nombre contacto principal',
			'cargo_contacto' => 'Cargo contacto',
			'correo_corporativo' => 'Correo corporativo',
			'telefono_corporativo' => 'Teléfono corporativo',
			'celular_contacto' => 'Celular contacto',
			'tipo_patrocinador' => 'Tipo patrocinador',
			'anios_vinculacion' => 'Años vinculación',
			'contacto_comercial_asocolderma' => 'Contacto comercial AsoColDerma',
			'validation_status' => 'Estado validación',
			'created' => 'Fecha creación',
		];

		$filter_values = $this->getAppliedFilterValues($request->query->all(), array_keys($export_columns));

		$query = \Drupal::database()
			->select('asocolderma_import_patrocinadores', 'p')
			->fields('p', array_keys($export_columns))
			->orderBy('p.id', 'DESC');

		if (!empty($ids_array)) {
			$query->condition('p.id', $ids_array, 'IN');
		} else {
			$this->applyFiltersToQuery($query, $filter_values);
		}

		$records = $query->execute()->fetchAll();
		$record_count = count($records);

		$filename = 'patrocinadores_export_' . date('Y-m-d_H-i-s') . '.csv';

		$export_scope = $this->resolveExportScope($ids_array, $filter_values);
		$filters_applied = $this->buildFiltersAppliedText($export_scope, $filter_values);
		$notes = $this->buildNotes($export_scope);

		\Drupal::service('asocolderma_data_core.export_logger')->logExport(
			'patrocinadores',
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
	protected function getAppliedFilterValues(array $query_params, array $allowed_columns): array
	{
		$ignored_params = [
			'ids',
			'page',
			'sort',
			'order',
			'q',
			'_wrapper_format',
			'ajax_page_state',
		];

		$filters = [];

		foreach ($query_params as $key => $value) {
			if (in_array($key, $ignored_params, TRUE)) {
				continue;
			}

			if (!in_array($key, $allowed_columns, TRUE)) {
				continue;
			}

			if (is_array($value)) {
				$value = implode(', ', array_filter(array_map('trim', $value)));
			}

			$value = trim((string) $value);

			if ($value === '') {
				continue;
			}

			$filters[$key] = $value;
		}

		return $filters;
	}

	/**
	 * Applies supported filters to the export query.
	 */
	protected function applyFiltersToQuery($query, array $filter_values): void
	{
		foreach ($filter_values as $field => $value) {
			if ($field === 'created') {
				continue;
			}

			if (in_array($field, ['id_asocolderma', 'anios_vinculacion'], TRUE) && is_numeric($value)) {
				$query->condition('p.' . $field, (int) $value);
				continue;
			}

			$query->condition('p.' . $field, '%' . \Drupal::database()->escapeLike($value) . '%', 'LIKE');
		}
	}

	/**
	 * Resolves export scope.
	 */
	protected function resolveExportScope(array $ids_array, array $filter_values): string
	{
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
	protected function buildFiltersAppliedText(string $export_scope, array $filter_values): string
	{
		if ($export_scope === 'all') {
			return 'Sin filtros aplicados';
		}

		if ($export_scope === 'selected') {
			if (empty($filter_values)) {
				return 'Registros seleccionados manualmente desde la vista, sin filtros previos aplicados';
			}

			return 'Filtros aplicados antes de la selección: ' . $this->formatFilters($filter_values) . '. Exportación realizada sobre registros seleccionados manualmente';
		}

		if ($export_scope === 'filtered') {
			return 'Filtros aplicados: ' . $this->formatFilters($filter_values);
		}

		return 'Sin filtros aplicados';
	}

	/**
	 * Builds export notes.
	 */
	protected function buildNotes(string $export_scope): string
	{
		if ($export_scope === 'selected') {
			return 'Exportación Excel de patrocinadores seleccionados manualmente';
		}

		if ($export_scope === 'filtered') {
			return 'Exportación Excel de patrocinadores filtrados';
		}

		return 'Exportación Excel completa de patrocinadores';
	}

	/**
	 * Formats filters as readable plain text.
	 */
	protected function formatFilters(array $filter_values): string
	{
		$labels = [
			'id_asocolderma' => 'ID AsoColDerma',
			'estado_patrocinador' => 'Estado',
			'razon_social' => 'Razón social',
			'nombre_comercial' => 'Nombre comercial',
			'nit' => 'NIT',
			'pais' => 'País',
			'ciudad_sede_principal' => 'Ciudad sede principal',
			'nombre_contacto_principal' => 'Nombre contacto principal',
			'cargo_contacto' => 'Cargo contacto',
			'correo_corporativo' => 'Correo corporativo',
			'telefono_corporativo' => 'Teléfono corporativo',
			'celular_contacto' => 'Celular contacto',
			'tipo_patrocinador' => 'Tipo patrocinador',
			'anios_vinculacion' => 'Años vinculación',
			'contacto_comercial_asocolderma' => 'Contacto comercial AsoColDerma',
			'validation_status' => 'Estado validación',
			'created' => 'Fecha creación',
		];

		$parts = [];

		foreach ($filter_values as $field => $value) {
			$label = $labels[$field] ?? $field;
			$parts[] = $label . ' = ' . $value;
		}

		return implode(', ', $parts);
	}
}
