<?php

namespace Drupal\custom_login_2fa\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides settings form for Custom Login 2FA.
 */
final class CustomLogin2faSettingsForm extends ConfigFormBase
{

  /**
   * The role storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $roleStorage;

  /**
   * Constructs a new CustomLogin2faSettingsForm.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory);
    $this->roleStorage = $entity_type_manager->getStorage('user_role');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'custom_login_2fa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return [
      'custom_login_2fa.settings',
    ];
  }

  /**
   * Builds role options excluding anonymous.
   */
  private function getRoleOptions(): array
  {
    $options = [];
    $roles = $this->roleStorage->loadMultiple();

    foreach ($roles as $role_id => $role) {
      if ($role_id === 'anonymous') {
        continue;
      }

      $options[$role_id] = $role->label() . ' (' . $role_id . ')';
    }

    asort($options);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('custom_login_2fa.settings');

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>Configura la autenticación de doble factor por correo para los roles seleccionados. El correo será enviado usando el servicio Mandrill del módulo <strong>enterprise_integrations</strong>.</p>',
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable 2FA login protection'),
      '#default_value' => (bool) $config->get('enabled'),
      '#description' => $this->t('If disabled, all users will use the normal Drupal login flow.'),
    ];

    $form['protected_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Protected roles'),
      '#options' => $this->getRoleOptions(),
      '#default_value' => $config->get('protected_roles') ?: [],
      '#description' => $this->t('Users with at least one selected role must validate a code before completing login.'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['code_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Code settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['code_settings']['code_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Code validity in seconds'),
      '#default_value' => (int) ($config->get('code_ttl') ?: 20),
      '#min' => 10,
      '#max' => 600,
      '#required' => TRUE,
      '#description' => $this->t('For this project use 20 seconds.'),
    ];

    $form['code_settings']['code_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Code length'),
      '#default_value' => (int) ($config->get('code_length') ?: 5),
      '#min' => 5,
      '#max' => 10,
      '#required' => TRUE,
      '#description' => $this->t('For this project use 5 characters. Codes use uppercase letters and numbers without confusing characters.'),
    ];

    $form['code_settings']['max_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum validation attempts'),
      '#default_value' => (int) ($config->get('max_attempts') ?: 3),
      '#min' => 1,
      '#max' => 10,
      '#required' => TRUE,
    ];

    $form['code_settings']['resend_cooldown'] = [
      '#type' => 'number',
      '#title' => $this->t('Resend cooldown in seconds'),
      '#default_value' => (int) ($config->get('resend_cooldown') ?: 20),
      '#min' => 5,
      '#max' => 300,
      '#required' => TRUE,
      '#description' => $this->t('Minimum time the user must wait before requesting another code.'),
    ];

    $form['code_settings']['max_resends'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum resend attempts'),
      '#default_value' => (int) ($config->get('max_resends') ?: 3),
      '#min' => 0,
      '#max' => 10,
      '#required' => TRUE,
      '#description' => $this->t('Maximum number of code resends allowed during a pending login attempt.'),
    ];

    $form['mail_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Mandrill email settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['mail_settings']['mandrill_message_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mandrill message group key'),
      '#default_value' => (string) ($config->get('mandrill_message_key') ?: ''),
      '#maxlength' => 128,
      '#description' => $this->t('Example: mail_text_N. This key must exist in the Mandrill settings of enterprise_integrations.'),
      '#required' => TRUE,
    ];

    $form['mail_settings']['tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Available Mandrill variables'),
      '#open' => FALSE,
    ];

    $form['mail_settings']['tokens']['list'] = [
      '#theme' => 'item_list',
      '#items' => [
        '*|CODE|*',
        '*|TTL|*',
        '*|USER_FULL_NAME|*',
        '*|USER_NAME|*',
        '*|USER_EMAIL|*',
        '*|SITE_NAME|*',
      ],
    ];

    $form['redirect_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Redirect settings'),
      '#open' => TRUE,
    ];

    $form['redirect_settings']['default_redirect_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default redirect path after successful 2FA'),
      '#default_value' => (string) ($config->get('default_redirect_path') ?: '/'),
      '#required' => TRUE,
      '#description' => $this->t('Example: /gestion-data/proveedores'),
    ];

    $role_redirect_paths = $config->get('role_redirect_paths') ?: [];

    $form['redirect_settings']['role_redirect_paths'] = [
      '#type' => 'details',
      '#title' => $this->t('Redirect paths by role'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#description' => $this->t('Optional. If a user has one of these roles, this path will be used. Leave empty to use the default redirect path.'),
    ];

    foreach ($this->getRoleOptions() as $role_id => $role_label) {
      $form['redirect_settings']['role_redirect_paths'][$role_id] = [
        '#type' => 'textfield',
        '#title' => $role_label,
        '#default_value' => (string) ($role_redirect_paths[$role_id] ?? ''),
        '#description' => $this->t('Leave empty to use the default redirect path.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    parent::validateForm($form, $form_state);

    $enabled = (bool) $form_state->getValue('enabled');
    $protected_roles = array_filter($form_state->getValue('protected_roles') ?: []);

    if ($enabled && empty($protected_roles)) {
      $form_state->setErrorByName('protected_roles', $this->t('Select at least one protected role when 2FA is enabled.'));
    }

    $code_ttl = (int) $form_state->getValue('code_ttl');
    if ($code_ttl < 10 || $code_ttl > 600) {
      $form_state->setErrorByName('code_ttl', $this->t('Code validity must be between 10 and 600 seconds.'));
    }

    $code_length = (int) $form_state->getValue('code_length');
    if ($code_length < 5 || $code_length > 10) {
      $form_state->setErrorByName('code_length', $this->t('Code length must be between 5 and 10 characters.'));
    }

    $max_attempts = (int) $form_state->getValue('max_attempts');
    if ($max_attempts < 1 || $max_attempts > 10) {
      $form_state->setErrorByName('max_attempts', $this->t('Maximum attempts must be between 1 and 10.'));
    }

    $resend_cooldown = (int) $form_state->getValue('resend_cooldown');
    if ($resend_cooldown < 5 || $resend_cooldown > 300) {
      $form_state->setErrorByName('resend_cooldown', $this->t('Resend cooldown must be between 5 and 300 seconds.'));
    }

    $max_resends = (int) $form_state->getValue('max_resends');
    if ($max_resends < 0 || $max_resends > 10) {
      $form_state->setErrorByName('max_resends', $this->t('Maximum resend attempts must be between 0 and 10.'));
    }

    $default_redirect_path = trim((string) $form_state->getValue('default_redirect_path'));
    if ($default_redirect_path === '' || !str_starts_with($default_redirect_path, '/')) {
      $form_state->setErrorByName('default_redirect_path', $this->t('The default redirect path must start with /.'));
    }

    $role_redirect_paths = $form_state->getValue('role_redirect_paths') ?: [];
    foreach ($role_redirect_paths as $role_id => $path) {
      $path = trim((string) $path);
      if ($path !== '' && !str_starts_with($path, '/')) {
        $form_state->setErrorByName('role_redirect_paths][' . $role_id, $this->t('Redirect paths must start with /.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $protected_roles = array_filter($form_state->getValue('protected_roles') ?: []);
    $role_redirect_paths = [];
    foreach (($form_state->getValue('role_redirect_paths') ?: []) as $role_id => $path) {
      $path = trim((string) $path);
      if ($path !== '') {
        $role_redirect_paths[$role_id] = $path;
      }
    }

    $this->configFactory()
      ->getEditable('custom_login_2fa.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('protected_roles', array_values($protected_roles))
      ->set('code_ttl', (int) $form_state->getValue('code_ttl'))
      ->set('code_length', (int) $form_state->getValue('code_length'))
      ->set('max_attempts', (int) $form_state->getValue('max_attempts'))
      ->set('mandrill_message_key', trim((string) $form_state->getValue('mandrill_message_key')))
      ->set('default_redirect_path', trim((string) $form_state->getValue('default_redirect_path')))
      ->set('role_redirect_paths', $role_redirect_paths)
      ->set('resend_cooldown', (int) $form_state->getValue('resend_cooldown'))
      ->set('max_resends', (int) $form_state->getValue('max_resends'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
