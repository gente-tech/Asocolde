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
			'properties' => [],
		];

		foreach ($data as $property_name => $property_value) {
			if ($property_value === NULL || $property_value === '') {
				continue;
			}

			$payload['properties'][$property_name] = (string) $property_value;
		}

		$endpoint = $apiUrl . '/crm/v3/objects/contacts';

		try {
			$this->logger->info('HubSpot payload enviado: <pre>@payload</pre>', [
				'@payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			]);
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
			$this->logger->error('Payload con error HubSpot: <pre>@payload</pre>', [
				'@payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			]);
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

	public function getContactByEmail(string $email): ?array
	{
		try {

			$config = $this->configFactory->get('enterprise_integrations.hubspot_settings');

			$token = (string) $config->get('hubspot_token');

			$properties = [
				'email',
				'firstname',
				'lastname',
				'phone',
				'pais_de_nacionalidad',
				'unidad_de_negocio',
				'tipo_interaccion',

				// Interesados / preinscripción programa.
				'interesados_cursos',
				'interesados_webinar',
				'interesados_diplomados',
				'interesados_programas_especiales',

				// Prospectos / descarga de programa.
				'prospectos_cursos',
				'prospectos_webinar',
				'prospectos_diplomados',
				'prospectos_programas_especiales',
			];

			$queryParts = [
				'idProperty=' . urlencode('email'),
			];

			foreach ($properties as $property) {
				$queryParts[] = 'properties=' . urlencode($property);
			}

			$url = 'https://api.hubapi.com/crm/v3/objects/contacts/' . urlencode($email) . '?' . implode('&', $queryParts);

			$response = $this->httpClient->get($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type' => 'application/json',
				],
			]);

			$data = json_decode($response->getBody()->getContents(), TRUE);

			return is_array($data) ? $data : NULL;
		} catch (\Exception $e) {

			$this->logger->warning('No se encontró contacto HubSpot para el correo @email', [
				'@email' => $email,
			]);

			return NULL;
		}
	}

	public function mergeHubspotProgramProperty(
		string $currentValue,
		string $programaId,
		string $programaNombre
	): string {

		$newLine = $programaId . ' | ' . trim($programaNombre);

		// Si está vacío, retorna primera línea.
		if (trim($currentValue) === '') {
			return $newLine;
		}

		$lines = preg_split('/\r\n|\r|\n/', trim($currentValue));

		$updated = FALSE;

		foreach ($lines as &$line) {

			$line = trim($line);

			// Busca líneas que empiecen con:
			// 80 |
			if (preg_match('/^' . preg_quote($programaId, '/') . '\s*\|/', $line)) {

				// Reemplaza línea completa.
				$line = $newLine;

				$updated = TRUE;
			}
		}

		unset($line);

		// Si no existe el ID, agrega nueva línea.
		if (!$updated) {
			$lines[] = $newLine;
		}

		// Elimina vacíos y duplicados exactos.
		$lines = array_unique(array_filter(array_map('trim', $lines)));

		return implode("\n", $lines);
	}

	public function mergeHubspotMultiCheckboxValue(
		string $currentValue,
		string $newValue
	): string {

		$currentItems = [];

		if (trim($currentValue) !== '') {
			$currentItems = explode(';', $currentValue);
		}

		$currentItems = array_map('trim', $currentItems);
		$currentItems = array_filter($currentItems);

		$currentItems[] = trim($newValue);

		$currentItems = array_unique($currentItems);

		return implode(';', $currentItems);
	}

	public function updateContactByEmail(
		string $email,
		array $properties
	): ?array {

		try {

			$config = $this->configFactory->get('enterprise_integrations.hubspot_settings');

			$token = (string) $config->get('hubspot_token');

			$url = 'https://api.hubapi.com/crm/v3/objects/contacts/' . urlencode($email) . '?idProperty=email';

			$payload = [
				'properties' => [],
			];

			foreach ($properties as $propertyName => $propertyValue) {

				if ($propertyValue === NULL) {
					continue;
				}

				$payload['properties'][$propertyName] = (string) $propertyValue;
			}

			$this->logger->info('HubSpot update payload: <pre>@payload</pre>', [
				'@payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			]);

			$response = $this->httpClient->patch($url, [
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

			$data = json_decode($body, TRUE);

			if ($statusCode < 200 || $statusCode >= 300) {

				$this->logger->error(
					'Error actualizando contacto HubSpot. Status: @status. Response: @response',
					[
						'@status' => $statusCode,
						'@response' => $body,
					]
				);

				return NULL;
			}

			return is_array($data) ? $data : NULL;
		} catch (\Exception $e) {

			$this->logger->error('Error actualizando contacto HubSpot: @message', [
				'@message' => $e->getMessage(),
			]);

			return NULL;
		}
	}

	public function createOrUpdateContactWithInterest(array $data): ?array
	{
		try {

			if (empty($data['email'])) {
				throw new \Exception('El email es obligatorio.');
			}

			if (empty($data['interest_property'])) {
				throw new \Exception('interest_property es obligatorio.');
			}

			if (empty($data['programa_id'])) {
				throw new \Exception('programa_id es obligatorio.');
			}

			if (empty($data['programa_nombre'])) {
				throw new \Exception('programa_nombre es obligatorio.');
			}

			$email = trim((string) $data['email']);
			$interestProperty = trim((string) $data['interest_property']);
			$programaId = trim((string) $data['programa_id']);
			$programaNombre = trim((string) $data['programa_nombre']);

			// Buscar contacto actual.
			$contact = $this->getContactByEmail($email);

			// Si no existe, crearlo primero.
			if (!$contact) {

				$createPayload = [
					'email' => $email,
				];

				if (!empty($data['firstname'])) {
					$createPayload['firstname'] = $data['firstname'];
				}

				if (!empty($data['lastname'])) {
					$createPayload['lastname'] = $data['lastname'];
				}

				if (!empty($data['phone'])) {
					$createPayload['phone'] = $data['phone'];
				}

				if (!empty($data['pais_de_nacionalidad'])) {
					$createPayload['pais_de_nacionalidad'] = $data['pais_de_nacionalidad'];
				}

				if (!empty($data['unidad_de_negocio'])) {
					$createPayload['unidad_de_negocio'] = $data['unidad_de_negocio'];
				}

				if (!empty($data['tipo_interaccion'])) {
					$createPayload['tipo_interaccion'] = $data['tipo_interaccion'];
				}

				$this->createContact($createPayload);

				// Volver a consultar con propiedades custom.
				$contact = $this->getContactByEmail($email);
			}

			if (!$contact) {
				throw new \Exception('No fue posible obtener el contacto HubSpot.');
			}

			$currentValue = '';

			if (
				!empty($contact['properties']) &&
				isset($contact['properties'][$interestProperty])
			) {
				$currentValue = (string) $contact['properties'][$interestProperty];
			}

			$mergedValue = $this->mergeHubspotProgramProperty(
				$currentValue,
				$programaId,
				$programaNombre
			);

			$updateProperties = [
				$interestProperty => $mergedValue,
			];

			if (!empty($data['pais_de_nacionalidad'])) {

				$currentPaisNacionalidad = '';

				if (
					!empty($contact['properties']) &&
					isset($contact['properties']['pais_de_nacionalidad'])
				) {
					$currentPaisNacionalidad = trim((string) $contact['properties']['pais_de_nacionalidad']);
				}

				if ($currentPaisNacionalidad === '') {
					$updateProperties['pais_de_nacionalidad'] = $data['pais_de_nacionalidad'];
				}
			}

			if (!empty($data['unidad_de_negocio'])) {
				$updateProperties['unidad_de_negocio'] = $data['unidad_de_negocio'];
			}

			if (!empty($data['tipo_interaccion'])) {

				$currentTipoInteraccion = '';

				if (
					!empty($contact['properties']) &&
					isset($contact['properties']['tipo_interaccion'])
				) {
					$currentTipoInteraccion = (string) $contact['properties']['tipo_interaccion'];
				}

				$updateProperties['tipo_interaccion'] = $this->mergeHubspotMultiCheckboxValue(
					$currentTipoInteraccion,
					(string) $data['tipo_interaccion']
				);
			}

			return $this->updateContactByEmail($email, $updateProperties);
		} catch (\Exception $e) {

			$this->logger->error('Error createOrUpdateContactWithInterest: @message', [
				'@message' => $e->getMessage(),
			]);

			return NULL;
		}
	}
}
