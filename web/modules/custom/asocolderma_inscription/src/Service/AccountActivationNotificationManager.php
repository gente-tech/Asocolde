<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\enterprise_integrations\Service\MandrillService;
use Drupal\enterprise_integrations\Service\TokenResolver;

/**
 * Gestiona la notificación de activación de cuenta del aspirante.
 *
 * La activación ocurre antes de que exista una solicitud de ingreso, por lo
 * cual no pertenece a SolicitudNotificationManager.
 */
final class AccountActivationNotificationManager
{

	private const PHASE_KEY = 'activacion_cuenta';

	public function __construct(
		private readonly InscriptionNotificationCatalog $notificationCatalog,
		private readonly ConfigFactoryInterface $configFactory,
		private readonly MandrillService $mandrillService,
		private readonly TokenResolver $tokenResolver,
		private readonly LoggerChannelInterface $logger,
	) {}

	/**
	 * Envía el correo de activación si el canal está configurado.
	 *
	 * @return array
	 *   Resultado normalizado con:
	 *   - success: si el procesamiento fue correcto.
	 *   - sent: si efectivamente se envió el correo.
	 *   - message: descripción del resultado.
	 */
	public function send(string $email, string $activationUrl): array
	{
		$email = trim($email);
		$activationUrl = trim($activationUrl);

		if ($email === '' || $activationUrl === '') {
			return [
				'success' => FALSE,
				'sent' => FALSE,
				'message' => 'Faltan datos requeridos para enviar la activación.',
			];
		}

		if (!$this->notificationCatalog->has(self::PHASE_KEY)) {
			$this->logger->error(
				'La fase @phase no existe en el catálogo de notificaciones.',
				[
					'@phase' => self::PHASE_KEY,
				],
			);

			return [
				'success' => FALSE,
				'sent' => FALSE,
				'message' => 'La fase de activación no pertenece al catálogo vigente.',
			];
		}

		if (
			!$this->notificationCatalog->supportsChannel(
				self::PHASE_KEY,
				'mandrill',
			)
		) {
			$this->logger->error(
				'La fase @phase no soporta el canal Mandrill.',
				[
					'@phase' => self::PHASE_KEY,
				],
			);

			return [
				'success' => FALSE,
				'sent' => FALSE,
				'message' => 'La activación de cuenta no admite correo.',
			];
		}

		$phaseConfig = $this->configFactory
			->get('asocolderma_inscription.notification_settings')
			->get('phases.' . self::PHASE_KEY);

		$phaseConfig = is_array($phaseConfig)
			? $phaseConfig
			: [];

		$mandrillKey = trim(
			(string) ($phaseConfig['mandrill_template_key'] ?? '')
		);

		/*
     * Regla de negocio:
     * si no existe una plantilla configurada, no se intenta ningún envío.
     */
		if ($mandrillKey === '') {
			return [
				'success' => TRUE,
				'sent' => FALSE,
				'message' => 'La activación de cuenta no tiene correo configurado.',
			];
		}

		try {
			$messageGroup = $this->mandrillService
				->getMessageGroupByKey($mandrillKey);

			if (!$messageGroup) {
				throw new \RuntimeException(
					sprintf(
						'No existe configuración Mandrill con key "%s".',
						$mandrillKey,
					)
				);
			}

			$templateSlug = trim(
				(string) ($messageGroup['mandrill_template_slug'] ?? '')
			);

			if ($templateSlug === '') {
				throw new \RuntimeException(
					sprintf(
						'La configuración Mandrill "%s" no tiene slug de plantilla.',
						$mandrillKey,
					)
				);
			}

			$subjectTokens = [
				'email' => $email,
				'correo' => $email,
				'tipo_usuario' => 'aspirante',
			];

			$subjectConfig = trim(
				(string) ($messageGroup['subject'] ?? '')
			);

			if ($subjectConfig === '') {
				$subjectConfig = 'Active su cuenta de aspirante Asocolderma';
			}

			$subject = $this->tokenResolver->replace(
				$subjectConfig,
				$subjectTokens,
			);

			$result = $this->mandrillService->sendTemplate(
				$templateSlug,
				[
					'subject' => $subject,
					'to_email' => $email,
					'to_name' => $email,
				],
				[
					[
						'name' => 'USER_EMAIL',
						'content' => $email,
					],
					[
						'name' => 'USER_ACTIVATION_URL',
						'content' => $activationUrl,
					],
				],
			);

			if (empty($result['success'])) {
				throw new \RuntimeException(
					'Mandrill no confirmó el envío del correo.'
				);
			}

			$response = $result['mandrill_response'] ?? [];

			if (
				isset($response[0]['status'])
				&& in_array(
					$response[0]['status'],
					['rejected', 'invalid'],
					TRUE,
				)
			) {
				throw new \RuntimeException(
					'Mandrill rechazó el correo. Estado: '
						. $response[0]['status']
				);
			}

			$this->logger->info(
				'Correo de activación enviado a @mail usando @key / @template.',
				[
					'@mail' => $email,
					'@key' => $mandrillKey,
					'@template' => $templateSlug,
				],
			);

			$this->sendInternalCopies(
				$messageGroup,
				$email,
			);

			return [
				'success' => TRUE,
				'sent' => TRUE,
				'message' => 'Correo de activación enviado correctamente.',
			];
		} catch (\Throwable $e) {
			$this->logger->error(
				'Error enviando correo de activación a @mail: @error',
				[
					'@mail' => $email,
					'@error' => $e->getMessage(),
				],
			);

			return [
				'success' => FALSE,
				'sent' => FALSE,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Envía las copias internas configuradas en el grupo Mandrill.
	 */
	private function sendInternalCopies(
		array $messageGroup,
		string $email,
	): void {
		if (
			empty($messageGroup['send_copy'])
			|| empty($messageGroup['copy_template_slug'])
			|| empty($messageGroup['copy_emails'])
			|| !is_array($messageGroup['copy_emails'])
		) {
			return;
		}

		$templateSlug = trim(
			(string) $messageGroup['copy_template_slug']
		);

		if ($templateSlug === '') {
			return;
		}

		$subjectConfig = trim(
			(string) ($messageGroup['copy_subject'] ?? '')
		);

		if ($subjectConfig === '') {
			$subjectConfig =
				'Nuevo registro de aspirante Asocolderma - [email]';
		}

		$subject = $this->tokenResolver->replace(
			$subjectConfig,
			[
				'email' => $email,
			],
		);

		foreach ($messageGroup['copy_emails'] as $copyEmail) {
			$copyEmail = trim((string) $copyEmail);

			if ($copyEmail === '') {
				continue;
			}

			try {
				$result = $this->mandrillService->sendTemplate(
					$templateSlug,
					[
						'subject' => $subject,
						'to_email' => $copyEmail,
					],
					[
						[
							'name' => 'USER_EMAIL',
							'content' => $email,
						],
					],
				);

				if (empty($result['success'])) {
					$this->logger->warning(
						'Mandrill no confirmó la copia interna de activación a @mail.',
						[
							'@mail' => $copyEmail,
						],
					);
				}
			} catch (\Throwable $e) {
				/*
         * Una copia administrativa nunca debe invalidar el envío principal
         * al aspirante.
         */
				$this->logger->warning(
					'Error enviando copia interna de activación a @mail: @error',
					[
						'@mail' => $copyEmail,
						'@error' => $e->getMessage(),
					],
				);
			}
		}
	}
}
