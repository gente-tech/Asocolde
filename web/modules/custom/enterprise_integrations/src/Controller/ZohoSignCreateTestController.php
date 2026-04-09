<?php

namespace Drupal\enterprise_integrations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controlador de prueba para crear documento en Zoho Sign.
 */
class ZohoSignCreateTestController extends ControllerBase {

	/**
	 * Servicio Zoho Sign.
	 *
	 * @var \Drupal\enterprise_integrations\Service\ZohoSignService
	 */
	protected ZohoSignService $zohoSignService;

	/**
	 * Constructor.
	 */
	public function __construct(ZohoSignService $zoho_sign_service)	{
		$this->zohoSignService = $zoho_sign_service;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): self	{
		return new static(
			$container->get('enterprise_integrations.zoho_sign')
		);
	}

	/**
	 * Prueba creación de documento desde plantilla.
	 */
	public function createDocument(): JsonResponse	{
		try {
			$template = $this->zohoSignService->getTemplateDetails();

			$action_id = $template['templates']['actions'][0]['action_id'] ?? '';
			if (empty($action_id)) {
				throw new \Exception('No fue posible obtener el action_id de la plantilla.');
			}

			$data = $this->zohoSignService->createDocumentFromTemplate([
				'action_id' => $action_id,
				'recipient_name' => 'Virgilio Padilla',
				'recipient_email' => 'vpadillar01@gmail.com',
				'field_text_data' => [
					'Texto-mnnvqbeg' => '1047421571',
					'Texto-mnnvmpuk' => 'Cartagena',
				],
				'notes' => 'Documento generado desde Drupal de prueba',
			]);

			return new JsonResponse($data, 200);
		} catch (\Throwable $e) {
			return new JsonResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}
}
