<?php

/**
 * @file
 * Views integration for AsoColDerma Data Core custom tables.
 */

/**
 * Implements hook_views_data().
 */
function asocolderma_data_core_views_data()
{
	module_load_include('install', 'asocolderma_data_core');

	if (!function_exists('asocolderma_data_core_schema')) {
		return [];
	}

	$schema = asocolderma_data_core_schema();
	$data = [];

	$tables = [
		'asocolderma_import_patrocinadores' => 'Patrocinadores',
		'asocolderma_import_asociados' => 'Asociados',
		'asocolderma_import_residentes' => 'Residentes',
		'asocolderma_import_proveedores' => 'Proveedores',
		'asocolderma_import_empleados' => 'Empleados',

		'asocolderma_record_action_log' => 'Auditoría acciones de registros',
		'asocolderma_export_log' => 'Auditoría exportaciones',

		'asocolderma_data_import_log' => 'Auditoría importaciones',
		'asocolderma_data_import_log_item' => 'Detalle importación',
		'asocolderma_data_import_log_item_change' => 'Cambios de importación',
	];

	foreach ($tables as $table_name => $label) {
		if (empty($schema[$table_name]['fields'])) {
			continue;
		}

		$data[$table_name]['table']['group'] = t('AsoColDerma Data Core');

		$data[$table_name]['table']['base'] = [
			'field' => 'id',
			'title' => t($label),
			'help' => t('Tabla custom de @label.', ['@label' => $label]),
			'weight' => -10,
		];

		foreach ($schema[$table_name]['fields'] as $field_name => $field_definition) {
			$type = $field_definition['type'] ?? 'varchar';

			$views_field_type = asocolderma_data_core_views_field_type($type);
			$views_filter_type = asocolderma_data_core_views_filter_type($type);
			$views_sort_type = asocolderma_data_core_views_sort_type($type);

			$field_data = [
				'title' => t(asocolderma_data_core_views_human_label($field_name)),
				'help' => t('Campo @field de la tabla @table.', [
					'@field' => $field_name,
					'@table' => $table_name,
				]),
				'field' => [
					'id' => $views_field_type,
				],
				'filter' => [
					'id' => $views_filter_type,
				],
				'sort' => [
					'id' => $views_sort_type,
				],
			];

			if (in_array($field_name, ['created', 'finished'], TRUE)) {
				$field_data['field'] = [
					'id' => 'date',
				];

				$field_data['filter'] = [
					'id' => 'date',
				];

				$field_data['sort'] = [
					'id' => 'date',
				];
			}

			$data[$table_name][$field_name] = $field_data;
		}
	}

	return $data;
}

/**
 * Converts database field type to Views field plugin.
 */
function asocolderma_data_core_views_field_type($type)
{
	switch ($type) {
		case 'serial':
		case 'int':
		case 'float':
		case 'numeric':
			return 'numeric';

		case 'text':
		case 'varchar':
		default:
			return 'standard';
	}
}

/**
 * Converts database field type to Views filter plugin.
 */
function asocolderma_data_core_views_filter_type($type)
{
	switch ($type) {
		case 'serial':
		case 'int':
		case 'float':
		case 'numeric':
			return 'numeric';

		case 'text':
		case 'varchar':
		default:
			return 'string';
	}
}

/**
 * Converts database field type to Views sort plugin.
 */
function asocolderma_data_core_views_sort_type($type)
{
	switch ($type) {
		case 'serial':
		case 'int':
		case 'float':
		case 'numeric':
			return 'standard';

		case 'text':
		case 'varchar':
		default:
			return 'standard';
	}
}

/**
 * Converts machine field name to readable label.
 */
function asocolderma_data_core_views_human_label($field_name)
{
	$labels = [
		'id' => 'ID',

		'action' => 'Acción',
		'table_name' => 'Tabla',
		'record_id' => 'ID registro',
		'identifier_type' => 'Tipo identificación',
		'identifier_value' => 'Identificación',
		'record_label' => 'Nombre / Razón social',
		'record_summary' => 'Registro',

		'module_key' => 'Módulo',
		'export_type' => 'Tipo exportación',
		'export_scope' => 'Alcance',
		'record_count' => 'Cantidad registros',
		'filters_applied' => 'Filtros aplicados',
		'export_filename' => 'Archivo exportado',

		'import_uuid' => 'UUID importación',
		'uid' => 'ID usuario',
		'username' => 'Usuario',
		'target_label' => 'Tabla destino',
		'original_filename' => 'Archivo original',
		'stored_filename' => 'Archivo almacenado',
		'file_uri' => 'URI archivo',
		'file_count' => 'Cantidad archivos',
		'total_rows' => 'Filas totales',
		'imported_rows' => 'Filas importadas',
		'failed_rows' => 'Filas fallidas',
		'status' => 'Estado',
		'message' => 'Mensaje',
		'ip' => 'IP',
		'user_agent' => 'User agent',
		'created' => 'Fecha creación',
		'finished' => 'Fecha finalización',
		'duration_seconds' => 'Duración segundos',

		'row_number' => 'Fila',
		'reference_label' => 'Registro',
		'reference_document' => 'NIT / Documento',
		'target_record_id' => 'ID registro destino',
		'operation' => 'Operación',
		'import_log_item_id' => 'ID detalle importación',
		'field_name' => 'Campo modificado',
		'old_value' => 'Valor anterior',
		'new_value' => 'Valor nuevo',

		'ip_address' => 'IP',
		'request_uri' => 'Ruta',
		'notes' => 'Detalle',
	];

	return $labels[$field_name] ?? ucwords(str_replace('_', ' ', $field_name));
}
