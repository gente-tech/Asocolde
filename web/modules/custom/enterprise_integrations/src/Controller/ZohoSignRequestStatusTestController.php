<?php

namespace Drupal\enterprise_integrations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controlador de prueba para consultar estado de un request en Zoho Sign.
 */
class ZohoSignRequestStatusTestController extends ControllerBase {

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
	 * Consulta el estado de un request por query param.
	 */
	public function getRequestStatus(Request $request): JsonResponse	{
		try {
			$request_id = (string) $request->query->get('request_id');

			if (empty($request_id)) {
				throw new \Exception('Debes enviar el query param request_id.');
			}

			$data = $this->zohoSignService->getRequestDetails($request_id);

			return new JsonResponse($data, 200);
		} catch (\Throwable $e) {
			return new JsonResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}
}
