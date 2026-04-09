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
			$response = $this->zohoSignService->createDocumentAndGetSignUrl([
				'recipient_name' => 'Virgilio Padilla',
				'recipient_email' => 'vpadillar01@gmail.com',
				'field_text_data' => [
					'Texto-mnnvqbeg' => '1047421571',
					'Texto-mnnvmpuk' => 'Cartagena',
				],
				'notes' => 'Documento generado desde Drupal para probar sign_url',
			]);

			return new JsonResponse($response, 200);
		} catch (\Throwable $e) {
			return new JsonResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}
}
