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
final class CustomLogin2faSettingsForm extends ConfigFormBase {

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
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'custom_login_2fa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'custom_login_2fa.settings',
    ];
  }

  /**
   * Builds role options excluding anonymous.
   */
  private function getRoleOptions(): array {
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
      '#description' => $this->t('Example: mail_text_7. This key must exist in the Mandrill settings of enterprise_integrations.'),
      '#required' => FALSE,
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $protected_roles = array_filter($form_state->getValue('protected_roles') ?: []);

    $this->configFactory()
      ->getEditable('custom_login_2fa.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('protected_roles', array_values($protected_roles))
      ->set('code_ttl', (int) $form_state->getValue('code_ttl'))
      ->set('code_length', (int) $form_state->getValue('code_length'))
      ->set('max_attempts', (int) $form_state->getValue('max_attempts'))
      ->set('mandrill_message_key', trim((string) $form_state->getValue('mandrill_message_key')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}