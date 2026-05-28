<?php

namespace Drupal\asocolderma_data_core\Service;

use Drupal\enterprise_integrations\Service\HubspotService;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes Data Core created records with HubSpot contacts.
 */
final class DataCoreHubspotSyncService
{

	/**
	 * HubSpot integration service.
	 */
	protected HubspotService $hubspotService;

	/**
	 * Logger.
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 */
	public function __construct(
		HubspotService $hubspot_service,
		LoggerInterface $logger,
	) {
		$this->hubspotService = $hubspot_service;
		$this->logger = $logger;
	}

	/**
	 * Sends a newly created Data Core record to HubSpot.
	 *
	 * This method is intentionally non-blocking:
	 * if HubSpot fails, the local creation/import must not fail.
	 */
	public function syncCreatedRecord(string $table_name, array $record): void
	{
		if (!$this->isSupportedTable($table_name)) {
			return;
		}

		$payload = $this->buildHubspotPayload($table_name, $record);

		if (empty($payload['email'])) {
			$this->logger->notice('No se envió registro Data Core a HubSpot porque no tiene correo. Tabla: @table', [
				'@table' => $table_name,
			]);
			return;
		}

		try {
			$email = trim((string) $payload['email']);

			$existing_contact = $this->hubspotService->getContactByEmail($email);

			if ($existing_contact) {
				$update_properties = [];

				foreach (['firstname', 'lastname', 'phone'] as $property) {
					if (!empty($payload[$property])) {
						$update_properties[$property] = $payload[$property];
					}
				}

				if (!empty($update_properties)) {
					$this->hubspotService->updateContactByEmail($email, $update_properties);
				}

				$this->logger->notice('Contacto HubSpot actualizado desde Data Core. Tabla: @table. Email: @email', [
					'@table' => $table_name,
					'@email' => $email,
				]);

				return;
			}

			$this->hubspotService->createContact($payload);

			$this->logger->notice('Contacto HubSpot creado desde Data Core. Tabla: @table. Email: @email', [
				'@table' => $table_name,
				'@email' => $email,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Error sincronizando registro Data Core con HubSpot. Tabla: @table. Error: @error', [
				'@table' => $table_name,
				'@error' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Checks if the table must be synchronized with HubSpot.
	 */
	protected function isSupportedTable(string $table_name): bool
	{
		return in_array($table_name, [
			'asocolderma_import_asociados',
			'asocolderma_import_residentes',
			'asocolderma_import_patrocinadores',
		], TRUE);
	}

	/**
	 * Builds the HubSpot contact payload depending on the source table.
	 */
	protected function buildHubspotPayload(string $table_name, array $record): array
	{
		switch ($table_name) {
			case 'asocolderma_import_asociados':
			case 'asocolderma_import_residentes':
				return $this->buildPersonPayload($record);

			case 'asocolderma_import_patrocinadores':
				return $this->buildSponsorPayload($record);
		}

		return [];
	}

	/**
	 * Builds payload for Asociados and Residentes.
	 */
	protected function buildPersonPayload(array $record): array
	{
		$firstname = trim(implode(' ', array_filter([
			$record['primer_nombre'] ?? '',
			$record['segundo_nombre'] ?? '',
		])));

		$lastname = trim(implode(' ', array_filter([
			$record['primer_apellido'] ?? '',
			$record['segundo_apellido'] ?? '',
		])));

		return $this->cleanPayload([
			'email' => $record['correo_principal'] ?? '',
			'firstname' => $firstname,
			'lastname' => $lastname,
			'phone' => $record['telefono_celular'] ?? '',
		]);
	}

	/**
	 * Builds payload for Patrocinadores.
	 */
	protected function buildSponsorPayload(array $record): array
	{
		$full_name = trim((string) ($record['nombre_contacto_principal'] ?? ''));
		[$firstname, $lastname] = $this->splitFullName($full_name);

		$phone = $record['celular_contacto'] ?? '';

		if (trim((string) $phone) === '') {
			$phone = $record['telefono_corporativo'] ?? '';
		}

		return $this->cleanPayload([
			'email' => $record['correo_corporativo'] ?? '',
			'firstname' => $firstname,
			'lastname' => $lastname,
			'phone' => $phone,
		]);
	}

	/**
	 * Splits a full name into firstname and lastname for HubSpot.
	 */
	protected function splitFullName(string $full_name): array
	{
		$full_name = trim(preg_replace('/\s+/', ' ', $full_name));

		if ($full_name === '') {
			return ['', ''];
		}

		$parts = explode(' ', $full_name);

		if (count($parts) === 1) {
			return [$parts[0], ''];
		}

		$firstname = array_shift($parts);
		$lastname = implode(' ', $parts);

		return [$firstname, $lastname];
	}

	/**
	 * Removes empty/null values from HubSpot payload.
	 */
	protected function cleanPayload(array $payload): array
	{
		$clean = [];

		foreach ($payload as $key => $value) {
			$value = trim((string) $value);

			if ($value === '') {
				continue;
			}

			$clean[$key] = $value;
		}

		return $clean;
	}
}
