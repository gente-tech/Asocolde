<?php

namespace Drupal\enterprise_integrations\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    if ($form_state->get('message_groups_last_id') === NULL) {
      $last_id = (int) ($config->get('mandrill.message_groups_last_id') ?? 0);
      $form_state->set('message_groups_last_id', $last_id);
    }

    $saved_groups = $config->get('mandrill.message_groups') ?? [];

    if ($form_state->get('message_groups_count') === NULL) {
      $initial_count = !empty($saved_groups) ? count($saved_groups) : 1;
      $form_state->set('message_groups_count', $initial_count);
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

    $form['mandrill']['message_groups_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'message-groups-wrapper',
      ],
    ];

    $form['mandrill']['message_groups_wrapper']['message_groups_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Plantillas configurables'),
      '#markup' => '<p>Agrega las plantillas Mandrill que necesites. Cada grupo tendrá una clave interna y un slug de plantilla.</p>',
    ];

    $form['mandrill']['message_groups_wrapper']['message_groups'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    for ($i = 0; $i < $groups_count; $i++) {
      $form['mandrill']['message_groups_wrapper']['message_groups'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Plantilla Mandrill @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];

      $group_default = $saved_groups[$i] ?? [
        'key' => '',
        'mandrill_template_slug' => '',
      ];

      $form['mandrill']['message_groups_wrapper']['message_groups'][$i]['key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Clave'),
        '#default_value' => $group_default['key'] ?? '',
        '#attributes' => ['readonly' => 'readonly'],
      ];

      $form['mandrill']['message_groups_wrapper']['message_groups'][$i]['mandrill_template_slug'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Slug de plantilla Mandrill'),
        '#description' => $this->t('Nombre exacto de la plantilla creada en Mandrill. Ejemplo: dermau-preinscripcion-programa'),
        '#default_value' => $group_default['mandrill_template_slug'] ?? '',
        '#required' => TRUE,
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
    $last_id = (int) $form_state->get('message_groups_last_id');

    $values = $form_state->getValue('message_groups') ?? [];

    $new_id = $last_id + 1;
    $values[] = [
      'key' => 'mail_text_' . $new_id,
      'mandrill_template_slug' => '',
    ];

    $form_state->setValue('message_groups', $values);
    $form_state->set('message_groups_count', $count + 1);
    $form_state->set('message_groups_last_id', $new_id);
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

    $new_count = max(1, count($values));
    $form_state->set('message_groups_count', $new_count);
    $form_state->setRebuild(TRUE);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->configFactory->getEditable('enterprise_integrations.settings');

    $config
      ->set('mandrill.api_key', $form_state->getValue('api_key'));

    $message_groups = $form_state->getValue('message_groups') ?? [];
    $last_id = (int) ($config->get('mandrill.message_groups_last_id') ?? 0);

    $clean_groups = [];

    foreach ($message_groups as $group) {
      if (!is_array($group)) {
        continue;
      }

      $template_slug = trim((string) ($group['mandrill_template_slug'] ?? ''));
      $key = trim((string) ($group['key'] ?? ''));

      if ($template_slug === '') {
        continue;
      }

      if ($key === '') {
        $last_id++;
        $key = 'mail_text_' . $last_id;
      }

      $clean_groups[] = [
        'key' => $key,
        'mandrill_template_slug' => $template_slug,
      ];
    }

    $config
      ->set('mandrill.message_groups', $clean_groups)
      ->set('mandrill.message_groups_last_id', $last_id);

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
