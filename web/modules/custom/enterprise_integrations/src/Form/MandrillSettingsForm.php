<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class MandrillSettingsForm extends ConfigFormBase
{

  protected function getEditableConfigNames()
  {
    return ['enterprise_integrations.settings'];
  }

  public function getFormId()
  {
    return 'enterprise_integrations_mandrill_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('enterprise_integrations.settings');

    // Inicializa cantidad de grupos en estado del formulario.
    if ($form_state->get('message_groups_count') === NULL) {
      $form_state->set('message_groups_count', 1);
    }

    $groups_count = (int) $form_state->get('message_groups_count');

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
      '#title' => $this->t('Asunto por defecto'),
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
      '#description' => $this->t('Usa tokens como {{subject}} y {{message}} dentro de la plantilla base. Esta plantilla será la misma para todos los correos.'),
      '#default_value' => $config->get('mandrill.default_html_template'),
      '#rows' => 10,
    ];

    $form['mandrill']['message_groups_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'message-groups-wrapper',
      ],
    ];

    $form['mandrill']['message_groups_wrapper']['message_groups_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Mensajes configurables'),
      '#markup' => '<p>Agrega los grupos de mensaje que necesites. Cada grupo tendrá asunto y mensaje.</p>',
    ];

    $form['mandrill']['message_groups_wrapper']['message_groups'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $groups_count; $i++) {
      $form['mandrill']['message_groups_wrapper']['message_groups'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Grupo de mensaje @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];

      $form['mandrill']['message_groups_wrapper']['message_groups'][$i]['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $form_state->getValue(['message_groups', $i, 'subject']) ?? '',
      ];

      $form['mandrill']['message_groups_wrapper']['message_groups'][$i]['message'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Mensaje'),
        '#default_value' => $form_state->getValue(['message_groups', $i, 'message']) ?? '',
        '#rows' => 6,
      ];

      if ($groups_count > 1) {
        $form['mandrill']['message_groups_wrapper']['message_groups'][$i]['remove_group'] = [
          '#type' => 'submit',
          '#value' => $this->t('Eliminar este grupo'),
          '#name' => 'remove_group_' . $i,
          '#submit' => ['::removeMessageGroupSubmit'],
          '#ajax' => [
            'callback' => '::messageGroupsAjaxCallback',
            'wrapper' => 'message-groups-wrapper',
          ],
          '#limit_validation_errors' => [],
          '#group_index' => $i,
        ];
      }
    }

    $form['mandrill']['message_groups_wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['mandrill']['message_groups_wrapper']['actions']['add_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Agregar grupo'),
      '#submit' => ['::addMessageGroupSubmit'],
      '#ajax' => [
        'callback' => '::messageGroupsAjaxCallback',
        'wrapper' => 'message-groups-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function messageGroupsAjaxCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['mandrill']['message_groups_wrapper'];
  }

  public function addMessageGroupSubmit(array &$form, FormStateInterface $form_state)
  {
    $count = (int) $form_state->get('message_groups_count');
    $form_state->set('message_groups_count', $count + 1);
    $form_state->setRebuild(TRUE);
  }

  public function removeMessageGroupSubmit(array &$form, FormStateInterface $form_state)
  {
    $trigger = $form_state->getTriggeringElement();
    $remove_index = isset($trigger['#group_index']) ? (int) $trigger['#group_index'] : NULL;

    $count = (int) $form_state->get('message_groups_count');
    $values = $form_state->getValue('message_groups') ?? [];

    if ($remove_index !== NULL && isset($values[$remove_index])) {
      unset($values[$remove_index]);
      $values = array_values($values);
      $form_state->setValue('message_groups', $values);
    }

    $new_count = max(1, $count - 1);
    $form_state->set('message_groups_count', $new_count);
    $form_state->setRebuild(TRUE);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
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

    // Guardar logo.
    $logo = $form_state->getValue('mail_logo');
    if (!empty($logo[0])) {
      $file = File::load($logo[0]);
      $file->setPermanent();
      $file->save();
      $config->set('mail_logo', $logo);
    }

    // Guardar banner.
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
