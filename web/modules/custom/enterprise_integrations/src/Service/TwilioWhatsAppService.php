<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for sending WhatsApp messages through Twilio.
 */
final class TwilioWhatsAppService
{

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
	 * Returns Twilio settings from configuration.
	 *
	 * @return array
	 *   Settings array.
	 */
	public function getSettings(): array
	{
		$config = $this->configFactory->get('enterprise_integrations.settings');

		return [
			'account_sid' => trim((string) $config->get('twilio.account_sid')),
			'auth_token' => trim((string) $config->get('twilio.auth_token')),
			'api_base_url' => rtrim(trim((string) $config->get('twilio.api_base_url')), '/'),
			'whatsapp_from' => trim((string) $config->get('twilio.whatsapp_from')),
			'templates' => $config->get('twilio.templates') ?? [],
		];
	}

	/**
	 * Validates required Twilio base configuration.
	 *
	 * @param array $settings
	 *   Twilio settings.
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when required configuration is missing.
	 */
	protected function validateBaseConfiguration(array $settings): void
	{
		$required = [
			'account_sid',
			'auth_token',
			'api_base_url',
			'whatsapp_from',
		];

		foreach ($required as $key) {
			if (empty($settings[$key])) {
				throw new \InvalidArgumentException(sprintf('Missing required Twilio configuration: %s', $key));
			}
		}

		if (strpos($settings['account_sid'], 'AC') !== 0) {
			throw new \InvalidArgumentException('El Account SID de Twilio debe iniciar por AC.');
		}

		if (!filter_var($settings['api_base_url'], FILTER_VALIDATE_URL)) {
			throw new \InvalidArgumentException('La API Base URL de Twilio no es válida.');
		}

		if (strpos($settings['whatsapp_from'], 'whatsapp:+') !== 0) {
			throw new \InvalidArgumentException('El campo WhatsApp From debe iniciar por whatsapp:+');
		}
	}

	/**
	 * Returns a configured Twilio template by key.
	 *
	 * @param string $key
	 *   Internal template key.
	 *
	 * @return array|null
	 *   Template configuration or NULL when not found.
	 */
	public function getTemplateByKey(string $key): ?array
	{
		$settings = $this->getSettings();
		$templates = $settings['templates'];

		if (!is_array($templates) || trim($key) === '') {
			return NULL;
		}

		foreach ($templates as $template) {
			if (!is_array($template)) {
				continue;
			}

			if (($template['key'] ?? '') === $key) {
				return $template;
			}
		}

		return NULL;
	}

	/**
	 * Sends a WhatsApp template message using an internal template key.
	 *
	 * @param string $template_key
	 *   Internal template key configured in Drupal.
	 * @param string $to
	 *   Destination WhatsApp number. Accepts +573001112233, 573001112233 or whatsapp:+573001112233.
	 * @param array $variables
	 *   Template variables. Example: ['1' => 'Juan', '2' => 'SOL-001'].
	 *
	 * @return array
	 *   Normalized response.
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when required data is missing.
	 */
	public function sendTemplateByKey(string $template_key, string $to, array $variables = []): array
	{
		$template = $this->getTemplateByKey($template_key);

		if (!$template) {
			throw new \InvalidArgumentException(sprintf('No existe la plantilla Twilio con clave: %s', $template_key));
		}

		$content_sid = trim((string) ($template['content_sid'] ?? ''));

		if ($content_sid === '') {
			throw new \InvalidArgumentException(sprintf('La plantilla Twilio "%s" no tiene Content SID configurado.', $template_key));
		}

		return $this->sendTemplate($content_sid, $to, $variables);
	}

	/**
	 * Sends a WhatsApp template message using a Content SID directly.
	 *
	 * @param string $content_sid
	 *   Twilio Content SID. Must start with HX.
	 * @param string $to
	 *   Destination WhatsApp number.
	 * @param array $variables
	 *   Template variables.
	 *
	 * @return array
	 *   Normalized response.
	 */
	public function sendTemplate(string $content_sid, string $to, array $variables = []): array
	{
		$settings = $this->getSettings();
		$this->validateBaseConfiguration($settings);

		$content_sid = trim($content_sid);

		if ($content_sid === '') {
			throw new \InvalidArgumentException('El Content SID de Twilio es obligatorio.');
		}

		if (strpos($content_sid, 'HX') !== 0) {
			throw new \InvalidArgumentException('El Content SID de Twilio debe iniciar por HX.');
		}

		$to = $this->normalizeWhatsappNumber($to);

		if ($to === '') {
			throw new \InvalidArgumentException('El número destino de WhatsApp es obligatorio.');
		}

		$endpoint = sprintf(
			'%s/2010-04-01/Accounts/%s/Messages.json',
			$settings['api_base_url'],
			$settings['account_sid']
		);

		$form_params = [
			'From' => $settings['whatsapp_from'],
			'To' => $to,
			'ContentSid' => $content_sid,
		];

		if (!empty($variables)) {
			$form_params['ContentVariables'] = json_encode($this->normalizeVariables($variables), JSON_UNESCAPED_UNICODE);
		}

		$response = $this->httpClient->request('POST', $endpoint, [
			'auth' => [
				$settings['account_sid'],
				$settings['auth_token'],
			],
			'form_params' => $form_params,
			'timeout' => 30,
			'connect_timeout' => 10,
			'http_errors' => FALSE,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		$status_code = $response->getStatusCode();
		$body = (string) $response->getBody();
		$decoded = json_decode($body, TRUE);

		if ($status_code < 200 || $status_code >= 300) {
			$this->logger->error(
				'Twilio WhatsApp template HTTP error. Status: @status Response: @response',
				[
					'@status' => $status_code,
					'@response' => $body,
				]
			);

			return [
				'success' => FALSE,
				'status_code' => $status_code,
				'twilio_response' => $decoded,
			];
		}

		if (is_array($decoded) && !empty($decoded['error_code'])) {
			$this->logger->error(
				'Twilio WhatsApp template error. Error code: @code Response: @response',
				[
					'@code' => $decoded['error_code'],
					'@response' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => FALSE,
				'status_code' => $status_code,
				'twilio_response' => $decoded,
			];
		}

		return [
			'success' => TRUE,
			'status_code' => $status_code,
			'twilio_response' => $decoded,
		];
	}

	/**
	 * Sends a free WhatsApp body message.
	 *
	 * This only works inside the WhatsApp customer service window.
	 *
	 * @param string $to
	 *   Destination WhatsApp number.
	 * @param string $message
	 *   Message body.
	 *
	 * @return array
	 *   Normalized response.
	 */
	public function sendBody(string $to, string $message): array
	{
		$settings = $this->getSettings();
		$this->validateBaseConfiguration($settings);

		$to = $this->normalizeWhatsappNumber($to);
		$message = trim($message);

		if ($to === '') {
			throw new \InvalidArgumentException('El número destino de WhatsApp es obligatorio.');
		}

		if ($message === '') {
			throw new \InvalidArgumentException('El mensaje de WhatsApp es obligatorio.');
		}

		$endpoint = sprintf(
			'%s/2010-04-01/Accounts/%s/Messages.json',
			$settings['api_base_url'],
			$settings['account_sid']
		);

		$response = $this->httpClient->request('POST', $endpoint, [
			'auth' => [
				$settings['account_sid'],
				$settings['auth_token'],
			],
			'form_params' => [
				'From' => $settings['whatsapp_from'],
				'To' => $to,
				'Body' => $message,
			],
			'timeout' => 30,
			'connect_timeout' => 10,
			'http_errors' => FALSE,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		$status_code = $response->getStatusCode();
		$body = (string) $response->getBody();
		$decoded = json_decode($body, TRUE);

		if ($status_code < 200 || $status_code >= 300) {
			$this->logger->error(
				'Twilio WhatsApp body HTTP error. Status: @status Response: @response',
				[
					'@status' => $status_code,
					'@response' => $body,
				]
			);

			return [
				'success' => FALSE,
				'status_code' => $status_code,
				'twilio_response' => $decoded,
			];
		}

		if (is_array($decoded) && !empty($decoded['error_code'])) {
			$this->logger->error(
				'Twilio WhatsApp body error. Error code: @code Response: @response',
				[
					'@code' => $decoded['error_code'],
					'@response' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
				]
			);

			return [
				'success' => FALSE,
				'status_code' => $status_code,
				'twilio_response' => $decoded,
			];
		}

		return [
			'success' => TRUE,
			'status_code' => $status_code,
			'twilio_response' => $decoded,
		];
	}

	/**
	 * Normalizes a WhatsApp number for Twilio.
	 *
	 * @param string $number
	 *   Original number.
	 *
	 * @return string
	 *   Normalized WhatsApp number.
	 */
	protected function normalizeWhatsappNumber(string $number): string
	{
		$number = trim($number);

		if ($number === '') {
			return '';
		}

		if (strpos($number, 'whatsapp:+') === 0) {
			return $number;
		}

		if (strpos($number, '+') === 0) {
			return 'whatsapp:' . $number;
		}

		$clean = preg_replace('/[^0-9]/', '', $number);

		if ($clean === '') {
			return '';
		}

		return 'whatsapp:+' . $clean;
	}

	/**
	 * Normalizes ContentVariables keys and values.
	 *
	 * @param array $variables
	 *   Original variables.
	 *
	 * @return array
	 *   Normalized variables.
	 */
	protected function normalizeVariables(array $variables): array
	{
		$normalized = [];

		foreach ($variables as $key => $value) {
			$key = (string) $key;

			if ($key === '') {
				continue;
			}

			if (is_scalar($value) || $value === NULL) {
				$normalized[$key] = (string) $value;
			}
		}

		return $normalized;
	}
}
