<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class PatrocinadoresExportController extends ControllerBase
{

	public function exportPdf()
	{
		return new Response('Export PDF OK');
	}
}
