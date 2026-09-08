<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Service;

/**
 * Obtiene y normaliza el esquema de la plantilla activa de Zoho Sign.
 *
 * Esta clase conoce únicamente la estructura de la API de Zoho Sign.
 * No contiene reglas de negocio de solicitudes ni conocimiento de campos
 * específicos de Drupal.
 */
final class ZohoSignTemplateSchemaProvider
{

	public function __construct(
		private readonly ZohoSignService $zohoSignService,
	) {}

	/**
	 * Retorna el esquema normalizado de la plantilla configurada.
	 */
	public function getSchema(): array
	{
		$response = $this->zohoSignService->getTemplateDetails();

		$template = $response['templates'] ?? NULL;

		if (!is_array($template)) {
			throw new \RuntimeException(
				'Zoho Sign no retornó una estructura válida para la plantilla.',
			);
		}

		$template_id = trim((string) ($template['template_id'] ?? ''));
		$template_name = trim((string) ($template['template_name'] ?? ''));

		if ($template_id === '') {
			throw new \RuntimeException(
				'La respuesta de Zoho Sign no contiene template_id.',
			);
		}

		$sign_action = $this->resolveSignAction($template);

		return [
			'template_id' => $template_id,
			'template_name' => $template_name,
			'action_id' => $sign_action['action_id'],
			'role' => $sign_action['role'],
			'prefill_fields' => $this->resolvePrefillFields($template),
			'signer_fields' => $this->resolveSignerFields($sign_action),
		];
	}

	/**
	 * Localiza la acción de firma dentro de la plantilla.
	 */
	private function resolveSignAction(array $template): array
	{
		$actions = $template['actions'] ?? [];

		if (!is_array($actions)) {
			throw new \RuntimeException(
				'La plantilla de Zoho Sign no contiene acciones válidas.',
			);
		}

		foreach ($actions as $action) {
			if (!is_array($action)) {
				continue;
			}

			$action_type = strtoupper(
				trim((string) ($action['action_type'] ?? '')),
			);

			if ($action_type !== 'SIGN') {
				continue;
			}

			$action_id = trim((string) ($action['action_id'] ?? ''));

			if ($action_id === '') {
				throw new \RuntimeException(
					'La acción de firma de Zoho Sign no contiene action_id.',
				);
			}

			return [
				'action_id' => $action_id,
				'role' => trim((string) ($action['role'] ?? '')),
				'fields' => is_array($action['fields'] ?? NULL)
					? $action['fields']
					: [],
			];
		}

		throw new \RuntimeException(
			'La plantilla configurada no contiene una acción de firma.',
		);
	}

	/**
	 * Obtiene los campos que Drupal debe precargar.
	 *
	 * La Data Label configurada en Zoho corresponde a "field_label".
	 */
	private function resolvePrefillFields(array $template): array
	{
		$result = [];
		$document_fields = $template['document_fields'] ?? [];

		if (!is_array($document_fields)) {
			return [];
		}

		foreach ($document_fields as $document) {
			if (!is_array($document)) {
				continue;
			}

			$fields = $document['fields'] ?? [];

			if (!is_array($fields)) {
				continue;
			}

			foreach ($fields as $field) {
				if (!is_array($field)) {
					continue;
				}

				$key = trim((string) ($field['field_label'] ?? ''));

				if ($key === '') {
					throw new \RuntimeException(
						'La plantilla contiene un campo de precarga sin Etiqueta de datos.',
					);
				}

				if (isset($result[$key])) {
					throw new \RuntimeException(
						sprintf(
							'La plantilla contiene una Etiqueta de datos duplicada: %s.',
							$key,
						),
					);
				}

				$result[$key] = $this->normalizeField($field);
			}
		}

		ksort($result);

		return $result;
	}

	/**
	 * Obtiene los campos que pertenecen al firmante.
	 *
	 * Estos campos no forman parte del mapeo Drupal -> field_text_data.
	 */
	private function resolveSignerFields(array $sign_action): array
	{
		$result = [];

		foreach ($sign_action['fields'] ?? [] as $field) {
			if (!is_array($field)) {
				continue;
			}

			$field_id = trim((string) ($field['field_id'] ?? ''));

			if ($field_id === '') {
				continue;
			}

			$result[$field_id] = $this->normalizeField($field);
		}

		return $result;
	}

	/**
	 * Normaliza la metadata de un campo Zoho.
	 */
	private function normalizeField(array $field): array
	{
		return [
			'field_id' => trim((string) ($field['field_id'] ?? '')),
			'label' => trim((string) ($field['field_label'] ?? '')),
			'name' => trim((string) ($field['field_name'] ?? '')),
			'category' => trim((string) ($field['field_category'] ?? '')),
			'type' => trim((string) ($field['field_type_name'] ?? '')),
			'required' => (bool) ($field['is_mandatory'] ?? FALSE),
		];
	}
}
