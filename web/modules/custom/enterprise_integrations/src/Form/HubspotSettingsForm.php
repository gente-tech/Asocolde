<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HubspotSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'enterprise_integrations_hubspot_settings';
  }

  protected function getEditableConfigNames() {
    return ['enterprise_integrations.hubspot_settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('enterprise_integrations.hubspot_settings');

    $form['hubspot_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HubSpot Access Token'),
      '#default_value' => $config->get('hubspot_token'),
      '#required' => TRUE,
    ];

    $form['hubspot_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HubSpot API URL'),
      '#default_value' => $config->get('hubspot_api_url') ?: 'https://api.hubapi.com',
      '#required' => TRUE,
    ];

    $form['hubspot_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HubSpot Integration'),
      '#default_value' => $config->get('hubspot_enabled'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('enterprise_integrations.hubspot_settings')
      ->set('hubspot_token', $form_state->getValue('hubspot_token'))
      ->set('hubspot_api_url', $form_state->getValue('hubspot_api_url'))
      ->set('hubspot_enabled', $form_state->getValue('hubspot_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}