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
				'subject' => $this->buildEmailSubject($node, $phase_key, $context),
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
			];
		}
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
	 * The same variable names are used for Twilio ContentVariables.
	 */
	private function buildTwilioVariables(NodeInterface $node, string $phase_key, array $context): array
	{
		return $this->buildNotificationVariables($node, $phase_key, $context);
	}

	/**
	 * Builds the unified variables dictionary for Mandrill and Twilio.
	 *
	 * These keys must match the institutional dictionary exactly.
	 */
	private function buildNotificationVariables(NodeInterface $node, string $phase_key, array $context): array
	{
		$current_status = $this->resolveCurrentStateName($node);

		$previous_status = trim((string) ($context['request_previous_status'] ?? $context['previous_status'] ?? ''));
		$new_status = trim((string) ($context['request_new_status'] ?? $context['new_status'] ?? $current_status));

		$status_changed_date = trim((string) ($context['request_status_changed_date'] ?? $context['status_changed_date'] ?? ''));
		if ($status_changed_date === '' && !empty($context['status_changed_timestamp'])) {
			$status_changed_date = $this->formatTimestamp((int) $context['status_changed_timestamp']);
		}

		$status_changed_by = trim((string) ($context['request_status_changed_by'] ?? $context['status_changed_by'] ?? ''));
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
	private function buildEmailSubject(NodeInterface $node, string $phase_key, array $context): string
	{
		if (!empty($context['subject'])) {
			return trim((string) $context['subject']);
		}

		$estado = $this->resolveCurrentStateName($node);
		$solicitud_id = $this->getSolicitudCode($node);

		if ($estado !== '') {
			return sprintf('Actualización de solicitud de ingreso %s - %s', $solicitud_id, $estado);
		}

		return sprintf('Actualización de solicitud de ingreso %s', $solicitud_id);
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
