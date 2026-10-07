<?php

namespace Drupal\enterprise_integrations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controlador de prueba para Zoho Sign.
 */
class ZohoSignTestController extends ControllerBase {

  /**
   * Servicio Zoho Sign.
   *
   * @var \Drupal\enterprise_integrations\Service\ZohoSignService
   */
  protected ZohoSignService $zohoSignService;

  /**
   * Constructor.
   */
  public function __construct(ZohoSignService $zoho_sign_service) {
    $this->zohoSignService = $zoho_sign_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('enterprise_integrations.zoho_sign')
    );
  }

  /**
   * Prueba consulta de plantilla.
   */
  public function testTemplate(): JsonResponse {
    try {
      $data = $this->zohoSignService->getTemplateDetails();
      return new JsonResponse($data, 200);
    }
    catch (\Throwable $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

}