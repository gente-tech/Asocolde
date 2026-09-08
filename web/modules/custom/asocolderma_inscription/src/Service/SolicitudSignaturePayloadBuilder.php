<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\asocolderma_inscription\Exception\SolicitudSignatureException;
use Drupal\enterprise_integrations\Service\ZohoSignTemplateSchemaProvider;
use Drupal\node\NodeInterface;

/**
 * Valida y construye el payload de precarga para la firma de una solicitud.
 *
 * Esta clase no crea documentos en Zoho Sign.
 * Únicamente cruza:
 *
 * - variables disponibles en Drupal;
 * - campos declarados por la plantilla Zoho;
 *
 * y retorna el payload exacto requerido por la plantilla.
 */
final class SolicitudSignaturePayloadBuilder
{

	public function __construct(
		private readonly SolicitudZohoVariableManager $variableManager,
		private readonly ZohoSignTemplateSchemaProvider $templateSchemaProvider,
	) {}

	/**
	 * Construye el payload necesario para la plantilla activa.
	 *
	 * @return array
	 *   Contiene esquema, datos resueltos y field_text_data final.
	 *
	 * @throws \Drupal\asocolderma_inscription\Exception\SolicitudSignatureException
	 */
	public function build(NodeInterface $node): array
	{
		try {
			$schema = $this->templateSchemaProvider->getSchema();
		} catch (\Throwable $e) {
			throw new SolicitudSignatureException(
				'FIRMA_PLANTILLA_INVALIDA',
				'No fue posible obtener o interpretar la plantilla de firma.',
				[],
				$e,
			);
		}

		$definitions = $this->variableManager->getDefinitions();
		$values = $this->variableManager->resolveAll($node);

		$prefill_fields = $schema['prefill_fields'] ?? [];

		if (!is_array($prefill_fields) || $prefill_fields === []) {
			throw new SolicitudSignatureException(
				'FIRMA_PLANTILLA_INVALIDA',
				'La plantilla no contiene campos de precarga válidos.',
				[
					'template_id' => $schema['template_id'] ?? '',
				],
			);
		}

		$unknown_fields = [];
		$required_missing = [];
		$field_text_data = [];

		foreach ($prefill_fields as $variable_key => $field_definition) {
			if (!array_key_exists($variable_key, $definitions)) {
				$unknown_fields[] = $variable_key;
				continue;
			}

			$value = isset($values[$variable_key])
				? trim((string) $values[$variable_key])
				: '';

			$required = !empty($field_definition['required']);

			if ($required && $value === '') {
				$required_missing[] = $variable_key;
				continue;
			}

			/*
       * Solo enviamos variables que la plantilla realmente solicita.
       *
       * Los opcionales vacíos también se incluyen como cadena vacía para
       * mantener un comportamiento determinista del contrato de datos.
       */
			$field_text_data[$variable_key] = $value;
		}

		/*
     * Una variable desconocida es un error de configuración incluso si en
     * Zoho está marcada como opcional. No permitimos plantillas parcialmente
     * incompatibles con el catálogo Drupal.
     */
		if ($unknown_fields !== []) {
			sort($unknown_fields);

			throw new SolicitudSignatureException(
				'FIRMA_CAMPO_DESCONOCIDO',
				'La plantilla contiene variables que Drupal no puede resolver.',
				[
					'template_id' => $schema['template_id'] ?? '',
					'fields' => $unknown_fields,
				],
			);
		}

		if ($required_missing !== []) {
			sort($required_missing);

			throw new SolicitudSignatureException(
				'FIRMA_VALOR_OBLIGATORIO_FALTANTE',
				'La solicitud no contiene valor para uno o más campos obligatorios de la plantilla.',
				[
					'template_id' => $schema['template_id'] ?? '',
					'fields' => $required_missing,
				],
			);
		}

		return [
			'template_id' => (string) ($schema['template_id'] ?? ''),
			'template_name' => (string) ($schema['template_name'] ?? ''),
			'template_schema_hash' => (string) ($schema['schema_hash'] ?? ''),
			'action_id' => (string) ($schema['action_id'] ?? ''),
			'role' => (string) ($schema['role'] ?? ''),
			'field_text_data' => $field_text_data,
			'requested_fields' => array_keys($prefill_fields),
		];
	}
}
