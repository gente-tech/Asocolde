<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class MandrillSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['enterprise_integrations.settings'];
  }

  public function getFormId() {
    return 'enterprise_integrations_mandrill_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('enterprise_integrations.settings');

    $form['mandrill'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuración de Servicio de envío de correos'),
      '#open' => TRUE,
    ];

    $form['mandrill']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('mandrill.api_key'),
      '#required' => TRUE,
    ];

    $form['mandrill']['from_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email de Origen'),
      '#default_value' => $config->get('mandrill.from_email'),
      '#required' => TRUE,
    ];

    $form['mandrill']['from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre (Desde)'),
      '#default_value' => $config->get('mandrill.from_name'),
      '#required' => TRUE,
    ];

    $form['mandrill']['default_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asunto'),
      '#default_value' => $config->get('mandrill.default_subject'),
    ];

    $form['mail_logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Logo del correo'),
      '#upload_location' => 'public://dermau_mail/',
      '#default_value' => $config->get('mail_logo'),
    ];

    $form['mail_banner'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Banner del correo'),
      '#upload_location' => 'public://dermau_mail/',
      '#default_value' => $config->get('mail_banner'),
    ];

    $form['mandrill']['default_html_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('HTML Template'),
      '#description' => $this->t('Use tokens like {{nombre}}, {{email}}, {{telefono}}, {{mensaje}}'),
      '#default_value' => $config->get('mandrill.default_html_template'),
      '#rows' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('enterprise_integrations.settings');

    $config
      ->set('mandrill.api_key', $form_state->getValue('api_key'))
      ->set('mandrill.from_email', $form_state->getValue('from_email'))
      ->set('mandrill.from_name', $form_state->getValue('from_name'))
      ->set('mandrill.default_subject', $form_state->getValue('default_subject'))
      ->set('mandrill.default_html_template', $form_state->getValue('default_html_template'))
      ->set('mandrill.internal_copy_enabled', $form_state->getValue('internal_copy_enabled'))
      ->set('mandrill.internal_copy_email', $form_state->getValue('internal_copy_email'))
      ->set('mandrill.internal_copy_name', $form_state->getValue('internal_copy_name'));

    // Guardar logo
    $logo = $form_state->getValue('mail_logo');
    if (!empty($logo[0])) {
      $file = File::load($logo[0]);
      $file->setPermanent();
      $file->save();
      $config->set('mail_logo', $logo);
    }

    // Guardar banner
    $banner = $form_state->getValue('mail_banner');
    if (!empty($banner[0])) {
      $file = File::load($banner[0]);
      $file->setPermanent();
      $file->save();
      $config->set('mail_banner', $banner);
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}