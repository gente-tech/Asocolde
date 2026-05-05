<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PatrocinadoresExportController extends ControllerBase
{

	public function exportExcel()
	{
		$ids = \Drupal::request()->query->get('ids');

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

		$query = \Drupal::database()
			->select('asocolderma_import_patrocinadores', 'p')
			->fields('p', array_keys($export_columns))
			->orderBy('p.id', 'DESC');

		if (!empty($ids)) {
			$ids_array = array_filter(array_map('intval', explode(',', $ids)));
			if (!empty($ids_array)) {
				$query->condition('p.id', $ids_array, 'IN');
			}
		}

		$records = $query->execute();

		$filename = 'patrocinadores_export_' . date('Y-m-d_H-i-s') . '.csv';

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
}
