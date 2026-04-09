<?php

namespace Drupal\enterprise_integrations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controlador de prueba para obtener sign_url de Zoho Sign.
 */
class ZohoSignSignUrlTestController extends ControllerBase {

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
	 * Prueba creación de documento + obtención de sign_url.
	 */
	public function getSignUrl(): JsonResponse	{
		try {
			$template = $this->zohoSignService->getTemplateDetails();

			$action_id = $template['templates']['actions'][0]['action_id'] ?? '';
			if (empty($action_id)) {
				throw new \Exception('No fue posible obtener el action_id de la plantilla.');
			}

			$document = $this->zohoSignService->createDocumentFromTemplate([
				'action_id' => $action_id,
				'recipient_name' => 'Virgilio Padilla',
				'recipient_email' => 'vpadillar01@gmail.com',
				'field_text_data' => [
					'Texto-mnnvqbeg' => '1047421571',
					'Texto-mnnvmpuk' => 'Cartagena',
				],
				'notes' => 'Documento generado desde Drupal para probar sign_url',
			]);

			$request_id = $document['requests']['request_id'] ?? '';
			$request_action_id = $document['requests']['actions'][0]['action_id'] ?? '';

			if (empty($request_id) || empty($request_action_id)) {
				throw new \Exception('No fue posible obtener request_id o action_id del documento creado.');
			}

			$sign_url_response = $this->zohoSignService->getEmbeddedSignUrl($request_id, $request_action_id);

			return new JsonResponse([
				'document_response' => $document,
				'sign_url_response' => $sign_url_response,
			], 200);
		} catch (\Throwable $e) {
			return new JsonResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}
}
