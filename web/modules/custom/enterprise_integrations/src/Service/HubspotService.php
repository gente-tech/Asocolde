<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

final class HubspotService
{

	/**
	 * HTTP client.
	 */
	protected ClientInterface $httpClient;

	/**
	 * Drupal config factory.
	 */
	protected ConfigFactoryInterface $configFactory;

	/**
	 * Logger channel.
	 */
	protected LoggerChannelInterface $logger;

	public function __construct(
		ClientInterface $httpClient,
		ConfigFactoryInterface $configFactory,
		LoggerChannelInterface $logger
	) {
		$this->httpClient = $httpClient;
		$this->configFactory = $configFactory;
		$this->logger = $logger;
	}

	/**
	 * Crea un contacto en HubSpot.
	 *
	 * @param array $data
	 *   Estructura esperada:
	 *   - email: string
	 *   - firstname: string|null
	 *   - lastname: string|null
	 *   - phone: string|null
	 *
	 * @return array
	 *   Respuesta estructurada.
	 */
	public function createContact(array $data): array
	{
		$config = $this->configFactory->get('enterprise_integrations.hubspot_settings');

		$enabled = (bool) $config->get('hubspot_enabled');
		$token = (string) $config->get('hubspot_token');
		$apiUrl = rtrim((string) $config->get('hubspot_api_url'), '/');

		if (!$enabled) {
			return [
				'success' => FALSE,
				'message' => 'La integración con HubSpot está deshabilitada.',
				'hubspot_response' => NULL,
			];
		}

		if (empty($token)) {
			return [
				'success' => FALSE,
				'message' => 'No hay token configurado para HubSpot.',
				'hubspot_response' => NULL,
			];
		}

		if (empty($data['email'])) {
			throw new \InvalidArgumentException('El campo email es obligatorio para crear el contacto en HubSpot.');
		}

		$payload = [
			'properties' => [
				'email' => (string) $data['email'],
			],
		];

		if (!empty($data['firstname'])) {
			$payload['properties']['firstname'] = (string) $data['firstname'];
		}

		if (!empty($data['lastname'])) {
			$payload['properties']['lastname'] = (string) $data['lastname'];
		}

		if (!empty($data['phone'])) {
			$payload['properties']['phone'] = (string) $data['phone'];
		}

		$endpoint = $apiUrl . '/crm/v3/objects/contacts';

		try {
			$response = $this->httpClient->request('POST', $endpoint, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
				'json' => $payload,
				'timeout' => 30,
				'connect_timeout' => 10,
				'http_errors' => FALSE,
			]);

			$statusCode = $response->getStatusCode();
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, TRUE);

			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logger->error(
					'Error HTTP creando contacto en HubSpot. Status: @status. Response: @response. Payload: @payload',
					[
						'@status' => $statusCode,
						'@response' => $body,
						'@payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
					]
				);

				return [
					'success' => FALSE,
					'message' => 'HubSpot respondió con error HTTP al crear el contacto.',
					'hubspot_response' => is_array($decodedBody) ? $decodedBody : NULL,
				];
			}

			$this->logger->notice(
				'Contacto creado correctamente en HubSpot para el email: @email',
				[
					'@email' => (string) $data['email'],
				]
			);

			return [
				'success' => TRUE,
				'message' => 'Contacto creado correctamente en HubSpot.',
				'hubspot_response' => is_array($decodedBody) ? $decodedBody : NULL,
			];
		} catch (GuzzleException $e) {
			$this->logger->error(
				'Excepción Guzzle creando contacto en HubSpot para @email. Error: @error',
				[
					'@email' => (string) $data['email'],
					'@error' => $e->getMessage(),
				]
			);

			return [
				'success' => FALSE,
				'message' => 'Error de comunicación con HubSpot.',
				'hubspot_response' => NULL,
			];
		} catch (\Throwable $e) {
			$this->logger->error(
				'Excepción general creando contacto en HubSpot para @email. Error: @error',
				[
					'@email' => (string) $data['email'],
					'@error' => $e->getMessage(),
				]
			);

			return [
				'success' => FALSE,
				'message' => 'Error general al crear el contacto en HubSpot.',
				'hubspot_response' => NULL,
			];
		}
	}
}
