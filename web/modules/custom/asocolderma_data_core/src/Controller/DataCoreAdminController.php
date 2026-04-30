<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;

class DataCoreAdminController extends ControllerBase
{

	/**
	 * Página principal de gestión de data.
	 */
	public function overview(): array
	{
		return [
			'#type' => 'markup',
			'#markup' => '<p>Panel principal de gestión de la data institucional de AsoColDerma.</p>',
		];
	}
}
