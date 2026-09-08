<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\asocolderma_inscription\Exception\SolicitudSignatureException;
use Drupal\enterprise_integrations\Service\ZohoSignService;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Orquesta el proceso de preparación de firma de una solicitud.
 *
 * La creación del documento ocurre exclusivamente cuando el aspirante
 * solicita iniciar la firma.
 */
final class SolicitudSignatureManager
{

	private const SIGNABLE_STATE = 'coord_documentos_enviados';

	/**
	 * Estados locales que representan una firma terminada.
	 */
	private const COMPLETED_STATUSES = [
		'completed',
		'signed',
	];

	/**
	 * Estados que indican que un request anterior ya no debe reutilizarse.
	 */
	private const NON_REUSABLE_STATUSES = [
		'declined',
		'recalled',
		'expired',
		'cancelled',
		'canceled',
		'revoked',
		'superseded',
		'failed',
	];

	public function __construct(
		private readonly SolicitudSignaturePayloadBuilder $payloadBuilder,
		private readonly SolicitudZohoVariableManager $variableManager,
		private readonly ZohoSignService $zohoSignService,
		private readonly LoggerInterface $logger,
	) {}

	/**
	 * Prepara la firma y retorna una URL fresca de Zoho Sign.
	 *
	 * Si no existe un request reutilizable, el documento se crea en este
	 * momento. Nunca durante una transición de estado.
	 *
	 * @throws \Drupal\asocolderma_inscription\Exception\SolicitudSignatureException
	 */
	public function prepareSignUrl(NodeInterface $node): string
	{
		try {
			$this->assertSolicitudCanSign($node);

			$mapping = $this->zohoSignService
				->getLatestRequestMappingBySolicitud((int) $node->id());

			if ($this->isCompleted($mapping)) {
				throw new SolicitudSignatureException(
					'FIRMA_SOLICITUD_YA_COMPLETADA',
					'La solicitud ya tiene un documento firmado.',
					[
						'zoho_request_id' => (string) ($mapping['zoho_request_id'] ?? ''),
						'status' => (string) ($mapping['status'] ?? ''),
					],
				);
			}

			/**
			 * Siempre hacemos el preflight contra la plantilla activa.
			 *
			 * Esto permite comprobar que un request pendiente fue creado con el mismo
			 * contrato de plantilla que está vigente en este momento.
			 */
			$payload = $this->payloadBuilder->build($node);

			/**
			 * Si existe un request reutilizable y fue creado con exactamente la misma
			 * plantilla y esquema, únicamente generamos una URL embebida fresca.
			 */
			if (
				$this->isReusable($mapping)
				&& $this->isTemplateCompatible($mapping, $payload)
			) {
				return $this->generateFreshSignUrl(
					(string) $mapping['zoho_request_id'],
					(string) $mapping['zoho_action_id'],
				);
			}

			/**
			 * Si el request todavía parece reutilizable por estado, pero fue creado con
			 * otra versión de la plantilla o es un mapping legado sin trazabilidad,
			 * se conserva para auditoría y se marca como reemplazado.
			 */
			if (
				$this->isReusable($mapping)
				&& !$this->isTemplateCompatible($mapping, $payload)
			) {
				$mapping_id = (int) ($mapping['id'] ?? 0);

				if ($mapping_id <= 0) {
					throw new SolicitudSignatureException(
						'FIRMA_SOLICITUD_INVALIDA',
						'El mapping anterior no contiene un identificador local válido.',
						[
							'zoho_request_id' => (string) ($mapping['zoho_request_id'] ?? ''),
						],
					);
				}

				$old_template_id = trim(
					(string) ($mapping['template_id'] ?? ''),
				);

				$old_schema_hash = trim(
					(string) ($mapping['template_schema_hash'] ?? ''),
				);

				$new_template_id = trim(
					(string) ($payload['template_id'] ?? ''),
				);

				$new_schema_hash = trim(
					(string) ($payload['template_schema_hash'] ?? ''),
				);

				try {
					$this->zohoSignService->markRequestMappingSuperseded(
						$mapping_id,
						sprintf(
							'Request reemplazado automáticamente por cambio de plantilla o esquema. Template anterior: %s. Hash anterior: %s. Template actual: %s. Hash actual: %s.',
							$old_template_id !== '' ? $old_template_id : 'NO_REGISTRADO',
							$old_schema_hash !== '' ? $old_schema_hash : 'NO_REGISTRADO',
							$new_template_id,
							$new_schema_hash,
						),
					);
				} catch (\Throwable $e) {
					throw new SolicitudSignatureException(
						'FIRMA_SOLICITUD_INVALIDA',
						'No fue posible reemplazar de forma segura el request anterior.',
						[
							'mapping_id' => $mapping_id,
							'zoho_request_id' => (string) ($mapping['zoho_request_id'] ?? ''),
							'error_original' => $e->getMessage(),
						],
						$e,
					);
				}
			}

			/**
			 * La identidad del firmante no depende de que esos campos estén
			 * presentes en la plantilla. Se obtiene del catálogo completo Drupal.
			 */
			$variables = $this->variableManager->resolveAll($node);

			$recipient_name = trim(
				(string) (
					$variables['aspirante_nombre_completo']
					?? $variables['nombre_completo']
					?? ''
				)
			);

			$recipient_email = trim(
				(string) (
					$variables['aspirante_correo_cuenta']
					?? $variables['correo']
					?? ''
				)
			);

			if ($recipient_name === '' || $recipient_email === '') {
				throw new SolicitudSignatureException(
					'FIRMA_DATOS_FIRMANTE_INCOMPLETOS',
					'No fue posible resolver la identidad completa del firmante.',
					[
						'recipient_name_available' => $recipient_name !== '',
						'recipient_email_available' => $recipient_email !== '',
						'template_id' => $payload['template_id'] ?? '',
					],
				);
			}

			try {
				$created = $this->zohoSignService->createSignatureRequest([
					'solicitud_nid' => (int) $node->id(),
					'template_id' => (string) $payload['template_id'],
					'template_schema_hash' => (string) $payload['template_schema_hash'],
					'action_id' => (string) $payload['action_id'],
					'recipient_name' => $recipient_name,
					'recipient_email' => $recipient_email,
					'field_text_data' => $payload['field_text_data'],
					'notes' => 'Solicitud de ingreso Asocolderma #'
						. $this->getSolicitudCode($node),
				]);
			} catch (\Throwable $e) {
				throw new SolicitudSignatureException(
					'FIRMA_ERROR_CREACION_DOCUMENTO_ZOHO',
					'Zoho Sign no pudo crear el documento de firma.',
					[
						'template_id' => $payload['template_id'] ?? '',
						'action_id' => $payload['action_id'] ?? '',
						'error_original' => $e->getMessage(),
					],
					$e,
				);
			}

			$request_id = trim((string) ($created['request_id'] ?? ''));
			$action_id = trim((string) ($created['action_id'] ?? ''));

			if ($request_id === '' || $action_id === '') {
				throw new SolicitudSignatureException(
					'FIRMA_SOLICITUD_INVALIDA',
					'Zoho Sign creó una respuesta sin request_id o action_id válido.',
					[
						'template_id' => $payload['template_id'] ?? '',
						'request_id' => $request_id,
						'action_id' => $action_id,
					],
				);
			}

			return $this->generateFreshSignUrl(
				$request_id,
				$action_id,
			);
		} catch (SolicitudSignatureException $e) {
			$this->logFailure($node, $e);
			throw $e;
		} catch (\Throwable $e) {
			$wrapped = new SolicitudSignatureException(
				'FIRMA_SOLICITUD_INVALIDA',
				'Se produjo un error no controlado preparando la firma.',
				[
					'error_original' => $e->getMessage(),
					'exception' => get_class($e),
				],
				$e,
			);

			$this->logFailure($node, $wrapped);

			throw $wrapped;
		}
	}

	/**
	 * Genera una URL de firma fresca para un request existente.
	 */
	private function generateFreshSignUrl(
		string $request_id,
		string $action_id,
	): string {
		try {
			$response = $this->zohoSignService->generateFreshSignUrl(
				$request_id,
				$action_id,
			);
		} catch (\Throwable $e) {
			throw new SolicitudSignatureException(
				'FIRMA_ERROR_URL_ZOHO',
				'No fue posible generar la URL de firma de Zoho Sign.',
				[
					'zoho_request_id' => $request_id,
					'zoho_action_id' => $action_id,
					'error_original' => $e->getMessage(),
				],
				$e,
			);
		}

		$sign_url = trim((string) ($response['sign_url'] ?? ''));

		if ($sign_url === '') {
			throw new SolicitudSignatureException(
				'FIRMA_ERROR_URL_ZOHO',
				'Zoho Sign respondió sin una URL de firma válida.',
				[
					'zoho_request_id' => $request_id,
					'zoho_action_id' => $action_id,
				],
			);
		}

		return $sign_url;
	}

	/**
	 * Valida las reglas mínimas para iniciar una firma.
	 */
	private function assertSolicitudCanSign(NodeInterface $node): void
	{
		if ($node->bundle() !== 'solicitud_ingreso') {
			throw new SolicitudSignatureException(
				'FIRMA_SOLICITUD_INVALIDA',
				'La entidad recibida no corresponde a una solicitud de ingreso.',
			);
		}

		if (
			!$node->hasField('field_state')
			|| $node->get('field_state')->isEmpty()
		) {
			throw new SolicitudSignatureException(
				'FIRMA_SOLICITUD_NO_HABILITADA',
				'La solicitud no tiene un estado válido para firma.',
			);
		}

		$term = $node->get('field_state')->entity;

		$functional_key = $term
			? \asocolderma_inscription_get_state_functional_key_from_term($term)
			: '';

		if ($functional_key !== self::SIGNABLE_STATE) {
			throw new SolicitudSignatureException(
				'FIRMA_SOLICITUD_NO_HABILITADA',
				'La solicitud no se encuentra en el estado habilitado para firma.',
				[
					'functional_state' => $functional_key,
				],
			);
		}
	}

	/**
	 * Determina si el último mapping representa una firma completada.
	 */
	private function isCompleted(?array $mapping): bool
	{
		if (!$mapping) {
			return FALSE;
		}

		$status = strtolower(
			trim((string) ($mapping['status'] ?? '')),
		);

		return in_array(
			$status,
			self::COMPLETED_STATUSES,
			TRUE,
		);
	}

	/**
	 * Comprueba que el request existente pertenezca exactamente al contrato
	 * de plantilla actualmente validado.
	 *
	 * Los mappings antiguos sin template_id o schema hash se consideran
	 * incompatibles y deben reemplazarse.
	 */
	private function isTemplateCompatible(
		?array $mapping,
		array $payload,
	): bool {
		if (!$mapping) {
			return FALSE;
		}

		$mapping_template_id = trim(
			(string) ($mapping['template_id'] ?? ''),
		);

		$mapping_schema_hash = trim(
			(string) ($mapping['template_schema_hash'] ?? ''),
		);

		$current_template_id = trim(
			(string) ($payload['template_id'] ?? ''),
		);

		$current_schema_hash = trim(
			(string) ($payload['template_schema_hash'] ?? ''),
		);

		if (
			$mapping_template_id === ''
			|| $mapping_schema_hash === ''
			|| $current_template_id === ''
			|| $current_schema_hash === ''
		) {
			return FALSE;
		}

		return hash_equals($mapping_template_id, $current_template_id)
			&& hash_equals($mapping_schema_hash, $current_schema_hash);
	}

	/**
	 * Determina si puede reutilizarse el request existente.
	 */
	private function isReusable(?array $mapping): bool
	{
		if (!$mapping) {
			return FALSE;
		}

		$request_id = trim(
			(string) ($mapping['zoho_request_id'] ?? ''),
		);

		$action_id = trim(
			(string) ($mapping['zoho_action_id'] ?? ''),
		);

		if ($request_id === '' || $action_id === '') {
			return FALSE;
		}

		$status = strtolower(
			trim((string) ($mapping['status'] ?? '')),
		);

		if (
			in_array(
				$status,
				self::NON_REUSABLE_STATUSES,
				TRUE,
			)
		) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Código público de la solicitud para trazabilidad.
	 */
	private function getSolicitudCode(NodeInterface $node): string
	{
		if (
			$node->hasField('field_solicitud_id')
			&& !$node->get('field_solicitud_id')->isEmpty()
		) {
			return trim(
				(string) $node->get('field_solicitud_id')->value,
			);
		}

		return 'NID-' . $node->id();
	}

	/**
	 * Registra el detalle técnico sin exponerlo al aspirante.
	 */
	private function logFailure(
		NodeInterface $node,
		SolicitudSignatureException $exception,
	): void {
		$this->logger->error(
			'[@technical_code] Error de firma | Solicitud NID: @nid | Código: @solicitud_code | Detalle: @detail | Contexto: @context',
			[
				'@technical_code' => $exception->getTechnicalCode(),
				'@nid' => (int) $node->id(),
				'@solicitud_code' => $this->getSolicitudCode($node),
				'@detail' => $exception->getMessage(),
				'@context' => json_encode(
					$exception->getContext(),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
				),
			],
		);
	}
}
