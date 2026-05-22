<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports proveedores data and stores export logs.
 */
class ProveedoresExportController extends ControllerBase {

	/**
	 * Exports proveedores as CSV file compatible with Excel.
	 */
	public function exportExcel() {
		$request = \Drupal::request();

		$ids = (string) $request->query->get('ids', '');
		$ids_array = array_filter(array_map('intval', explode(',', $ids)));

		$export_columns = [
			'id_asocolderma' => 'ID AsoColDerma',
			'estado_proveedor' => 'Estado proveedor',
			'tipo_proveedor' => 'Tipo proveedor',
			'anios_vinculacion_desde' => 'Años vinculación desde',
			'razon_social' => 'Razón social',
			'nombre_comercial' => 'Nombre comercial',
			'nit' => 'NIT',
			'pais' => 'País',
			'ciudad_sede_principal' => 'Ciudad sede principal',
			'direccion_fiscal' => 'Dirección fiscal',
			'sitio_web_corporativo' => 'Sitio web corporativo',
			'nombre_contacto_principal' => 'Nombre contacto principal',
			'cargo_contacto' => 'Cargo contacto',
			'correo_corporativo' => 'Correo corporativo',
			'telefono_corporativo' => 'Teléfono corporativo',
			'celular_contacto' => 'Celular contacto',
			'descripcion_servicio_prestado' => 'Descripción servicio prestado',
			'eventos_proyectos_vinculados' => 'Eventos/proyectos vinculados',
			'responsable_asocolderma_gestiona' => 'Responsable AsoColDerma gestiona',
			'observaciones_generales' => 'Observaciones generales',
			'estado_pago' => 'Estado pago',
			'valor_contrato_vigente' => 'Valor contrato vigente',
			'valor_pagado' => 'Valor pagado',
			'metodo_pago' => 'Método pago',
			'iva' => 'IVA',
			'datos_bancarios' => 'Datos bancarios',
			'copia_contrato_vigente' => 'Copia contrato vigente',
			'copia_rut' => 'Copia RUT',
			'copia_camara_comercio' => 'Copia Cámara de Comercio',
			'copia_cedula_representante' => 'Copia cédula representante',
			'copia_certificacion_bancaria' => 'Copia certificación bancaria',
			'otros_documentos_adjuntos' => 'Otros documentos adjuntos',
			'validation_status' => 'Estado validación',
			'created' => 'Fecha creación',
		];

		$filter_values = $this->getAppliedFilterValues($request->query->all(), array_keys($export_columns));

		$query = \Drupal::database()
			->select('asocolderma_import_proveedores', 'p')
			->fields('p', array_keys($export_columns))
			->orderBy('p.id', 'DESC');

		if (!empty($ids_array)) {
			$query->condition('p.id', $ids_array, 'IN');
		}
		else {
			$this->applyFiltersToQuery($query, $filter_values);
		}

		$records = $query->execute()->fetchAll();
		$record_count = count($records);

		$filename = 'proveedores_export_' . date('Y-m-d_H-i-s') . '.csv';

		$export_scope = $this->resolveExportScope($ids_array, $filter_values);
		$filters_applied = $this->buildFiltersAppliedText($export_scope, $filter_values);
		$notes = $this->buildNotes($export_scope);

		\Drupal::service('asocolderma_data_core.export_logger')->logExport(
			'proveedores',
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

		$allowed_filters = array_merge($allowed_columns, ['is_active']);

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
	protected function applyFiltersToQuery($query, array $filter_values): void {
		foreach ($filter_values as $field => $value) {
			if ($field === 'created') {
				continue;
			}

			/*
			 * El filtro expuesto "Estado" de la vista representa el estado operativo
			 * activo/inactivo del registro, almacenado en is_active.
			 */
			if ($field === 'estado_proveedor' && in_array((string) $value, ['0', '1'], TRUE)) {
				$query->condition('p.is_active', (int) $value);
				continue;
			}

			if ($field === 'is_active' && in_array((string) $value, ['0', '1'], TRUE)) {
				$query->condition('p.is_active', (int) $value);
				continue;
			}

			if ($field === 'validation_status') {
				$query->condition('p.validation_status', $value);
				continue;
			}

			if (in_array($field, ['id_asocolderma', 'valor_contrato_vigente', 'valor_pagado'], TRUE) && is_numeric($value)) {
				$query->condition('p.' . $field, $value);
				continue;
			}

			$query->condition('p.' . $field, '%' . \Drupal::database()->escapeLike($value) . '%', 'LIKE');
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
	protected function buildFiltersAppliedText(string $export_scope, array $filter_values): string {
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
	protected function buildNotes(string $export_scope): string {
		if ($export_scope === 'selected') {
			return 'Exportación Excel de proveedores seleccionados manualmente';
		}

		if ($export_scope === 'filtered') {
			return 'Exportación Excel de proveedores filtrados';
		}

		return 'Exportación Excel completa de proveedores';
	}

	/**
	 * Formats filters as readable plain text.
	 */
	protected function formatFilters(array $filter_values): string {
		$labels = [
			'id_asocolderma' => 'ID AsoColDerma',
			'estado_proveedor' => 'Estado',
			'is_active' => 'Estado',
			'tipo_proveedor' => 'Tipo proveedor',
			'anios_vinculacion_desde' => 'Años vinculación desde',
			'razon_social' => 'Razón social',
			'nombre_comercial' => 'Nombre comercial',
			'nit' => 'NIT',
			'pais' => 'País',
			'ciudad_sede_principal' => 'Ciudad sede principal',
			'direccion_fiscal' => 'Dirección fiscal',
			'sitio_web_corporativo' => 'Sitio web corporativo',
			'nombre_contacto_principal' => 'Nombre contacto principal',
			'cargo_contacto' => 'Cargo contacto',
			'correo_corporativo' => 'Correo corporativo',
			'telefono_corporativo' => 'Teléfono corporativo',
			'celular_contacto' => 'Celular contacto',
			'descripcion_servicio_prestado' => 'Descripción servicio prestado',
			'eventos_proyectos_vinculados' => 'Eventos/proyectos vinculados',
			'responsable_asocolderma_gestiona' => 'Responsable AsoColDerma gestiona',
			'observaciones_generales' => 'Observaciones generales',
			'estado_pago' => 'Estado pago',
			'valor_contrato_vigente' => 'Valor contrato vigente',
			'valor_pagado' => 'Valor pagado',
			'metodo_pago' => 'Método pago',
			'iva' => 'IVA',
			'datos_bancarios' => 'Datos bancarios',
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