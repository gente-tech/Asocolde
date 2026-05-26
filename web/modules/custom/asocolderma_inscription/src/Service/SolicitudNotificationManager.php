<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\enterprise_integrations\Service\MandrillService;
use Drupal\enterprise_integrations\Service\TwilioWhatsAppService;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Handles notification delivery for solicitud_ingreso workflow phases.
 */
final class SolicitudNotificationManager
{

	public function __construct(
		private readonly ConfigFactoryInterface $configFactory,
		private readonly MandrillService $mandrillService,
		private readonly TwilioWhatsAppService $twilioWhatsAppService,
		private readonly LoggerChannelInterface $logger,
	) {}

	/**
	 * Sends configured notifications for a workflow phase.
	 *
	 * @param \Drupal\node\NodeInterface $node
	 *   Solicitud ingreso node.
	 * @param string $phase_key
	 *   Internal workflow phase key configured in notification settings.
	 * @param array $context
	 *   Optional extra context.
	 *
	 * @return array
	 *   Normalized notification result.
	 */
	public function sendForPhase(NodeInterface $node, string $phase_key, array $context = []): array
	{
		if ($node->bundle() !== 'solicitud_ingreso') {
			return [
				'success' => FALSE,
				'message' => 'El nodo no corresponde al tipo solicitud_ingreso.',
				'mandrill' => NULL,
				'twilio' => NULL,
			];
		}

		$phase_config = $this->getPhaseConfig($phase_key);

		if (!$phase_config) {
			return [
				'success' => FALSE,
				'message' => sprintf('No existe configuración de notificaciones para la fase "%s".', $phase_key),
				'mandrill' => NULL,
				'twilio' => NULL,
			];
		}

		$mandrill_result = NULL;
		$twilio_result = NULL;

		$mandrill_key = trim((string) ($phase_config['mandrill_template_key'] ?? ''));
		$twilio_key = trim((string) ($phase_config['twilio_template_key'] ?? ''));

		if ($mandrill_key !== '') {
			$mandrill_result = $this->sendMandrillNotification($node, $phase_key, $mandrill_key, $context);
		}

		if ($twilio_key !== '') {
			$twilio_result = $this->sendTwilioNotification($node, $phase_key, $twilio_key, $context);
		}

		return [
			'success' => TRUE,
			'message' => 'Proceso de notificaciones ejecutado.',
			'mandrill' => $mandrill_result,
			'twilio' => $twilio_result,
		];
	}

	/**
	 * Returns phase configuration by key.
	 */
	private function getPhaseConfig(string $phase_key): ?array
	{
		$phase_key = trim($phase_key);

		if ($phase_key === '') {
			return NULL;
		}

		$config = $this->configFactory->get('asocolderma_inscription.notification_settings');
		$phase_config = $config->get("phases.$phase_key");

		return is_array($phase_config) ? $phase_config : NULL;
	}

	/**
	 * Sends Mandrill notification for a phase.
	 */
	private function sendMandrillNotification(
		NodeInterface $node,
		string $phase_key,
		string $mandrill_key,
		array $context,
	): array {
		try {
			$message_group = $this->mandrillService->getMessageGroupByKey($mandrill_key);

			if (!$message_group) {
				throw new \RuntimeException(sprintf('No existe configuración Mandrill con key "%s".', $mandrill_key));
			}

			$template_slug = trim((string) ($message_group['mandrill_template_slug'] ?? ''));

			if ($template_slug === '') {
				throw new \RuntimeException(sprintf('La configuración Mandrill "%s" no tiene slug de plantilla.', $mandrill_key));
			}

			$recipient_email = $this->resolveRecipientEmail($node);
			$recipient_name = $this->resolveRecipientName($node);

			if ($recipient_email === '') {
				throw new \RuntimeException('No fue posible enviar el correo porque la solicitud no tiene email principal.');
			}

			$params = [
				'subject' => $this->buildEmailSubject($node, $phase_key, $context, $message_group),
				'to_email' => $recipient_email,
				'to_name' => $recipient_name,
			];

			$merge_vars = $this->buildMandrillMergeVars($node, $phase_key, $context);

			$result = $this->mandrillService->sendTemplate($template_slug, $params, $merge_vars);

			$this->logger->notice(
				'Notificación Mandrill enviada para solicitud @nid en fase @phase usando key @key.',
				[
					'@nid' => $node->id(),
					'@phase' => $phase_key,
					'@key' => $mandrill_key,
				]
			);

			$result['copy_results'] = $this->sendMandrillCopies(
				$node,
				$phase_key,
				$context,
				$message_group,
				$merge_vars
			);

			return $result;
		} catch (\Throwable $e) {
			$this->logger->error(
				'Error enviando notificación Mandrill para solicitud @nid en fase @phase: @message',
				[
					'@nid' => $node->id(),
					'@phase' => $phase_key,
					'@message' => $e->getMessage(),
				]
			);

			return [
				'success' => FALSE,
				'message' => $e->getMessage(),
				'copy_results' => [],
			];
		}
	}

	/**
	 * Sends internal Mandrill copies configured in the selected message group.
	 */
	private function sendMandrillCopies(
		NodeInterface $node,
		string $phase_key,
		array $context,
		array $message_group,
		array $merge_vars,
	): array {
		if (empty($message_group['send_copy'])) {
			return [];
		}

		$copy_template_slug = trim((string) ($message_group['copy_template_slug'] ?? ''));

		if ($copy_template_slug === '') {
			$this->logger->warning(
				'La fase @phase tiene copia Mandrill activa, pero no tiene plantilla de copia configurada.',
				[
					'@phase' => $phase_key,
				]
			);

			return [];
		}

		$copy_emails = $this->normalizeCopyEmails($message_group['copy_emails'] ?? []);

		if ($copy_emails === []) {
			$this->logger->warning(
				'La fase @phase tiene copia Mandrill activa, pero no tiene correos de copia configurados.',
				[
					'@phase' => $phase_key,
				]
			);

			return [];
		}

		$copy_subject = $this->buildCopyEmailSubject($node, $phase_key, $context, $message_group);

		$copy_results = [];

		foreach ($copy_emails as $copy_email) {
			try {
				$copy_result = $this->mandrillService->sendTemplate(
					$copy_template_slug,
					[
						'subject' => $copy_subject,
						'to_email' => $copy_email,
						'to_name' => $copy_email,
					],
					$merge_vars
				);

				$copy_results[$copy_email] = $copy_result;

				if (empty($copy_result['success'])) {
					$this->logger->warning(
						'Mandrill no confirmó el envío de copia interna para solicitud @nid a @mail en fase @phase.',
						[
							'@nid' => $node->id(),
							'@mail' => $copy_email,
							'@phase' => $phase_key,
						]
					);
				} else {
					$this->logger->notice(
						'Copia interna Mandrill enviada para solicitud @nid a @mail en fase @phase.',
						[
							'@nid' => $node->id(),
							'@mail' => $copy_email,
							'@phase' => $phase_key,
						]
					);
				}
			} catch (\Throwable $e) {
				$copy_results[$copy_email] = [
					'success' => FALSE,
					'message' => $e->getMessage(),
				];

				$this->logger->error(
					'Error enviando copia interna Mandrill para solicitud @nid a @mail en fase @phase: @message',
					[
						'@nid' => $node->id(),
						'@mail' => $copy_email,
						'@phase' => $phase_key,
						'@message' => $e->getMessage(),
					]
				);
			}
		}

		return $copy_results;
	}

	/**
	 * Builds the subject used for internal copy emails.
	 */
	private function buildCopyEmailSubject(
		NodeInterface $node,
		string $phase_key,
		array $context,
		array $message_group,
	): string {
		$copy_subject_config = trim((string) ($message_group['copy_subject'] ?? ''));

		if ($copy_subject_config !== '') {
			return $this->replaceSubjectTokens($copy_subject_config, $node, $phase_key, $context);
		}

		$main_subject = $this->buildEmailSubject($node, $phase_key, $context, $message_group);

		return trim('Copia interna - ' . $main_subject);
	}

	/**
	 * Normalizes copy email configuration.
	 */
	private function normalizeCopyEmails(mixed $copy_emails): array
	{
		if (is_string($copy_emails)) {
			$copy_emails = preg_split('/[\n,;]+/', $copy_emails) ?: [];
		}

		if (!is_array($copy_emails)) {
			return [];
		}

		$clean_emails = [];

		foreach ($copy_emails as $copy_email) {
			$copy_email = trim((string) $copy_email);

			if ($copy_email === '') {
				continue;
			}

			if (!filter_var($copy_email, FILTER_VALIDATE_EMAIL)) {
				$this->logger->warning(
					'Correo de copia Mandrill inválido omitido: @mail.',
					[
						'@mail' => $copy_email,
					]
				);

				continue;
			}

			$clean_emails[$copy_email] = $copy_email;
		}

		return array_values($clean_emails);
	}

	/**
	 * Sends Twilio WhatsApp notification for a phase.
	 */
	private function sendTwilioNotification(
		NodeInterface $node,
		string $phase_key,
		string $twilio_key,
		array $context,
	): array {
		try {
			$recipient_phone = $this->resolveRecipientPhone($node);

			if ($recipient_phone === '') {
				throw new \RuntimeException('No fue posible enviar WhatsApp porque la solicitud no tiene celular.');
			}

			$variables = $this->buildTwilioVariables($node, $phase_key, $context);

			$result = $this->twilioWhatsAppService->sendTemplateByKey($twilio_key, $recipient_phone, $variables);

			$this->logger->notice(
				'Notificación Twilio enviada para solicitud @nid en fase @phase usando key @key.',
				[
					'@nid' => $node->id(),
					'@phase' => $phase_key,
					'@key' => $twilio_key,
				]
			);

			return $result;
		} catch (\Throwable $e) {
			$this->logger->error(
				'Error enviando notificación Twilio para solicitud @nid en fase @phase: @message',
				[
					'@nid' => $node->id(),
					'@phase' => $phase_key,
					'@message' => $e->getMessage(),
				]
			);

			return [
				'success' => FALSE,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Builds Mandrill global merge vars.
	 *
	 * These variable names are intended to be used in Mandrill as:
	 * *|user_full_name|*
	 * *|request_code|*
	 * *|request_current_status|*
	 */
	private function buildMandrillMergeVars(NodeInterface $node, string $phase_key, array $context): array
	{
		$variables = $this->buildNotificationVariables($node, $phase_key, $context);

		$merge_vars = [];

		foreach ($variables as $name => $content) {
			$merge_vars[] = [
				'name' => $name,
				'content' => $content,
			];
		}

		return $merge_vars;
	}

	/**
	 * Builds Twilio WhatsApp template variables.
	 *
	 * Soporta temporalmente varios tipos de plantillas:
	 *
	 * 1. Plantilla general de creación/cambio de estado:
	 *    {{1}} = Nombre completo del aspirante
	 *    {{2}} = Código público de la solicitud
	 *    {{3}} = Estado de la solicitud
	 *
	 * 2. Plantilla de rechazo:
	 *    {{name_user}} = Nombre completo del aspirante
	 *    {{id_solicitud}} = Código público de la solicitud
	 *
	 * 3. Plantilla de pendiente aclaración:
	 *    {{name_user}} = Nombre completo del aspirante
	 *    {{id_solicitud}} = Código público de la solicitud
	 *    {{motivo_aclaracion}} = Motivo/comentario de aclaración
	 *
	 * 4. Plantilla de pendiente firma de documentos:
	 *    {{name_user}} = Nombre completo del aspirante
	 *    {{id_solicitud}} = Código público de la solicitud
	 *
	 * 5. Plantilla de pendiente pago de ingreso:
	 *    {{name_user}} = Nombre completo del aspirante
	 *    {{id_solicitud}} = Código público de la solicitud
	 *
	 * Más adelante este método podrá normalizarse usando el diccionario
	 * institucional completo de variables.
	 */
	private function buildTwilioVariables(NodeInterface $node, string $phase_key, array $context): array
	{
		$variables = $this->buildNotificationVariables($node, $phase_key, $context);

		$user_full_name = trim((string) ($variables['user_full_name'] ?? ''));
		$request_code = trim((string) ($variables['request_code'] ?? ''));

		$status = trim((string) ($variables['request_new_status'] ?? ''));

		if ($status === '') {
			$status = trim((string) ($variables['request_current_status'] ?? ''));
		}

		$phase_key_normalized = mb_strtolower($phase_key);
		$status_normalized = mb_strtolower($status);

		$is_clarification_phase = str_contains($phase_key_normalized, 'pendiente_aclaracion')
			|| str_contains($phase_key_normalized, 'aclaracion')
			|| str_contains($status_normalized, 'pendiente aclaración')
			|| str_contains($status_normalized, 'pendiente aclaracion')
			|| str_contains($status_normalized, 'aclaración')
			|| str_contains($status_normalized, 'aclaracion');

		if ($is_clarification_phase) {
			$motivo_aclaracion = trim((string) ($variables['request_status_change_comment'] ?? ''));

			if ($motivo_aclaracion === '') {
				$motivo_aclaracion = trim((string) ($context['request_status_change_comment'] ?? ''));
			}

			if ($motivo_aclaracion === '') {
				$motivo_aclaracion = 'Por favor ingresa a la plataforma para consultar el detalle de la aclaración solicitada.';
			}

			return [
				'name_user' => $user_full_name,
				'id_solicitud' => $request_code,
				'motivo_aclaracion' => $motivo_aclaracion,
			];
		}

		$is_rejected_phase = str_contains($phase_key_normalized, 'rechazada')
			|| str_contains($phase_key_normalized, 'rechazado')
			|| $status_normalized === 'rechazada'
			|| $status_normalized === 'rechazado';

		if ($is_rejected_phase) {
			return [
				'name_user' => $user_full_name,
				'id_solicitud' => $request_code,
			];
		}

		$is_signature_pending_phase = str_contains($phase_key_normalized, 'pendiente_firma')
			|| str_contains($phase_key_normalized, 'firma')
			|| str_contains($status_normalized, 'pendiente firma')
			|| str_contains($status_normalized, 'pendiente de firma')
			|| str_contains($status_normalized, 'firma de documentos');

		if ($is_signature_pending_phase) {
			return [
				'name_user' => $user_full_name,
				'id_solicitud' => $request_code,
			];
		}

		$is_payment_pending_phase = str_contains($phase_key_normalized, 'pendiente_pago')
			|| str_contains($phase_key_normalized, 'pago')
			|| str_contains($status_normalized, 'pendiente pago')
			|| str_contains($status_normalized, 'pendiente de pago')
			|| str_contains($status_normalized, 'pago de ingreso');

		if ($is_payment_pending_phase) {
			return [
				'name_user' => $user_full_name,
				'id_solicitud' => $request_code,
			];
		}

		return [
			'1' => $user_full_name,
			'2' => $request_code,
			'3' => $status,
		];
	}

	/**
	 * Builds the unified variables dictionary for Mandrill and Twilio.
	 *
	 * These keys must match the institutional dictionary exactly.
	 */
	private function buildNotificationVariables(NodeInterface $node, string $phase_key, array $context): array
	{
		$current_status = $this->resolveCurrentStateName($node);

		$previous_status = trim((string) (
			$context['request_previous_status']
			?? $context['previous_status']
			?? $context['from_state']
			?? ''
		));

		$new_status = trim((string) (
			$context['request_new_status']
			?? $context['new_status']
			?? $context['to_state']
			?? $current_status
		));

		$status_changed_date = trim((string) (
			$context['request_status_changed_date']
			?? $context['status_changed_date']
			?? ''
		));

		if ($status_changed_date === '') {
			$status_changed_timestamp = (int) (
				$context['request_status_changed_timestamp']
				?? $context['status_changed_timestamp']
				?? $context['changed_timestamp']
				?? \Drupal::time()->getRequestTime()
			);

			$status_changed_date = $this->formatTimestamp($status_changed_timestamp);
		}

		$status_changed_by = trim((string) (
			$context['request_status_changed_by']
			?? $context['status_changed_by']
			?? ''
		));
		if ($status_changed_by === '' && !empty($context['changed_by'])) {
			$status_changed_by = (string) $context['changed_by'];
		}

		return [
			'user_full_name' => $this->resolveRecipientName($node),
			'user_first_name' => $this->resolveFirstName($node),
			'user_last_name' => $this->resolveLastName($node),
			'user_email' => $this->resolveRecipientEmail($node),
			'user_mobile' => $this->resolveRecipientPhone($node),
			'user_document_number' => $this->resolveDocumentNumber($node),
			'user_activation_url' => $this->resolveActivationUrl($node, $context),

			'request_code' => $this->getSolicitudCode($node),
			'request_url' => $this->resolveRequestUrl($node),
			'request_created_date' => $this->resolveRequestCreatedDate($node),
			'request_current_status' => $current_status,
			'request_previous_status' => $previous_status,
			'request_new_status' => $new_status,
			'request_status_changed_date' => $status_changed_date,
			'request_status_changed_by' => $status_changed_by,
			'request_status_change_comment' => trim((string) ($context['request_status_change_comment'] ?? $context['status_change_comment'] ?? $context['comment'] ?? '')),
		];
	}

	/**
	 * Builds a default email subject.
	 */
	private function buildEmailSubject(NodeInterface $node, string $phase_key, array $context, array $message_group = []): string
	{
		$subject_config = trim((string) ($message_group['subject'] ?? ''));

		if ($subject_config !== '') {
			return $this->replaceSubjectTokens($subject_config, $node, $phase_key, $context);
		}

		if (!empty($context['subject'])) {
			return $this->replaceSubjectTokens(trim((string) $context['subject']), $node, $phase_key, $context);
		}

		$estado = $this->resolveCurrentStateName($node);
		$solicitud_id = $this->getSolicitudCode($node);

		if ($estado !== '') {
			return sprintf('Actualización de solicitud de ingreso %s - %s', $solicitud_id, $estado);
		}

		return sprintf('Actualización de solicitud de ingreso %s', $solicitud_id);
	}

	private function replaceSubjectTokens(string $subject, NodeInterface $node, string $phase_key, array $context): string
	{
		$variables = $this->buildNotificationVariables($node, $phase_key, $context);

		$replacements = [];

		foreach ($variables as $key => $value) {
			$value = trim((string) $value);

			// Soporta tokens tipo [request_code].
			$replacements['[' . $key . ']'] = $value;

			// Soporta tokens tipo *|request_code|* por si se usan también en asunto.
			$replacements['*|' . $key . '|*'] = $value;
		}

		return trim(strtr($subject, $replacements));
	}

	/**
	 * Resolves applicant full name.
	 */
	private function resolveRecipientName(NodeInterface $node): string
	{
		$parts = [];

		foreach (['field_nombre1', 'field_nombre2', 'field_apellido1', 'field_apellido2'] as $field_name) {
			if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
				$parts[] = trim((string) $node->get($field_name)->value);
			}
		}

		$full_name = trim(implode(' ', array_filter($parts)));

		if ($full_name !== '') {
			return $full_name;
		}

		$owner = $node->getOwner();

		if ($owner) {
			return trim((string) $owner->getDisplayName());
		}

		return '';
	}

	/**
	 * Resolves applicant email.
	 */
	private function resolveRecipientEmail(NodeInterface $node): string
	{
		foreach (['field_email_principal', 'field_email'] as $field_name) {
			if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
				return trim((string) $node->get($field_name)->value);
			}
		}

		$owner = $node->getOwner();

		if ($owner && $owner->getEmail()) {
			return trim((string) $owner->getEmail());
		}

		return '';
	}

	/**
	 * Resolves applicant cellphone.
	 */
	private function resolveRecipientPhone(NodeInterface $node): string
	{
		foreach (['field_celular', 'field_telefono'] as $field_name) {
			if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
				return trim((string) $node->get($field_name)->value);
			}
		}

		return '';
	}

	/**
	 * Gets solicitud public code.
	 */
	private function getSolicitudCode(NodeInterface $node): string
	{
		if ($node->hasField('field_solicitud_id') && !$node->get('field_solicitud_id')->isEmpty()) {
			return trim((string) $node->get('field_solicitud_id')->value);
		}

		return 'NID-' . $node->id();
	}

	/**
	 * Resolves current solicitud state label.
	 */
	private function resolveCurrentStateName(NodeInterface $node): string
	{
		if (!$node->hasField('field_state') || $node->get('field_state')->isEmpty()) {
			return '';
		}

		$entity = $node->get('field_state')->entity;

		if ($entity) {
			return (string) $entity->label();
		}

		return '';
	}

	/**
	 * Resolves applicant first name.
	 */
	private function resolveFirstName(NodeInterface $node): string
	{
		if ($node->hasField('field_nombre1') && !$node->get('field_nombre1')->isEmpty()) {
			return trim((string) $node->get('field_nombre1')->value);
		}

		return '';
	}

	/**
	 * Resolves applicant first last name.
	 */
	private function resolveLastName(NodeInterface $node): string
	{
		if ($node->hasField('field_apellido1') && !$node->get('field_apellido1')->isEmpty()) {
			return trim((string) $node->get('field_apellido1')->value);
		}

		return '';
	}

	/**
	 * Resolves applicant document number.
	 */
	private function resolveDocumentNumber(NodeInterface $node): string
	{
		if ($node->hasField('field_numero_documento') && !$node->get('field_numero_documento')->isEmpty()) {
			return trim((string) $node->get('field_numero_documento')->value);
		}

		return '';
	}

	/**
	 * Resolves account activation URL.
	 *
	 * Important:
	 * The solicitud creation flow normally happens after the aspirante account
	 * has already been activated. Therefore this value must be passed in context
	 * when the caller really has an activation URL available.
	 */
	private function resolveActivationUrl(NodeInterface $node, array $context): string
	{
		return trim((string) ($context['user_activation_url'] ?? $context['activation_url'] ?? ''));
	}

	/**
	 * Resolves absolute public URL for the solicitud.
	 */
	private function resolveRequestUrl(NodeInterface $node): string
	{
		try {
			if (!$node->id()) {
				return '';
			}

			return Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE])->toString();
		} catch (\Throwable) {
			return '';
		}
	}

	/**
	 * Resolves solicitud created date.
	 */
	private function resolveRequestCreatedDate(NodeInterface $node): string
	{
		try {
			return $this->formatTimestamp((int) $node->getCreatedTime());
		} catch (\Throwable) {
			return '';
		}
	}

	/**
	 * Formats timestamps consistently for notification variables.
	 */
	private function formatTimestamp(int $timestamp): string
	{
		if ($timestamp <= 0) {
			return '';
		}

		return \Drupal::service('date.formatter')->format($timestamp, 'custom', 'd/m/Y H:i');
	}
}
