<?php

declare(strict_types=1);

namespace Drupal\enterprise_integrations\Controller\tests;

use Drupal\Core\Controller\ControllerBase;
use Drupal\enterprise_integrations\Service\TwilioWhatsAppService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Temporary controller to test Twilio WhatsApp integration.
 */
final class TwilioTestController extends ControllerBase
{

	/**
	 * Twilio WhatsApp service.
	 *
	 * @var \Drupal\enterprise_integrations\Service\TwilioWhatsAppService
	 */
	protected TwilioWhatsAppService $twilioWhatsApp;

	/**
	 * Constructs the controller.
	 */
	public function __construct(TwilioWhatsAppService $twilio_whatsapp)
	{
		$this->twilioWhatsApp = $twilio_whatsapp;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('enterprise_integrations.twilio_whatsapp')
		);
	}

	/**
	 * Sends a test WhatsApp template message.
	 *
	 * @return array
	 *   Render array.
	 */
	public function sendTemplateTest(): array
	{
		try {
			$result = $this->twilioWhatsApp->sendTemplateByKey(
				'twilio_template_1',
				'+573215574712',
				[
					'1' => 'Virgilio Manuel',
					'2' => 'SOL-2026-001',
					'3' => 'Pendiente firma de documentos',
				]
			);

			return [
				'#type' => 'pre',
				'#markup' => print_r($result, TRUE),
			];
		} catch (\Throwable $e) {
			return [
				'#type' => 'pre',
				'#markup' => 'Error Twilio: ' . $e->getMessage(),
			];
		}
	}
}
