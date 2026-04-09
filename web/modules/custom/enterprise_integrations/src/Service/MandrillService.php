<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for sending transactional emails through Mailchimp Transactional.
 */
final class MandrillService
{

	/**
	 * Mandrill endpoint for sending messages.
	 */
	private const ENDPOINT_SEND = 'https://mandrillapp.com/api/1.0/messages/send.json';

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

	private function getMailAssets(): array
	{
		$config = $this->configFactory->get('enterprise_integrations.settings');

		$logo_fid = $config->get('mail_logo');
		$banner_fid = $config->get('mail_banner');

		$logo_url = '';
		$banner_url = '';

		if (!empty($logo_fid[0])) {
			$file = \Drupal\file\Entity\File::load((int) $logo_fid[0]);
			if ($file) {
				$logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
			}
		}

		if (!empty($banner_fid[0])) {
			$file = \Drupal\file\Entity\File::load((int) $banner_fid[0]);
			if ($file) {
				$banner_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
			}
		}

		return [
			'logo_url' => $logo_url,
			'banner_url' => $banner_url,
		];
	}

	/**
	 * Sends an email through Mandrill using the configured defaults.
	 *
	 * Expected $params structure:
	 * - to_email: string
	 * - to_name: string|null
	 * - subject: string|null
	 * - html: string|null
	 * - text: string|null
	 * - internal_copy: bool|null
	 * - reply_to: string|null
	 * - tags: array|null
	 * - metadata: array|null
	 *
	 * @param array $params
	 *   Message parameters.
	 *
	 * @return array
	 *   Structured response:
	 *   - success: bool
	 *   - message: string
	 *   - mandrill_response: array|null
	 *   - request_payload: array|null
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when required data is missing.
	 */
	public function send(array $params): array
	{
		$config = $this->getSettings();

		$this->validateBaseConfiguration($config);
		$this->validateMessageParams($params);

		$subject = !empty($params['subject'])
			? (string) $params['subject']
			: (string) $config['default_subject'];

		$html = !empty($params['html'])
			? (string) $params['html']
			: '';

		$text = !empty($params['text'])
			? (string) $params['text']
			: '';

		$recipients = [
			[
				'email' => (string) $params['to_email'],
				'name' => (string) ($params['to_name'] ?? ''),
				'type' => 'to',
			],
		];

		if (!empty($params['internal_copy']) && !empty($config['internal_copy_enabled']) && !empty($config['internal_copy_email'])) {
			$recipients[] = [
				'email' => (string) $config['internal_copy_email'],
				'name' => (string) ($config['internal_copy_name'] ?? ''),
				'type' => 'to',
			];
		}

		$message = [
			'from_email' => (string) $config['from_email'],
			'from_name' => (string) $config['from_name'],
			'subject' => $subject,
			'to' => $recipients,
			'headers' => [],
		];

		if ($html !== '') {
			$message['html'] = $html;
		}

		if ($text !== '') {
			$message['text'] = $text;
		}

		if (!empty($params['reply_to'])) {
			$message['headers']['Reply-To'] = (string) $params['reply_to'];
		}

		if (!empty($params['tags']) && is_array($params['tags'])) {
			$message['tags'] = array_values($params['tags']);
		}

		if (!empty($params['metadata']) && is_array($params['metadata'])) {
			$message['metadata'] = $params['metadata'];
		}

		if (empty($message['headers'])) {
			unset($message['headers']);
		}

		$payload = [
			'key' => (string) $config['api_key'],
			'message' => $message,
		];

		try {
			$response = $this->httpClient->request('POST', self::ENDPOINT_SEND, [
				'json' => $payload,
				'timeout' => 30,
				'connect_timeout' => 10,
				'http_errors' => FALSE,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
			]);

			$statusCode = $response->getStatusCode();
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, TRUE);

			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logger->error(
					'Mandrill HTTP error. Status: @status. Response: @response. Payload: @payload',
					[
						'@status' => $statusCode,
						'@response' => $body,
						'@payload' => json_encode($this->sanitizePayloadForLogs($payload), JSON_UNESCAPED_UNICODE),
					]
				);

				return [
					'success' => FALSE,
					'message' => 'Mandrill responded with an HTTP error.',
					'mandrill_response' => is_array($decodedBody) ? $decodedBody : NULL,
					'request_payload' => $this->sanitizePayloadForLogs($payload),
				];
			}

			if (is_array($decodedBody) && isset($decodedBody[0]['status']) && in_array($decodedBody[0]['status'], ['rejected', 'invalid'], TRUE)) {
				$this->logger->error(
					'Mandrill rejected the email. Response: @response. Payload: @payload',
					[
						'@response' => json_encode($decodedBody, JSON_UNESCAPED_UNICODE),
						'@payload' => json_encode($this->sanitizePayloadForLogs($payload), JSON_UNESCAPED_UNICODE),
					]
				);

				return [
					'success' => FALSE,
					'message' => 'Mandrill rejected the email.',
					'mandrill_response' => $decodedBody,
					'request_payload' => $this->sanitizePayloadForLogs($payload),
				];
			}

			$this->logger->notice(
				'Mandrill email sent successfully to @email. Response: @response',
				[
					'@email' => $params['to_email'],
					'@response' => json_encode($decodedBody, JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => TRUE,
				'message' => 'Email sent successfully.',
				'mandrill_response' => is_array($decodedBody) ? $decodedBody : NULL,
				'request_payload' => $this->sanitizePayloadForLogs($payload),
			];
		} catch (GuzzleException $e) {
			$this->logger->error(
				'Mandrill connection error: @message. Payload: @payload',
				[
					'@message' => $e->getMessage(),
					'@payload' => json_encode($this->sanitizePayloadForLogs($payload), JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => FALSE,
				'message' => 'Connection error while sending email through Mandrill.',
				'mandrill_response' => NULL,
				'request_payload' => $this->sanitizePayloadForLogs($payload),
			];
		} catch (\Throwable $e) {
			$this->logger->error(
				'Unexpected Mandrill service error: @message. Payload: @payload',
				[
					'@message' => $e->getMessage(),
					'@payload' => json_encode($this->sanitizePayloadForLogs($payload), JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => FALSE,
				'message' => 'Unexpected error while sending email.',
				'mandrill_response' => NULL,
				'request_payload' => $this->sanitizePayloadForLogs($payload),
			];
		}
	}

	/**
	 * Builds HTML content from a configurable template and tokens.
	 *
	 * Supported token format:
	 * {{nombre}}, {{email}}, {{telefono}}, etc.
	 *
	 * @param string $template
	 *   Raw HTML template.
	 * @param array $tokens
	 *   Key/value token replacements.
	 *
	 * @return string
	 *   Final rendered HTML.
	 */
	public function renderTemplate(string $template, array $tokens = []): string
	{
		$replace = [];

		$assets = $this->getMailAssets();

		$replace['{{logo_url}}'] = $assets['logo_url'] ?? '';
		$replace['{{banner_url}}'] = $assets['banner_url'] ?? '';

		foreach ($tokens as $key => $value) {
			$replace['{{' . trim((string) $key) . '}}'] = nl2br((string) $value);
		}

		return strtr($template, $replace);
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
			'from_email' => (string) $config->get('mandrill.from_email'),
			'from_name' => (string) $config->get('mandrill.from_name'),
			'default_subject' => (string) $config->get('mandrill.default_subject'),
			'default_html_template' => (string) $config->get('mandrill.default_html_template'),
			'internal_copy_enabled' => (bool) $config->get('mandrill.internal_copy_enabled'),
			'internal_copy_email' => (string) $config->get('mandrill.internal_copy_email'),
			'internal_copy_name' => (string) $config->get('mandrill.internal_copy_name'),
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
			'from_email',
			'from_name',
		];

		foreach ($required as $key) {
			if (empty($config[$key])) {
				throw new \InvalidArgumentException(sprintf('Missing required Mandrill configuration: %s', $key));
			}
		}
	}

	/**
	 * Validates runtime message parameters.
	 *
	 * @param array $params
	 *   Message parameters.
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when required parameters are missing.
	 */
	protected function validateMessageParams(array $params): void
	{
		if (empty($params['to_email'])) {
			throw new \InvalidArgumentException('Missing required parameter: to_email');
		}

		if (empty($params['html']) && empty($params['text'])) {
			throw new \InvalidArgumentException('At least one of html or text must be provided.');
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
}
