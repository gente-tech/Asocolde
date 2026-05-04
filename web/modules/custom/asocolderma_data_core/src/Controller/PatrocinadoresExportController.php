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

		$filename = 'patrocinadores_export_' . date('Y-m-d_H-i-s') . '.csv';

		$response = new StreamedResponse(function () use ($ids) {
			$handle = fopen('php://output', 'w');

			fputcsv($handle, [
				'IDs seleccionados',
			]);

			fputcsv($handle, [
				$ids ?: 'none',
			]);

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
