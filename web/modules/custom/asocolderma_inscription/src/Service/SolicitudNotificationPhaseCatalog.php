<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

/**
 * Fuente única de verdad de las fases notificables de solicitudes de ingreso.
 *
 * Define:
 * - Las fases disponibles para configurar notificaciones.
 * - La relación entre estados funcionales y fases notificables.
 *
 * Mandrill y Twilio no pertenecen a este catálogo. Son canales de entrega
 * configurables independientemente para cada fase.
 */
final class SolicitudNotificationPhaseCatalog
{

	/**
	 * Fases notificables vigentes.
	 */
	private const PHASES = [
		'activacion_cuenta' => [
			'label' => 'Activación de cuenta',
			'description' => 'Se ejecuta cuando el aspirante se registra y debe activar su cuenta antes de iniciar sesión.',
			'context_type' => 'account_activation',
		],
		'solicitud_creada' => [
			'label' => 'Solicitud creada / En trámite',
			'description' => 'Se ejecuta cuando el aspirante crea una solicitud de ingreso.',
			'context_type' => 'status_change',
		],
		'pendiente_aclaracion' => [
			'label' => 'Pendiente aclaración',
			'description' => 'Se ejecuta cuando Secretaría General solicita aclaraciones al aspirante.',
			'context_type' => 'clarification',
		],
		'ajustes_realizados' => [
			'label' => 'Ajustes realizados',
			'description' => 'Se ejecuta cuando el aspirante realiza las correcciones solicitadas y devuelve la solicitud a Secretaría General.',
			'context_type' => 'status_change',
		],
		'aprobada_secretaria' => [
			'label' => 'Aprobada por Secretaría General',
			'description' => 'Se ejecuta cuando Secretaría General aprueba la solicitud.',
			'context_type' => 'status_change',
		],
		'rechazada_secretaria' => [
			'label' => 'Rechazada por Secretaría General',
			'description' => 'Se ejecuta cuando Secretaría General rechaza la solicitud.',
			'context_type' => 'rejection',
		],
		'aprobada_junta_directiva' => [
			'label' => 'Aprobada por Junta Directiva',
			'description' => 'Se ejecuta cuando la Junta Directiva aprueba la solicitud.',
			'context_type' => 'status_change',
		],
		'rechazada_junta_directiva' => [
			'label' => 'Rechazada por Junta Directiva',
			'description' => 'Se ejecuta cuando la Junta Directiva rechaza la solicitud.',
			'context_type' => 'rejection',
		],
		'aprobada_asamblea_general' => [
			'label' => 'Aprobada por Asamblea General',
			'description' => 'Se ejecuta cuando la Asamblea General aprueba la solicitud.',
			'context_type' => 'status_change',
		],
		'rechazada_asamblea_general' => [
			'label' => 'Rechazada por Asamblea General',
			'description' => 'Se ejecuta cuando la Asamblea General rechaza la solicitud.',
			'context_type' => 'rejection',
		],
		'documentos_enviados' => [
			'label' => 'Documentos enviados',
			'description' => 'Se ejecuta cuando los documentos quedan disponibles para el proceso de firma.',
			'context_type' => 'status_change',
		],
		'pendiente_pago_ingreso' => [
			'label' => 'Pendiente pago de ingreso',
			'description' => 'Se ejecuta cuando se envían al aspirante las instrucciones para realizar el pago de ingreso.',
			'context_type' => 'payment',
		],
		'miembro_activo' => [
			'label' => 'Miembro activo',
			'description' => 'Se ejecuta cuando el aspirante completa el proceso y es convertido en miembro activo.',
			'context_type' => 'status_change',
		],
	];

	/**
	 * Relación entre estado funcional destino y fase notificable.
	 */
	private const STATE_TO_PHASE = [
		'sg_pendiente_aclaracion' => 'pendiente_aclaracion',
		'sg_aprobado' => 'aprobada_secretaria',
		'sg_rechazado' => 'rechazada_secretaria',

		'junta_aprobado' => 'aprobada_junta_directiva',
		'junta_rechazado' => 'rechazada_junta_directiva',

		'asamblea_aprobado' => 'aprobada_asamblea_general',
		'asamblea_rechazado' => 'rechazada_asamblea_general',

		'coord_documentos_enviados' => 'documentos_enviados',
		'coord_pago_ingreso' => 'pendiente_pago_ingreso',
		'coord_miembro_activo' => 'miembro_activo',
	];

	/**
	 * Obtiene todas las fases notificables vigentes.
	 */
	public function all(): array
	{
		return self::PHASES;
	}

	/**
	 * Comprueba si una fase existe en el catálogo vigente.
	 */
	public function has(string $phaseKey): bool
	{
		return isset(self::PHASES[trim($phaseKey)]);
	}

	/**
	 * Resuelve la fase notificable de una transición de estado.
	 */
	public function resolveForTransition(
		string $toFunctionalKey,
		string $origin = '',
	): ?string {
		$toFunctionalKey = trim($toFunctionalKey);
		$origin = trim($origin);

		// El retorno a Secretaría después de corregir una aclaración es un
		// evento diferente a la creación inicial de la solicitud.
		if (
			$toFunctionalKey === 'sg_en_tramite'
			&& $origin === 'aspirante_ajustes_realizados'
		) {
			return 'ajustes_realizados';
		}

		return self::STATE_TO_PHASE[$toFunctionalKey] ?? NULL;
	}

	/**
	 * Obtiene el tipo de contexto requerido por una fase notificable.
	 */
	public function getContextType(string $phaseKey): ?string
	{
		$phaseKey = trim($phaseKey);

		return self::PHASES[$phaseKey]['context_type'] ?? NULL;
	}
}
