<?php

declare(strict_types=1);

namespace Drupal\custom_login_2fa\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\enterprise_integrations\Service\MandrillService;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages reusable email-based 2FA login codes.
 */
final class CustomLogin2faManager
{

	/**
	 * Allowed characters for generated codes.
	 *
	 * Excludes confusing characters: I, O, 0, 1.
	 */
	private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	public function __construct(
		private readonly Connection $database,
		private readonly ConfigFactoryInterface $configFactory,
		private readonly TimeInterface $time,
		private readonly RequestStack $requestStack,
		private readonly MandrillService $mandrill,
		private readonly LoggerChannelInterface $logger,
	) {}

	/**
	 * Determines whether the module is enabled.
	 */
	public function isEnabled(): bool
	{
		return (bool) $this->configFactory
			->get('custom_login_2fa.settings')
			->get('enabled');
	}

	/**
	 * Gets protected role IDs from config.
	 *
	 * @return string[]
	 *   Protected role IDs.
	 */
	public function getProtectedRoles(): array
	{
		$roles = $this->configFactory
			->get('custom_login_2fa.settings')
			->get('protected_roles') ?? [];

		if (!is_array($roles)) {
			return [];
		}

		return array_values(array_filter(array_map('strval', $roles)));
	}

	/**
	 * Determines whether the given user must complete 2FA.
	 */
	public function userRequires2fa(UserInterface $user): bool
	{
		if (!$this->isEnabled()) {
			return FALSE;
		}

		if ($user->isAnonymous() || !$user->isActive()) {
			return FALSE;
		}

		$protected_roles = $this->getProtectedRoles();
		if ($protected_roles === []) {
			return FALSE;
		}

		return count(array_intersect($protected_roles, $user->getRoles())) > 0;
	}

	/**
	 * Creates a 2FA challenge and sends the code by Mandrill.
	 *
	 * @throws \RuntimeException
	 *   Thrown when the code cannot be sent.
	 */
	public function createAndSendChallenge(UserInterface $user): int
	{
		if (!$this->userRequires2fa($user)) {
			throw new \InvalidArgumentException('The user does not require 2FA.');
		}

		$mail = trim((string) $user->getEmail());
		if ($mail === '') {
			throw new \RuntimeException('The user does not have an email address.');
		}

		$config = $this->configFactory->get('custom_login_2fa.settings');

		$ttl = max(10, (int) ($config->get('code_ttl') ?: 20));
		$length = max(5, min(10, (int) ($config->get('code_length') ?: 5)));
		$now = $this->time->getRequestTime();

		$code = $this->generateCode($length);

		$this->invalidatePendingCodes((int) $user->id());

		$request = $this->requestStack->getCurrentRequest();

		$challenge_id = (int) $this->database->insert('custom_login_2fa_code')
			->fields([
				'uid' => (int) $user->id(),
				'mail' => $mail,
				'code_hash' => password_hash($code, PASSWORD_DEFAULT),
				'created' => $now,
				'expires' => $now + $ttl,
				'consumed' => NULL,
				'attempts' => 0,
				'ip_address' => $request?->getClientIp(),
				'user_agent' => substr((string) $request?->headers->get('User-Agent'), 0, 512),
				'status' => 'pending',
			])
			->execute();

		try {
			$this->sendCodeEmail($user, $code, $ttl);
		} catch (\Throwable $exception) {
			$this->database->update('custom_login_2fa_code')
				->fields([
					'status' => 'blocked',
				])
				->condition('id', $challenge_id)
				->execute();

			$this->logger->error(
				'2FA code could not be sent to user @uid. Error: @error',
				[
					'@uid' => $user->id(),
					'@error' => $exception->getMessage(),
				]
			);

			throw new \RuntimeException('No fue posible enviar el código de verificación.');
		}

		$this->logger->notice(
			'2FA challenge created for user @uid. Challenge ID: @challenge_id',
			[
				'@uid' => $user->id(),
				'@challenge_id' => $challenge_id,
			]
		);

		return $challenge_id;
	}

	/**
	 * Validates the submitted code.
	 *
	 * @return array
	 *   Result array with keys: valid, message.
	 */
	public function validateCode(int $uid, string $submitted_code): array
	{
		$submitted_code = strtoupper(trim($submitted_code));

		if ($submitted_code === '') {
			return [
				'valid' => FALSE,
				'message' => 'Debes ingresar el código de verificación.',
			];
		}

		$config = $this->configFactory->get('custom_login_2fa.settings');
		$max_attempts = max(1, (int) ($config->get('max_attempts') ?: 3));
		$now = $this->time->getRequestTime();

		$record = $this->database->select('custom_login_2fa_code', 'c')
			->fields('c')
			->condition('uid', $uid)
			->condition('status', 'pending')
			->orderBy('id', 'DESC')
			->range(0, 1)
			->execute()
			->fetchObject();

		if (!$record) {
			return [
				'valid' => FALSE,
				'message' => 'No existe un código pendiente para validar.',
			];
		}

		if ((int) $record->expires < $now) {
			$this->database->update('custom_login_2fa_code')
				->fields([
					'status' => 'expired',
				])
				->condition('id', (int) $record->id)
				->execute();

			return [
				'valid' => FALSE,
				'message' => 'El código venció. Debes iniciar sesión nuevamente.',
			];
		}

		if ((int) $record->attempts >= $max_attempts) {
			$this->database->update('custom_login_2fa_code')
				->fields([
					'status' => 'blocked',
				])
				->condition('id', (int) $record->id)
				->execute();

			return [
				'valid' => FALSE,
				'message' => 'El código fue bloqueado por superar el máximo de intentos.',
			];
		}

		$this->database->update('custom_login_2fa_code')
			->expression('attempts', 'attempts + 1')
			->condition('id', (int) $record->id)
			->execute();

		if (!password_verify($submitted_code, (string) $record->code_hash)) {
			return [
				'valid' => FALSE,
				'message' => 'El código ingresado no es válido.',
			];
		}

		$this->database->update('custom_login_2fa_code')
			->fields([
				'status' => 'consumed',
				'consumed' => $now,
			])
			->condition('id', (int) $record->id)
			->execute();

		$this->logger->notice(
			'2FA challenge consumed successfully for user @uid. Challenge ID: @challenge_id',
			[
				'@uid' => $uid,
				'@challenge_id' => $record->id,
			]
		);

		return [
			'valid' => TRUE,
			'message' => 'Código validado correctamente.',
		];
	}

	/**
	 * Invalidates pending codes for a user.
	 */
	public function invalidatePendingCodes(int $uid): void
	{
		$this->database->update('custom_login_2fa_code')
			->fields([
				'status' => 'expired',
			])
			->condition('uid', $uid)
			->condition('status', 'pending')
			->execute();
	}

	/**
	 * Generates an uppercase alphanumeric 2FA code.
	 */
	private function generateCode(int $length): string
	{
		$alphabet_length = strlen(self::CODE_ALPHABET);
		$code = '';

		for ($i = 0; $i < $length; $i++) {
			$code .= self::CODE_ALPHABET[random_int(0, $alphabet_length - 1)];
		}

		return $code;
	}

	/**
	 * Sends the 2FA code using enterprise_integrations Mandrill service.
	 */
	private function sendCodeEmail(UserInterface $user, string $code, int $ttl): void
	{
		$config = $this->configFactory->get('custom_login_2fa.settings');

		$message_key = trim((string) $config->get('mandrill_message_key'));
		if ($message_key === '') {
			throw new \RuntimeException('No se configuró la clave del mensaje Mandrill para 2FA.');
		}

		$message_group = $this->mandrill->getMessageGroupByKey($message_key);
		if (!$message_group) {
			throw new \RuntimeException(sprintf('No existe el grupo de mensaje Mandrill con key "%s".', $message_key));
		}

		$template_slug = trim((string) ($message_group['mandrill_template_slug'] ?? ''));
		$subject = trim((string) ($message_group['subject'] ?? ''));

		if ($template_slug === '') {
			throw new \RuntimeException(sprintf('El grupo Mandrill "%s" no tiene mandrill_template_slug configurado.', $message_key));
		}

		if ($subject === '') {
			throw new \RuntimeException(sprintf('El grupo Mandrill "%s" no tiene subject configurado.', $message_key));
		}

		$site_name = (string) $this->configFactory
			->get('system.site')
			->get('name');

		$to_name = $user->getDisplayName();
		$to_email = (string) $user->getEmail();

		$merge_vars = [
			[
				'name' => 'CODE',
				'content' => $code,
			],
			[
				'name' => 'TTL',
				'content' => (string) $ttl,
			],
			[
				'name' => 'USER_FULL_NAME',
				'content' => $to_name,
			],
			[
				'name' => 'USER_NAME',
				'content' => $user->getAccountName(),
			],
			[
				'name' => 'USER_EMAIL',
				'content' => $to_email,
			],
			[
				'name' => 'SITE_NAME',
				'content' => $site_name,
			],
		];

		$params = [
			'subject' => $subject,
			'to_email' => $to_email,
			'to_name' => $to_name,
		];

		$result = $this->mandrill->sendTemplate($template_slug, $params, $merge_vars);

		if (empty($result['success'])) {
			throw new \RuntimeException('Mandrill no confirmó el envío del código 2FA.');
		}
	}

	/**
	 * Gets redirect path after successful 2FA validation.
	 */
	public function getRedirectPathForUser(UserInterface $user): string
	{
		$config = $this->configFactory->get('custom_login_2fa.settings');

		$role_redirect_paths = $config->get('role_redirect_paths') ?: [];
		if (!is_array($role_redirect_paths)) {
			$role_redirect_paths = [];
		}

		foreach ($user->getRoles() as $role_id) {
			if (!empty($role_redirect_paths[$role_id]) && is_string($role_redirect_paths[$role_id])) {
				return $role_redirect_paths[$role_id];
			}
		}

		$default_path = trim((string) ($config->get('default_redirect_path') ?: '/'));

		return str_starts_with($default_path, '/') ? $default_path : '/';
	}
}
