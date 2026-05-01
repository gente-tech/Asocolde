<?php

/**
 * @file
 * Views integration for AsoColDerma custom data tables.
 */

/**
 * Implements hook_views_data().
 */
function asocolderma_data_core_views_data()
{
	$data = [];

	$tables = [
		'asocolderma_import_patrocinadores' => 'Patrocinadores',
		'asocolderma_import_asociados' => 'Asociados',
		'asocolderma_import_residentes' => 'Residentes',
		'asocolderma_import_proveedores' => 'Proveedores',
		'asocolderma_import_empleados' => 'Empleados',
	];

	foreach ($tables as $table_name => $label) {
		$data[$table_name]['table']['group'] = t('AsoColDerma Data Core');

		$data[$table_name]['table']['base'] = [
			'field' => 'id',
			'title' => t($label),
			'help' => t('Tabla custom de @label.', ['@label' => $label]),
			'weight' => -10,
		];

		$data[$table_name]['id'] = [
			'title' => t('ID'),
			'help' => t('ID interno del registro.'),
			'field' => ['id' => 'numeric'],
			'filter' => ['id' => 'numeric'],
			'sort' => ['id' => 'standard'],
		];

		$data[$table_name]['created'] = [
			'title' => t('Fecha de creación'),
			'help' => t('Fecha de creación del registro.'),
			'field' => ['id' => 'date'],
			'filter' => ['id' => 'date'],
			'sort' => ['id' => 'date'],
		];

		$data[$table_name]['changed'] = [
			'title' => t('Última modificación'),
			'help' => t('Fecha de última modificación del registro.'),
			'field' => ['id' => 'date'],
			'filter' => ['id' => 'date'],
			'sort' => ['id' => 'date'],
		];
	}

	return $data;
}
