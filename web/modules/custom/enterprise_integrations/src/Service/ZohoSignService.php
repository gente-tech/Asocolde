<?php

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Connection;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Drupal\Core\Url;

/**
 * Servicio para integración con Zoho Sign.
 */
class ZohoSignService
{

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
	 * Conexión a base de datos.
	 *
	 * @var \Drupal\Core\Database\Connection
	 */
	protected Connection $database;

	/**
	 * Constructor.
	 */
	public function __construct(
		ClientInterface $http_client,
		ConfigFactoryInterface $config_factory,
		LoggerChannelFactoryInterface $logger_factory,
		Connection $database
	) {
		$this->httpClient = $http_client;
		$this->configFactory = $config_factory;
		$this->logger = $logger_factory->get('enterprise_integrations');
		$this->database = $database;
	}

	/**
	 * Retorna la configuración de Zoho Sign.
	 */
	protected function getSettings(): array
	{
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
	public function getAccessToken(): string
	{
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
		} catch (\Throwable $e) {
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
	public function getTemplateDetails(): array
	{
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
		} catch (\Throwable $e) {
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
	public function createDocumentFromTemplate(array $data): array
	{
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
				'redirect_pages' => $data['redirect_pages'] ?? [],
			],
		];

		$this->logger->notice('Zoho Sign redirect_pages payload: @redirect_pages', [
			'@redirect_pages' => json_encode($data['redirect_pages'] ?? [], JSON_UNESCAPED_UNICODE),
		]);

		try {
			$response = $this->httpClient->request(
				'POST',
				$settings['api_domain'] . '/api/v1/templates/' . $settings['template_id'] . '/createdocument',
				[
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
				]
			);

			$response_data = json_decode((string) $response->getBody(), TRUE);

			if (empty($response_data) || !is_array($response_data)) {
				throw new \Exception('Zoho retornó una respuesta inválida al crear el documento.');
			}

			return $response_data;
		} catch (RequestException $e) {
			$response = $e->getResponse();
			$status_code = $response ? $response->getStatusCode() : 0;
			$response_body = $response ? (string) $response->getBody() : '';
			$decoded_body = json_decode($response_body, TRUE);

			$this->logger->error('Error creando documento en Zoho Sign. Status: @status. Response: @response. Payload: @payload', [
				'@status' => $status_code,
				'@response' => $response_body,
				'@payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
			]);

			$error_message = $this->extractZohoErrorMessage($response, $decoded_body);
			throw new \Exception('Zoho Sign rechazó la creación del documento: ' . $error_message);
		} catch (\Throwable $e) {
			$this->logger->error('Error creando documento en Zoho Sign: @message. Payload: @payload', [
				'@message' => $e->getMessage(),
				'@payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
			]);
			throw new \Exception('No fue posible crear el documento en Zoho Sign: ' . $e->getMessage());
		}
	}

	/**
	 * Obtiene la URL embebida de firma.
	 *
	 * @param string $request_id
	 *   Request ID de Zoho.
	 * @param string $action_id
	 *   Action ID del firmante.
	 *
	 * @return array
	 *   Respuesta de Zoho.
	 *
	 * @throws \Exception
	 */
	public function getEmbeddedSignUrl(string $request_id, string $action_id): array
	{
		$settings = $this->getSettings();
		$access_token = $this->getAccessToken();

		if (empty($request_id)) {
			throw new \Exception('Falta request_id.');
		}

		if (empty($action_id)) {
			throw new \Exception('Falta action_id.');
		}

		$url = $settings['api_domain'] . '/api/v1/requests/' . $request_id . '/actions/' . $action_id . '/embedtoken';

		$query = [
			'host' => $settings['host'],
		];

		// if (!empty($settings['redirect_url'])) {
		// 	$query['redirect_url'] = $settings['redirect_url'];
		// }

		try {
			$response = $this->httpClient->request('POST', $url, [
				'headers' => [
					'Authorization' => 'Zoho-oauthtoken ' . $access_token,
					'Accept' => 'application/json',
				],
				'query' => $query,
			]);

			$response_data = json_decode((string) $response->getBody(), TRUE);

			if (empty($response_data) || !is_array($response_data)) {
				throw new \Exception('Zoho retornó una respuesta inválida al obtener el sign_url.');
			}

			return $response_data;
		} catch (\Throwable $e) {
			$this->logger->error('Error obteniendo sign_url de Zoho Sign: @message', [
				'@message' => $e->getMessage(),
			]);
			throw new \Exception('No fue posible obtener la URL de firma de Zoho Sign.');
		}
	}

	/**
	 * Crea documento desde plantilla y obtiene la URL de firma embebida.
	 *
	 * @param array $data
	 *   Datos del firmante y campos del documento.
	 *
	 * @return array
	 *   Respuesta consolidada.
	 *
	 * @throws \Exception
	 */
	public function createDocumentAndGetSignUrl(array $data): array
	{
		if (empty($data['solicitud_nid'])) {
			throw new \Exception('Falta solicitud_nid.');
		}

		$template = $this->getTemplateDetails();

		$action_id = $template['templates']['actions'][0]['action_id'] ?? '';
		if (empty($action_id)) {
			throw new \Exception('No fue posible obtener el action_id de la plantilla.');
		}

		$document = $this->createDocumentFromTemplate([
			'action_id' => $action_id,
			'recipient_name' => $data['recipient_name'] ?? '',
			'recipient_email' => $data['recipient_email'] ?? '',
			'field_text_data' => $data['field_text_data'] ?? [],
			'notes' => $data['notes'] ?? '',
		]);

		$request_id = $document['requests']['request_id'] ?? '';
		$request_action_id = $document['requests']['actions'][0]['action_id'] ?? '';
		$document_id = $document['requests']['document_ids'][0]['document_id'] ?? '';
		$zsdocumentid = $document['requests']['zsdocumentid'] ?? '';
		$request_status = $document['requests']['request_status'] ?? 'pending';
		$action_status = $document['requests']['actions'][0]['action_status'] ?? 'UNOPENED';

		if (empty($request_id) || empty($request_action_id)) {
			throw new \Exception('No fue posible obtener request_id o action_id del documento creado.');
		}

		$sign_url_response = $this->getEmbeddedSignUrl($request_id, $request_action_id);
		$sign_url = $sign_url_response['sign_url'] ?? '';

		$this->saveRequestMapping([
			'solicitud_nid' => (int) $data['solicitud_nid'],
			'zoho_request_id' => $request_id,
			'zoho_action_id' => $request_action_id,
			'zoho_document_id' => $document_id,
			'zoho_zsdocumentid' => $zsdocumentid,
			'recipient_name' => $data['recipient_name'] ?? '',
			'recipient_email' => $data['recipient_email'] ?? '',
			'status' => strtolower($request_status),
			'requested_at' => \Drupal::time()->getRequestTime(),
			'payload' => [
				'document_response' => $document,
				'sign_url_response' => $sign_url_response,
			],
		]);

		return [
			'request_id' => $request_id,
			'action_id' => $request_action_id,
			'document_id' => $document_id,
			'zsdocumentid' => $zsdocumentid,
			'request_status' => $request_status,
			'action_status' => $action_status,
			'sign_url' => $sign_url,
			'document_response' => $document,
			'sign_url_response' => $sign_url_response,
		];
	}

	/**
	 * Guarda el mapeo entre la solicitud y el request de Zoho Sign.
	 *
	 * @param array $data
	 *   Datos a persistir.
	 *
	 * @return int
	 *   ID del registro insertado.
	 *
	 * @throws \Exception
	 */
	public function saveRequestMapping(array $data): int
	{
		if (empty($data['solicitud_nid'])) {
			throw new \Exception('Falta solicitud_nid.');
		}

		if (empty($data['zoho_request_id'])) {
			throw new \Exception('Falta zoho_request_id.');
		}

		$now = \Drupal::time()->getRequestTime();

		$insert_id = $this->database->insert('enterprise_integrations_zoho_sign_requests')
			->fields([
				'solicitud_nid' => (int) $data['solicitud_nid'],
				'zoho_request_id' => (string) $data['zoho_request_id'],
				'zoho_action_id' => !empty($data['zoho_action_id']) ? (string) $data['zoho_action_id'] : NULL,
				'zoho_document_id' => !empty($data['zoho_document_id']) ? (string) $data['zoho_document_id'] : NULL,
				'zoho_zsdocumentid' => !empty($data['zoho_zsdocumentid']) ? (string) $data['zoho_zsdocumentid'] : NULL,
				'recipient_name' => !empty($data['recipient_name']) ? (string) $data['recipient_name'] : NULL,
				'recipient_email' => !empty($data['recipient_email']) ? (string) $data['recipient_email'] : NULL,
				'status' => !empty($data['status']) ? (string) $data['status'] : 'pending',
				'requested_at' => !empty($data['requested_at']) ? (int) $data['requested_at'] : $now,
				'payload' => !empty($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : NULL,
				'created' => $now,
				'changed' => $now,
			])
			->execute();

		return (int) $insert_id;
	}

	/**
	 * Crea el request/documento desde plantilla y lo persiste en Drupal.
	 *
	 * Este método NO genera sign_url.
	 *
	 * @param array $data
	 *   Datos del firmante y campos del documento.
	 *
	 * @return array
	 *   Datos consolidados del request creado.
	 *
	 * @throws \Exception
	 */
	public function createSignatureRequest(array $data): array
	{
		if (empty($data['solicitud_nid'])) {
			throw new \Exception('Falta solicitud_nid.');
		}

		$template = $this->getTemplateDetails();

		$action_id = $template['templates']['actions'][0]['action_id'] ?? '';
		if (empty($action_id)) {
			throw new \Exception('No fue posible obtener el action_id de la plantilla.');
		}

		$config = $this->getSettings();

		$base_url = $config['host'] ?: $config['redirect_url'];

		if (empty($base_url)) {
			throw new \Exception('No hay host o redirect_url configurado en Zoho Sign.');
		}

		$base_url = rtrim($base_url, '/');

		$return_url = $base_url . '/solicitud/' . (int) $data['solicitud_nid'] . '/firma/retorno';

		$this->logger->notice('Zoho Sign return_url construido para solicitud @nid: @url', [
			'@nid' => (int) $data['solicitud_nid'],
			'@url' => $return_url,
		]);

		$document = $this->createDocumentFromTemplate([
			'action_id' => $action_id,
			'recipient_name' => $data['recipient_name'] ?? '',
			'recipient_email' => $data['recipient_email'] ?? '',
			'field_text_data' => $data['field_text_data'] ?? [],
			'notes' => $data['notes'] ?? '',
			'redirect_pages' => [
				'sign_success' => $return_url,
				'sign_completed' => $return_url,
				'sign_declined' => $return_url,
				'sign_later' => $return_url,
			],
		]);

		$request_id = $document['requests']['request_id'] ?? '';
		$request_action_id = $document['requests']['actions'][0]['action_id'] ?? '';
		$document_id = $document['requests']['document_ids'][0]['document_id'] ?? '';
		$zsdocumentid = $document['requests']['zsdocumentid'] ?? '';
		$request_status = $document['requests']['request_status'] ?? 'pending';
		$action_status = $document['requests']['actions'][0]['action_status'] ?? 'UNOPENED';

		if (empty($request_id) || empty($request_action_id)) {
			throw new \Exception('No fue posible obtener request_id o action_id del documento creado.');
		}

		$this->saveRequestMapping([
			'solicitud_nid' => (int) $data['solicitud_nid'],
			'zoho_request_id' => $request_id,
			'zoho_action_id' => $request_action_id,
			'zoho_document_id' => $document_id,
			'zoho_zsdocumentid' => $zsdocumentid,
			'recipient_name' => $data['recipient_name'] ?? '',
			'recipient_email' => $data['recipient_email'] ?? '',
			'status' => strtolower($request_status),
			'requested_at' => \Drupal::time()->getRequestTime(),
			'payload' => [
				'document_response' => $document,
			],
		]);

		return [
			'request_id' => $request_id,
			'action_id' => $request_action_id,
			'document_id' => $document_id,
			'zsdocumentid' => $zsdocumentid,
			'request_status' => $request_status,
			'action_status' => $action_status,
			'document_response' => $document,
		];
	}

	/**
	 * Genera una sign_url fresca para un request existente.
	 *
	 * @param string $request_id
	 *   Request ID de Zoho.
	 * @param string $action_id
	 *   Action ID del firmante.
	 *
	 * @return array
	 *   Respuesta consolidada.
	 *
	 * @throws \Exception
	 */
	public function generateFreshSignUrl(string $request_id, string $action_id): array
	{
		$sign_url_response = $this->getEmbeddedSignUrl($request_id, $action_id);
		$sign_url = $sign_url_response['sign_url'] ?? '';

		if (empty($sign_url)) {
			throw new \Exception('Zoho no retornó sign_url.');
		}

		return [
			'request_id' => $request_id,
			'action_id' => $action_id,
			'sign_url' => $sign_url,
			'sign_url_response' => $sign_url_response,
		];
	}

	/**
	 * Retorna el último mapeo guardado para una solicitud.
	 *
	 * @param int $solicitud_nid
	 *   NID de la solicitud.
	 *
	 * @return array|null
	 *   Registro o NULL.
	 */
	public function getLatestRequestMappingBySolicitud(int $solicitud_nid): ?array
	{
		$query = $this->database->select('enterprise_integrations_zoho_sign_requests', 'z');
		$query->fields('z');
		$query->condition('solicitud_nid', $solicitud_nid);
		$query->orderBy('id', 'DESC');
		$query->range(0, 1);

		$record = $query->execute()->fetchAssoc();

		return $record ?: NULL;
	}

	/**
	 * Extrae un mensaje útil desde la respuesta de Zoho.
	 */
	private function extractZohoErrorMessage(?ResponseInterface $response, ?array $decoded_body): string
	{
		if (!$response) {
			return 'Sin respuesta HTTP de Zoho.';
		}

		if (is_array($decoded_body)) {
			if (!empty($decoded_body['message']) && is_string($decoded_body['message'])) {
				return $decoded_body['message'];
			}

			if (!empty($decoded_body['error']['message']) && is_string($decoded_body['error']['message'])) {
				return $decoded_body['error']['message'];
			}

			if (!empty($decoded_body['errors'][0]['message']) && is_string($decoded_body['errors'][0]['message'])) {
				return $decoded_body['errors'][0]['message'];
			}

			if (!empty($decoded_body['code']) || !empty($decoded_body['status'])) {
				return json_encode($decoded_body, JSON_UNESCAPED_UNICODE);
			}
		}

		$body = (string) $response->getBody();
		return $body !== '' ? $body : 'Respuesta vacía de Zoho.';
	}

	/**
	 * Consulta el detalle de un request/documento en Zoho Sign.
	 *
	 * @param string $request_id
	 *   Request ID de Zoho Sign.
	 *
	 * @return array
	 *   Respuesta decodificada.
	 *
	 * @throws \Exception
	 */
	public function getRequestDetails(string $request_id): array
	{
		$settings = $this->getSettings();
		$access_token = $this->getAccessToken();

		if ($request_id === '') {
			throw new \Exception('Falta request_id.');
		}

		try {
			$response = $this->httpClient->request(
				'GET',
				$settings['api_domain'] . '/api/v1/requests/' . $request_id,
				[
					'headers' => [
						'Authorization' => 'Zoho-oauthtoken ' . $access_token,
						'Accept' => 'application/json',
					],
				]
			);

			$response_data = json_decode((string) $response->getBody(), TRUE);

			if (empty($response_data) || !is_array($response_data)) {
				throw new \Exception('Zoho retornó una respuesta inválida al consultar el request.');
			}

			return $response_data;
		} catch (\Throwable $e) {
			$this->logger->error('Error consultando request de Zoho Sign @request_id: @message', [
				'@request_id' => $request_id,
				'@message' => $e->getMessage(),
			]);

			throw new \Exception('No fue posible consultar el estado del documento en Zoho Sign: ' . $e->getMessage());
		}
	}

	/**
	 * Obtiene la URL de descarga del documento firmado.
	 *
	 * @param string $request_id
	 *
	 * @return string|null
	 */
	public function getSignedDocumentUrl(string $request_id): ?string
	{
		$settings = $this->getSettings();
		$access_token = $this->getAccessToken();

		try {
			$response = $this->httpClient->request(
				'GET',
				$settings['api_domain'] . '/api/v1/requests/' . $request_id . '/pdf',
				[
					'headers' => [
						'Authorization' => 'Zoho-oauthtoken ' . $access_token,
					],
				]
			);

			return $settings['api_domain'] . '/api/v1/requests/' . $request_id . '/pdf';
		} catch (\Throwable $e) {
			$this->logger->error('Error obteniendo PDF firmado @request_id: @message', [
				'@request_id' => $request_id,
				'@message' => $e->getMessage(),
			]);

			return NULL;
		}
	}
}
