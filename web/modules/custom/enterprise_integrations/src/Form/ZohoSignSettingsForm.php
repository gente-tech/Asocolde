<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuración de Zoho Sign.
 */
class ZohoSignSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['enterprise_integrations.zoho_sign_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'enterprise_integrations_zoho_sign_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('enterprise_integrations.zoho_sign_settings');

    $form['oauth'] = [
      '#type' => 'details',
      '#title' => $this->t('OAuth'),
      '#open' => TRUE,
    ];

    $form['oauth']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id') ?? '',
      '#required' => TRUE,
    ];

    $form['oauth']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret') ?? '',
      '#required' => TRUE,
    ];

    $form['oauth']['refresh_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Refresh Token'),
      '#default_value' => $config->get('refresh_token') ?? '',
      '#required' => TRUE,
    ];

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API'),
      '#open' => TRUE,
    ];

    $form['api']['accounts_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accounts Domain'),
      '#default_value' => $config->get('accounts_domain') ?? 'https://accounts.zoho.com',
      '#required' => TRUE,
      '#description' => $this->t('Ejemplo: https://accounts.zoho.com'),
    ];

    $form['api']['api_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Domain'),
      '#default_value' => $config->get('api_domain') ?? 'https://sign.zoho.com',
      '#required' => TRUE,
      '#description' => $this->t('Ejemplo: https://sign.zoho.com'),
    ];

    $form['api']['oauth_api_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OAuth API Domain'),
      '#default_value' => $config->get('oauth_api_domain') ?? 'https://www.zohoapis.com',
      '#required' => TRUE,
      '#description' => $this->t('Ejemplo: https://www.zohoapis.com'),
    ];

    $form['template'] = [
      '#type' => 'details',
      '#title' => $this->t('Template'),
      '#open' => TRUE,
    ];

    $form['template']['template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Template ID'),
      '#default_value' => $config->get('template_id') ?? '',
      '#required' => TRUE,
    ];

    $form['integration'] = [
      '#type' => 'details',
      '#title' => $this->t('Integración'),
      '#open' => TRUE,
    ];

    $form['integration']['webhook_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook URL'),
      '#default_value' => $config->get('webhook_url') ?? '',
      '#required' => FALSE,
    ];

    $form['integration']['redirect_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URL'),
      '#default_value' => $config->get('redirect_url') ?? '',
      '#required' => FALSE,
    ];

    $form['integration']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#default_value' => $config->get('host') ?? '',
      '#required' => FALSE,
      '#description' => $this->t('Ejemplo: https://asocolderma.org.co'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $url_fields = [
      'accounts_domain',
      'api_domain',
      'oauth_api_domain',
      'webhook_url',
      'redirect_url',
      'host',
    ];

    foreach ($url_fields as $field_name) {
      $value = trim((string) $form_state->getValue($field_name));
      if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName($field_name, $this->t('El campo %field debe ser una URL válida.', [
          '%field' => $field_name,
        ]));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()
      ->getEditable('enterprise_integrations.zoho_sign_settings')
      ->set('client_id', trim((string) $form_state->getValue('client_id')))
      ->set('client_secret', trim((string) $form_state->getValue('client_secret')))
      ->set('refresh_token', trim((string) $form_state->getValue('refresh_token')))
      ->set('accounts_domain', trim((string) $form_state->getValue('accounts_domain')))
      ->set('api_domain', trim((string) $form_state->getValue('api_domain')))
      ->set('oauth_api_domain', trim((string) $form_state->getValue('oauth_api_domain')))
      ->set('template_id', trim((string) $form_state->getValue('template_id')))
      ->set('webhook_url', trim((string) $form_state->getValue('webhook_url')))
      ->set('redirect_url', trim((string) $form_state->getValue('redirect_url')))
      ->set('host', trim((string) $form_state->getValue('host')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}