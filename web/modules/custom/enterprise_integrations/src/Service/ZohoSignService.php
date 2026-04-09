<?php

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Servicio para integración con Zoho Sign.
 */
class ZohoSignService {

	/**
	 * Cliente HTTP.
	 *
	 * @var \GuzzleHttp\ClientInterface
	 */
	protected ClientInterface $httpClient;

	/**
	 * Config factory.
	 *
	 * @var \Drupal\Core\Config\ConfigFactoryInterface
	 */
	protected ConfigFactoryInterface $configFactory;

	/**
	 * Logger.
	 *
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Constructor.
	 */
	public function __construct(
		ClientInterface $http_client,
		ConfigFactoryInterface $config_factory,
		LoggerChannelFactoryInterface $logger_factory
	) {
		$this->httpClient = $http_client;
		$this->configFactory = $config_factory;
		$this->logger = $logger_factory->get('enterprise_integrations');
	}

	/**
	 * Retorna la configuración de Zoho Sign.
	 */
	protected function getSettings(): array	{
		$config = $this->configFactory->get('enterprise_integrations.zoho_sign_settings');

		return [
			'client_id' => (string) $config->get('client_id'),
			'client_secret' => (string) $config->get('client_secret'),
			'refresh_token' => (string) $config->get('refresh_token'),
			'accounts_domain' => rtrim((string) $config->get('accounts_domain'), '/'),
			'api_domain' => rtrim((string) $config->get('api_domain'), '/'),
			'oauth_api_domain' => rtrim((string) $config->get('oauth_api_domain'), '/'),
			'template_id' => (string) $config->get('template_id'),
			'webhook_url' => (string) $config->get('webhook_url'),
			'redirect_url' => (string) $config->get('redirect_url'),
			'host' => rtrim((string) $config->get('host'), '/'),
		];
	}

	/**
	 * Genera un access token usando el refresh token.
	 *
	 * @return string
	 *   Access token.
	 *
	 * @throws \Exception
	 */
  	public function getAccessToken(): string {
		$settings = $this->getSettings();

		try {
		$response = $this->httpClient->request('POST', $settings['accounts_domain'] . '/oauth/v2/token', [
			'form_params' => [
			'grant_type' => 'refresh_token',
			'refresh_token' => $settings['refresh_token'],
			'client_id' => $settings['client_id'],
			'client_secret' => $settings['client_secret'],
			],
			'headers' => [
			'Accept' => 'application/json',
			],
		]);

		$data = json_decode((string) $response->getBody(), TRUE);

		if (empty($data['access_token'])) {
			throw new \Exception('Zoho no retornó access_token.');
		}

		return $data['access_token'];
		}
		catch (\Throwable $e) {
		$this->logger->error('Error obteniendo access token de Zoho Sign: @message', [
			'@message' => $e->getMessage(),
		]);
		throw new \Exception('No fue posible obtener el access token de Zoho Sign.');
		}
	}

	/**
	 * Consulta el detalle de la plantilla configurada.
	 *
	 * @return array
	 *   Respuesta de Zoho.
	 *
	 * @throws \Exception
	 */
	public function getTemplateDetails(): array {
		$settings = $this->getSettings();
		$access_token = $this->getAccessToken();

		if (empty($settings['template_id'])) {
		throw new \Exception('No hay template_id configurado para Zoho Sign.');
		}

		try {
		$response = $this->httpClient->request('GET', $settings['api_domain'] . '/api/v1/templates/' . $settings['template_id'], [
			'headers' => [
			'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			'Accept' => 'application/json',
			],
		]);

		$data = json_decode((string) $response->getBody(), TRUE);

		if (empty($data) || !is_array($data)) {
			throw new \Exception('Zoho retornó una respuesta inválida al consultar la plantilla.');
		}

		return $data;
		}
		catch (\Throwable $e) {
		$this->logger->error('Error consultando template de Zoho Sign: @message', [
			'@message' => $e->getMessage(),
		]);
		throw new \Exception('No fue posible consultar la plantilla de Zoho Sign.');
		}
	}

	/**
	 * Crea un documento desde la plantilla de Zoho Sign.
	 *
	 * @param array $data
	 *   Datos para construir el documento.
	 *
	 * @return array
	 *   Respuesta de Zoho.
	 *
	 * @throws \Exception
	 */
	public function createDocumentFromTemplate(array $data): array {
		$settings = $this->getSettings();
		$access_token = $this->getAccessToken();

		if (empty($settings['template_id'])) {
			throw new \Exception('No hay template_id configurado para Zoho Sign.');
		}

		if (empty($data['action_id'])) {
			throw new \Exception('Falta action_id.');
		}

		if (empty($data['recipient_name'])) {
			throw new \Exception('Falta recipient_name.');
		}

		if (empty($data['recipient_email'])) {
			throw new \Exception('Falta recipient_email.');
		}

		$payload = [
			'templates' => [
				'actions' => [
					[
						'action_id' => $data['action_id'],
						'recipient_name' => $data['recipient_name'],
						'recipient_email' => $data['recipient_email'],
						'verify_recipient' => FALSE,
						'is_embedded' => TRUE,
					],
				],
				'field_data' => [
					'field_text_data' => $data['field_text_data'] ?? [],
				],
				'notes' => $data['notes'] ?? '',
			],	
		];

		try {
			$response = $this->httpClient->request('POST', $settings['api_domain'] . '/api/v1/templates/' . $settings['template_id'] . '/createdocument', [
				'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Accept' => 'application/json',
				],
				'multipart' => [
				[
					'name' => 'data',
					'contents' => json_encode($payload, JSON_UNESCAPED_UNICODE),
				],
				],
			]);

			$response_data = json_decode((string) $response->getBody(), TRUE);

			if (empty($response_data) || !is_array($response_data)) {
				throw new \Exception('Zoho retornó una respuesta inválida al crear el documento.');
			}

			return $response_data;
		}
		catch (\Throwable $e) {
			$this->logger->error('Error creando documento en Zoho Sign: @message', [
				'@message' => $e->getMessage(),
			]);
			throw new \Exception('No fue posible crear el documento en Zoho Sign.');
		}
	}
}
