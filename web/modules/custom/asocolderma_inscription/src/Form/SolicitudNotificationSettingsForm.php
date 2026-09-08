<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudNotificationPhaseCatalog;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for solicitud notification mappings.
 *
 * This form does not store Mandrill or Twilio credentials.
 * It only maps each business phase of the inscription workflow
 * to previously configured Mandrill and Twilio template keys.
 */
final class SolicitudNotificationSettingsForm extends ConfigFormBase
{

	/**
	 * Catálogo único de fases notificables.
	 */
	private SolicitudNotificationPhaseCatalog $phaseCatalog;

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): static
	{
		/** @var static $instance */
		$instance = parent::create($container);

		$instance->phaseCatalog = $container->get(
			'asocolderma_inscription.solicitud_notification_phase_catalog'
		);

		return $instance;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames(): array
	{
		return [
			'asocolderma_inscription.notification_settings',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_inscription_notification_settings_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$config = $this->config('asocolderma_inscription.notification_settings');

		$mandrill_options = $this->getMandrillTemplateOptions();
		$twilio_options = $this->getTwilioTemplateOptions();

		$form['intro'] = [
			'#type' => 'item',
			'#markup' => '<p>Configure qué plantilla de correo Mandrill y qué plantilla de WhatsApp Twilio se usará en cada fase del flujo de solicitud de ingreso.</p>',
		];

		$form['warnings'] = [
			'#type' => 'container',
		];

		if (count($mandrill_options) === 1) {
			$form['warnings']['mandrill_empty'] = [
				'#type' => 'item',
				'#markup' => '<div class="messages messages--warning">No hay plantillas Mandrill configuradas. Primero configure las plantillas en Asocolderma > Integraciones > Mandrill.</div>',
			];
		}

		if (count($twilio_options) === 1) {
			$form['warnings']['twilio_empty'] = [
				'#type' => 'item',
				'#markup' => '<div class="messages messages--warning">No hay plantillas Twilio configuradas. Primero configure las plantillas en Asocolderma > Integraciones > Twilio > Templates.</div>',
			];
		}

		$form['phases'] = [
			'#type' => 'details',
			'#title' => $this->t('Notificaciones por fase de solicitud'),
			'#open' => TRUE,
			'#tree' => TRUE,
		];

		foreach ($this->phaseCatalog->all() as $phase_key => $phase) {
			$form['phases'][$phase_key] = [
				'#type' => 'details',
				'#title' => $phase['label'],
				'#open' => FALSE,
			];

			$form['phases'][$phase_key]['description'] = [
				'#type' => 'item',
				'#markup' => '<p>' . $phase['description'] . '</p>',
			];

			$form['phases'][$phase_key]['mandrill_template_key'] = [
				'#type' => 'select',
				'#title' => $this->t('Plantilla Mandrill'),
				'#description' => $this->t('Seleccione la configuración de correo que se enviará en esta fase.'),
				'#options' => $mandrill_options,
				'#default_value' => $config->get("phases.$phase_key.mandrill_template_key") ?? '',
			];

			$form['phases'][$phase_key]['twilio_template_key'] = [
				'#type' => 'select',
				'#title' => $this->t('Plantilla Twilio WhatsApp'),
				'#description' => $this->t('Seleccione la plantilla de WhatsApp que se enviará en esta fase.'),
				'#options' => $twilio_options,
				'#default_value' => $config->get("phases.$phase_key.twilio_template_key") ?? '',
			];
		}

		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$config = $this->configFactory->getEditable(
			'asocolderma_inscription.notification_settings'
		);

		$values = $form_state->getValue('phases') ?? [];
		$clean_phases = [];

		foreach ($this->phaseCatalog->all() as $phase_key => $phase) {
			$phase_values = $values[$phase_key] ?? [];

			$clean_phases[$phase_key] = [
				'label' => $phase['label'],
				'mandrill_template_key' => trim(
					(string) ($phase_values['mandrill_template_key'] ?? '')
				),
				'twilio_template_key' => trim(
					(string) ($phase_values['twilio_template_key'] ?? '')
				),
			];
		}

		$config
			->set('phases', $clean_phases)
			->save();

		parent::submitForm($form, $form_state);
	}

	/**
	 * Returns Mandrill template options from enterprise_integrations settings.
	 *
	 * @return array
	 *   Select options.
	 */
	private function getMandrillTemplateOptions(): array
	{
		$options = [
			'' => $this->t('- No enviar correo -'),
		];

		$enterprise_config = $this->configFactory->get(
			'enterprise_integrations.settings'
		);

		$groups = $enterprise_config->get('mandrill.message_groups') ?? [];

		if (!is_array($groups)) {
			return $options;
		}

		foreach ($groups as $group) {
			if (!is_array($group)) {
				continue;
			}

			$key = trim((string) ($group['key'] ?? ''));
			$slug = trim((string) ($group['mandrill_template_slug'] ?? ''));

			if ($key === '' || $slug === '') {
				continue;
			}

			$options[$key] = $key . ' - ' . $slug;
		}

		return $options;
	}

	/**
	 * Returns Twilio template options from enterprise_integrations settings.
	 *
	 * @return array
	 *   Select options.
	 */
	private function getTwilioTemplateOptions(): array
	{
		$options = [
			'' => $this->t('- No enviar WhatsApp -'),
		];

		$enterprise_config = $this->configFactory->get(
			'enterprise_integrations.settings'
		);

		$templates = $enterprise_config->get('twilio.templates') ?? [];

		if (!is_array($templates)) {
			return $options;
		}

		foreach ($templates as $template) {
			if (!is_array($template)) {
				continue;
			}

			$key = trim((string) ($template['key'] ?? ''));
			$label = trim((string) ($template['label'] ?? ''));

			if ($key === '') {
				continue;
			}

			$option_label = $key;

			if ($label !== '') {
				$option_label .= ' - ' . $label;
			}

			$options[$key] = $option_label;
		}

		return $options;
	}
}
