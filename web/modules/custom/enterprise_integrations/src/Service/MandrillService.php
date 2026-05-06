<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for sending transactional emails through Mailchimp Transactional.
 */
final class MandrillService
{

	/**
	 * Mandrill endpoint for sending messages with templates.
	 */
	private const ENDPOINT_SEND_TEMPLATE = 'https://mandrillapp.com/api/1.0/messages/send-template.json';

	/**
	 * HTTP client.
	 *
	 * @var \GuzzleHttp\ClientInterface
	 */
	protected ClientInterface $httpClient;

	/**
	 * Drupal config factory.
	 *
	 * @var \Drupal\Core\Config\ConfigFactoryInterface
	 */
	protected ConfigFactoryInterface $configFactory;

	/**
	 * Logger channel.
	 *
	 * @var \Drupal\Core\Logger\LoggerChannelInterface
	 */
	protected LoggerChannelInterface $logger;

	/**
	 * Constructs the service.
	 */
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
	 * Returns module settings from config.
	 *
	 * @return array
	 *   Settings array.
	 */
	public function getSettings(): array
	{
		$config = $this->configFactory->get('enterprise_integrations.settings');

		return [
			'api_key' => (string) $config->get('mandrill.api_key'),
		];
	}

	/**
	 * Validates required base configuration.
	 *
	 * @param array $config
	 *   Settings array.
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when base configuration is missing.
	 */
	protected function validateBaseConfiguration(array $config): void
	{
		$required = [
			'api_key',
		];

		foreach ($required as $key) {
			if (empty($config[$key])) {
				throw new \InvalidArgumentException(sprintf('Missing required Mandrill configuration: %s', $key));
			}
		}
	}

	/**
	 * Sanitizes payload before saving it in logs.
	 *
	 * @param array $payload
	 *   Original payload.
	 *
	 * @return array
	 *   Sanitized payload.
	 */
	protected function sanitizePayloadForLogs(array $payload): array
	{
		if (isset($payload['key'])) {
			$payload['key'] = '***redacted***';
		}

		return $payload;
	}

	public function getMessageGroupByKey(string $key): ?array
	{
		$config = $this->configFactory->get('enterprise_integrations.settings');
		$message_groups = $config->get('mandrill.message_groups') ?? [];

		if (!is_array($message_groups) || $key === '') {
			return NULL;
		}

		foreach ($message_groups as $group) {
			if (!is_array($group)) {
				continue;
			}

			if (($group['key'] ?? '') === $key) {
				return $group;
			}
		}

		return NULL;
	}

	public function sendTemplate(string $template_slug, array $params = [], array $merge_vars = []): array
	{
		$config = $this->getSettings();
		$this->validateBaseConfiguration($config);

		if ($template_slug === '') {
			throw new \InvalidArgumentException('El slug de la plantilla Mandrill es obligatorio.');
		}

		if (empty($params['subject'])) {
			throw new \InvalidArgumentException('El parámetro "subject" es obligatorio.');
		}

		if (empty($params['to_email'])) {
			throw new \InvalidArgumentException('El parámetro "to_email" es obligatorio.');
		}

		$recipients = [
			[
				'email' => $params['to_email'],
				'name' => $params['to_name'] ?? '',
				'type' => 'to',
			],
		];

		if (!empty($params['copy_emails']) && is_array($params['copy_emails'])) {
			foreach ($params['copy_emails'] as $copy_email) {
				$copy_email = trim((string) $copy_email);

				if ($copy_email !== '') {
					$recipients[] = [
						'email' => $copy_email,
						'type' => 'bcc',
					];
				}
			}
		}

		$message = [
			'subject' => $params['subject'],
			'to' => $recipients,
			'global_merge_vars' => $merge_vars,
		];

		$payload = [
			'key' => $config['api_key'],
			'template_name' => $template_slug,
			'template_content' => [],
			'message' => $message,
		];

		$response = $this->httpClient->request('POST', self::ENDPOINT_SEND_TEMPLATE, [
			'json' => $payload,
			'timeout' => 30,
			'connect_timeout' => 10,
			'http_errors' => FALSE,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
		]);

		$status_code = $response->getStatusCode();
		$body = (string) $response->getBody();
		$decoded = json_decode($body, TRUE);

		if ($status_code < 200 || $status_code >= 300) {
			$this->logger->error(
				'Mandrill send-template HTTP error. Status: @status Response: @response',
				[
					'@status' => $status_code,
					'@response' => $body,
				]
			);

			return [
				'success' => FALSE,
				'mandrill_response' => $decoded,
			];
		}

		if (is_array($decoded) && isset($decoded[0]['status']) && in_array($decoded[0]['status'], ['rejected', 'invalid'], TRUE)) {
			$this->logger->error(
				'Mandrill send-template rejected email. Response: @response',
				[
					'@response' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => FALSE,
				'mandrill_response' => $decoded,
			];
		}

		return [
			'success' => TRUE,
			'mandrill_response' => $decoded,
		];
	}
}
