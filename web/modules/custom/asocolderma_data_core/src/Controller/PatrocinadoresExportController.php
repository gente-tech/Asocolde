<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class PatrocinadoresExportController extends ControllerBase
{

	public function exportExcel()
	{
		$ids = \Drupal::request()->query->get('ids');

		return new Response('Export Excel OK | IDs: ' . ($ids ?: 'none'));
	}
}
