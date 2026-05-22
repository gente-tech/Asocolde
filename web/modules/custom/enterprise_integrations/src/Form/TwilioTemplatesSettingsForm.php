<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Twilio WhatsApp templates.
 */
class TwilioTemplatesSettingsForm extends ConfigFormBase
{

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames()
	{
		return ['enterprise_integrations.settings'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return 'enterprise_integrations_twilio_templates_settings_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{
		$config = $this->config('enterprise_integrations.settings');

		if ($form_state->get('twilio_templates_last_id') === NULL) {
			$last_id = (int) ($config->get('twilio.templates_last_id') ?? 0);
			$form_state->set('twilio_templates_last_id', $last_id);
		}

		$saved_templates = $config->get('twilio.templates') ?? [];

		if ($form_state->get('twilio_templates_count') === NULL) {
			$initial_count = !empty($saved_templates) ? count($saved_templates) : 1;
			$form_state->set('twilio_templates_count', $initial_count);
		}

		$templates_count = (int) $form_state->get('twilio_templates_count');

		$form['twilio'] = [
			'#type' => 'details',
			'#title' => $this->t('Configuración de Twilio'),
			'#open' => TRUE,
		];

		$form['twilio']['account_sid'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Account SID'),
			'#description' => $this->t('Account SID de Twilio. Debe iniciar por AC.'),
			'#default_value' => $config->get('twilio.account_sid'),
			'#required' => TRUE,
		];

		$form['twilio']['auth_token'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Auth Token'),
			'#description' => $this->t('Auth Token principal de Twilio.'),
			'#default_value' => $config->get('twilio.auth_token'),
			'#required' => TRUE,
		];

		$form['twilio']['api_base_url'] = [
			'#type' => 'textfield',
			'#title' => $this->t('API Base URL'),
			'#description' => $this->t('URL base del API de Twilio. Ejemplo: https://api.twilio.com'),
			'#default_value' => $config->get('twilio.api_base_url'),
			'#required' => TRUE,
		];

		$form['twilio']['whatsapp_from'] = [
			'#type' => 'textfield',
			'#title' => $this->t('WhatsApp From'),
			'#description' => $this->t('Número emisor de WhatsApp configurado en Twilio. Ejemplo: whatsapp:+573246163746'),
			'#default_value' => $config->get('twilio.whatsapp_from'),
			'#required' => TRUE,
		];

		$form['twilio']['templates_wrapper'] = [
			'#type' => 'container',
			'#attributes' => [
				'id' => 'twilio-templates-wrapper',
			],
		];

		$form['twilio']['templates_wrapper']['templates_title'] = [
			'#type' => 'item',
			'#title' => $this->t('Plantillas configurables'),
			'#markup' => '<p>Agrega las plantillas de Twilio que necesites. Cada plantilla tendrá una clave interna y un Content SID.</p>',
		];

		$form['twilio']['templates_wrapper']['templates'] = [
			'#type' => 'container',
			'#tree' => TRUE,
		];

		for ($i = 0; $i < $templates_count; $i++) {
			$template_default = $saved_templates[$i] ?? [
				'key' => '',
				'label' => '',
				'content_sid' => '',
			];

			$form['twilio']['templates_wrapper']['templates'][$i] = [
				'#type' => 'details',
				'#title' => $this->t('Plantilla Twilio @num', ['@num' => $i + 1]),
				'#open' => TRUE,
			];

			$form['twilio']['templates_wrapper']['templates'][$i]['key'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Clave interna'),
				'#description' => $this->t('Identificador interno para llamar esta plantilla desde código. Si queda vacío, se generará automáticamente.'),
				'#default_value' => $template_default['key'] ?? '',
				'#attributes' => [
					'readonly' => 'readonly',
				],
			];

			$form['twilio']['templates_wrapper']['templates'][$i]['label'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Nombre descriptivo'),
				'#description' => $this->t('Nombre administrativo para identificar la plantilla.'),
				'#default_value' => $template_default['label'] ?? '',
				'#required' => TRUE,
			];

			$form['twilio']['templates_wrapper']['templates'][$i]['content_sid'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Content SID / Template SID'),
				'#description' => $this->t('SID de la plantilla creada en Twilio. Debe iniciar por HX.'),
				'#default_value' => $template_default['content_sid'] ?? '',
				'#required' => TRUE,
			];

			if ($templates_count > 1) {
				$form['twilio']['templates_wrapper']['templates'][$i]['remove_template'] = [
					'#type' => 'submit',
					'#value' => $this->t('Eliminar plantilla'),
					'#name' => 'remove_twilio_template_' . $i,
					'#submit' => ['::removeTemplateSubmit'],
					'#ajax' => [
						'callback' => '::templatesAjaxCallback',
						'wrapper' => 'twilio-templates-wrapper',
					],
					'#limit_validation_errors' => [],
					'#template_index' => $i,
				];
			}
		}

		$form['twilio']['templates_wrapper']['actions'] = [
			'#type' => 'actions',
		];

		$form['twilio']['templates_wrapper']['actions']['add_template'] = [
			'#type' => 'submit',
			'#value' => $this->t('Agregar plantilla'),
			'#submit' => ['::addTemplateSubmit'],
			'#ajax' => [
				'callback' => '::templatesAjaxCallback',
				'wrapper' => 'twilio-templates-wrapper',
			],
			'#limit_validation_errors' => [],
		];

		return parent::buildForm($form, $form_state);
	}

	/**
	 * AJAX callback for templates wrapper.
	 */
	public function templatesAjaxCallback(array &$form, FormStateInterface $form_state)
	{
		return $form['twilio']['templates_wrapper'];
	}

	/**
	 * Adds a new template group.
	 */
	public function addTemplateSubmit(array &$form, FormStateInterface $form_state)
	{
		$count = (int) $form_state->get('twilio_templates_count');
		$last_id = (int) $form_state->get('twilio_templates_last_id');

		$values = $form_state->getValue('templates') ?? [];

		$new_id = $last_id + 1;

		$values[] = [
			'key' => 'twilio_template_' . $new_id,
			'label' => '',
			'content_sid' => '',
		];

		$form_state->setValue('templates', $values);
		$form_state->set('twilio_templates_count', $count + 1);
		$form_state->set('twilio_templates_last_id', $new_id);
		$form_state->setRebuild(TRUE);
	}

	/**
	 * Removes a template group.
	 */
	public function removeTemplateSubmit(array &$form, FormStateInterface $form_state)
	{
		$trigger = $form_state->getTriggeringElement();
		$remove_index = isset($trigger['#template_index']) ? (int) $trigger['#template_index'] : NULL;

		$values = $form_state->getValue('templates') ?? [];

		if ($remove_index !== NULL && isset($values[$remove_index])) {
			unset($values[$remove_index]);
			$values = array_values($values);
			$form_state->setValue('templates', $values);
		}

		$new_count = max(1, count($values));
		$form_state->set('twilio_templates_count', $new_count);
		$form_state->setRebuild(TRUE);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state)
	{
		parent::validateForm($form, $form_state);

		$account_sid = trim((string) $form_state->getValue('account_sid'));
		$api_base_url = trim((string) $form_state->getValue('api_base_url'));
		$whatsapp_from = trim((string) $form_state->getValue('whatsapp_from'));
		$templates = $form_state->getValue('templates') ?? [];

		if ($account_sid !== '' && strpos($account_sid, 'AC') !== 0) {
			$form_state->setErrorByName('account_sid', $this->t('El Account SID debe iniciar por AC.'));
		}

		if ($api_base_url !== '' && !filter_var($api_base_url, FILTER_VALIDATE_URL)) {
			$form_state->setErrorByName('api_base_url', $this->t('La API Base URL debe ser una URL válida.'));
		}

		if ($whatsapp_from !== '' && strpos($whatsapp_from, 'whatsapp:+') !== 0) {
			$form_state->setErrorByName('whatsapp_from', $this->t('El campo WhatsApp From debe iniciar por whatsapp:+'));
		}

		foreach ($templates as $index => $template) {
			if (!is_array($template)) {
				continue;
			}

			$content_sid = trim((string) ($template['content_sid'] ?? ''));

			if ($content_sid !== '' && strpos($content_sid, 'HX') !== 0) {
				$form_state->setErrorByName(
					'templates][' . $index . '][content_sid',
					$this->t('El Content SID de la plantilla @num debe iniciar por HX.', ['@num' => $index + 1])
				);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		$config = $this->configFactory->getEditable('enterprise_integrations.settings');

		$config
			->set('twilio.account_sid', trim((string) $form_state->getValue('account_sid')))
			->set('twilio.auth_token', trim((string) $form_state->getValue('auth_token')))
			->set('twilio.api_base_url', rtrim(trim((string) $form_state->getValue('api_base_url')), '/'))
			->set('twilio.whatsapp_from', trim((string) $form_state->getValue('whatsapp_from')));

		$templates = $form_state->getValue('templates') ?? [];
		$last_id = (int) ($config->get('twilio.templates_last_id') ?? 0);

		$clean_templates = [];

		foreach ($templates as $template) {
			if (!is_array($template)) {
				continue;
			}

			$label = trim((string) ($template['label'] ?? ''));
			$content_sid = trim((string) ($template['content_sid'] ?? ''));
			$key = trim((string) ($template['key'] ?? ''));

			if ($label === '' || $content_sid === '') {
				continue;
			}

			if ($key === '') {
				$last_id++;
				$key = 'twilio_template_' . $last_id;
			}

			$clean_templates[] = [
				'key' => $key,
				'label' => $label,
				'content_sid' => $content_sid,
			];
		}

		$config
			->set('twilio.templates', $clean_templates)
			->set('twilio.templates_last_id', $last_id);

		$config->save();

		parent::submitForm($form, $form_state);
	}
}
