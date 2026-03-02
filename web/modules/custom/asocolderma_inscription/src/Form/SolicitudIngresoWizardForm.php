<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudIdGenerator;
use Drupal\asocolderma_inscription\Service\SolicitudManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class SolicitudIngresoWizardForm extends FormBase
{

  private const TEMPSTORE_COLLECTION = 'asocolderma_inscription_wizard';
  private const TEMPSTORE_KEY = 'solicitud_ingreso_draft';

  private function getTempStore()
  {
    return \Drupal::service('tempstore.private')->get(self::TEMPSTORE_COLLECTION);
  }

  private function getSolicitudManager(): SolicitudManager
  {
    return \Drupal::service('asocolderma_inscription.solicitud_manager');
  }

  private function getIdGenerator(): SolicitudIdGenerator
  {
    return \Drupal::service('asocolderma_inscription.solicitud_id_generator');
  }

  /**
   * Obtiene el TID del término "En trámite" del vocabulario estado_solicitud_ingreso.
   *
   * Este término se usa como estado por defecto al crear una solicitud.
   *
   * @throws \RuntimeException
   *   Si no existe el término.
   */
  private function getTidEstadoEnTramite(): int
  {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $term_storage->loadByProperties([
      'vid' => 'estado_solicitud_ingreso',
      'name' => 'En trámite',
    ]);

    if (empty($terms)) {
      throw new \RuntimeException('No existe el término "En trámite" en el vocabulario estado_solicitud_ingreso.');
    }

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = reset($terms);
    return (int) $term->id();
  }

  public function getFormId(): string
  {
    return 'asocolderma_solicitud_ingreso_wizard';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $account = $this->currentUser();

    // Solo aspirante.
    if (!in_array('aspirante', $account->getRoles(), TRUE)) {
      $form['msg'] = ['#markup' => $this->t('No tienes permisos para crear una solicitud.')];
      return $form;
    }

    // Regla: una solicitud activa.
    if ($this->getSolicitudManager()->hasActiveSolicitud((int) $account->id())) {
      $form['msg'] = ['#markup' => $this->t('Ya tienes una solicitud activa. No puedes crear otra.')];
      return $form;
    }

    $draft = $this->getTempStore()->get(self::TEMPSTORE_KEY);
    $stored_step = isset($draft['step']) ? (int) $draft['step'] : 1;
    $stored_values = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];

    $step = (int) ($form_state->get('step') ?? $stored_step);
    $step = max(1, min(5, $step));
    $form_state->set('step', $step);

    $wizard_values = (array) ($form_state->get('wizard_values') ?? $stored_values);
    $form_state->set('wizard_values', $wizard_values);

    $form['#tree'] = TRUE;

    $form['step_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Paso @step de 5', ['@step' => $step]),
    ];

    if ($step === 1) {
      $form['general'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 1: Información general'),
        '#open' => TRUE,
      ];

      $form['general']['tipo_asociado'] = [
        '#type' => 'select',
        '#title' => $this->t('Tipo de asociado al que aspira'),
        '#required' => TRUE,
        '#options' => [
          'numero' => $this->t('Número'),
          'adherente' => $this->t('Adherente'),
          'correspondiente' => $this->t('Correspondiente'),
          'internacional' => $this->t('Internacional'),
        ],
        '#default_value' => $wizard_values['general']['tipo_asociado'] ?? NULL,
      ];

      $form['general']['nombre1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Primer nombre'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['nombre1'] ?? '',
      ];
      $form['general']['nombre2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Segundo nombre'),
        '#default_value' => $wizard_values['general']['nombre2'] ?? '',
      ];
      $form['general']['apellido1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Primer apellido'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['apellido1'] ?? '',
      ];
      $form['general']['apellido2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Segundo apellido'),
        '#default_value' => $wizard_values['general']['apellido2'] ?? '',
      ];

      $form['general']['fecha_nacimiento'] = [
        '#type' => 'date',
        '#title' => $this->t('Fecha de nacimiento'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['fecha_nacimiento'] ?? '',
      ];

      $form['general']['estado_civil'] = [
        '#type' => 'select',
        '#title' => $this->t('Estado civil'),
        '#required' => TRUE,
        '#options' => [
          'soltero' => $this->t('Soltero'),
          'casado' => $this->t('Casado'),
          'union_libre' => $this->t('Unión libre'),
          'divorciado' => $this->t('Divorciado'),
          'viudo' => $this->t('Viudo'),
        ],
        '#default_value' => $wizard_values['general']['estado_civil'] ?? NULL,
      ];

      $form['general']['sexo'] = [
        '#type' => 'select',
        '#title' => $this->t('Sexo'),
        '#required' => TRUE,
        '#options' => [
          'm' => $this->t('Masculino'),
          'f' => $this->t('Femenino'),
          'o' => $this->t('Otro'),
        ],
        '#default_value' => $wizard_values['general']['sexo'] ?? NULL,
      ];

      $form['general']['tipo_documento'] = [
        '#type' => 'select',
        '#title' => $this->t('Tipo de documento'),
        '#required' => TRUE,
        '#options' => [
          'cc' => $this->t('Cédula de ciudadanía'),
          'ce' => $this->t('Cédula de extranjería'),
          'pasaporte' => $this->t('Pasaporte'),
        ],
        '#default_value' => $wizard_values['general']['tipo_documento'] ?? NULL,
      ];

      $form['general']['numero_documento'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Número de documento'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['numero_documento'] ?? '',
      ];

      $form['general']['registro_medico'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Registro médico'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['registro_medico'] ?? '',
      ];

      $form['general']['pais'] = [
        '#type' => 'textfield',
        '#title' => $this->t('País'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['pais'] ?? '',
      ];

      $form['general']['ciudad_ejercicio'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Ciudad de ejercicio'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['ciudad_ejercicio'] ?? '',
      ];
    }

    if ($step === 2) {
      $form['contacto'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 2: Información de contacto'),
        '#open' => TRUE,
      ];

      $form['contacto']['direccion'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Dirección'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['direccion'] ?? '',
      ];

      $form['contacto']['telefono'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Teléfono'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['telefono'] ?? '',
      ];

      $form['contacto']['celular'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Celular'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['celular'] ?? '',
      ];

      $form['contacto']['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Correo electrónico'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['email'] ?? '',
      ];
    }

    if ($step === 3) {
      $form['profesional'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 3: Información profesional'),
        '#open' => TRUE,
      ];

      $form['profesional']['universidad'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Universidad'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['profesional']['universidad'] ?? '',
      ];

      $form['profesional']['titulo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Título'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['profesional']['titulo'] ?? '',
      ];

      $form['profesional']['fecha_grado'] = [
        '#type' => 'date',
        '#title' => $this->t('Fecha de grado'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['profesional']['fecha_grado'] ?? '',
      ];

      $form['profesional']['especialidad'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Especialidad'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['profesional']['especialidad'] ?? '',
      ];

      $form['profesional']['subespecialidad'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subespecialidad (si aplica)'),
        '#default_value' => $wizard_values['profesional']['subespecialidad'] ?? '',
      ];

      $form['profesional']['lugar_trabajo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Lugar de trabajo'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['profesional']['lugar_trabajo'] ?? '',
      ];
    }

    if ($step === 4) {
      $form['adjuntos'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 4: Adjuntos'),
        '#open' => TRUE,
      ];

      $form['adjuntos']['adj_id'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Documento de identidad'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/id/',
        '#default_value' => $wizard_values['adjuntos']['adj_id'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf jpg jpeg png'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_hv'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Hoja de vida'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/hv/',
        '#default_value' => $wizard_values['adjuntos']['adj_hv'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_rut'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('RUT'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/rut/',
        '#default_value' => $wizard_values['adjuntos']['adj_rut'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_rethus'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('RETHUS'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/rethus/',
        '#default_value' => $wizard_values['adjuntos']['adj_rethus'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_diploma_medico'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Diploma médico'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/diploma_medico/',
        '#default_value' => $wizard_values['adjuntos']['adj_diploma_medico'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_diploma_dermatologo'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Diploma dermatólogo'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/diploma_dermatologo/',
        '#default_value' => $wizard_values['adjuntos']['adj_diploma_dermatologo'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_cert_publicacion'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Certificación de publicaciones (si aplica)'),
        '#upload_location' => 'private://solicitud_ingreso/cert_publicacion/',
        '#default_value' => $wizard_values['adjuntos']['adj_cert_publicacion'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_aut_verificacion'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Autorización de verificación'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/aut_verificacion/',
        '#default_value' => $wizard_values['adjuntos']['adj_aut_verificacion'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_carta_1'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Carta 1'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/carta_1/',
        '#default_value' => $wizard_values['adjuntos']['adj_carta_1'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_carta_2'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Carta 2'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/carta_2/',
        '#default_value' => $wizard_values['adjuntos']['adj_carta_2'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];
    }

    if ($step === 5) {
      $form['confirm'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 5: Confirmación'),
        '#open' => TRUE,
      ];

      $form['confirm']['terms'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Acepto los términos y condiciones'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['confirm']['terms'] ?? 0,
      ];

      $form['confirm']['preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Resumen'),
        '#markup' => $this->t('Revisa que la información sea correcta antes de enviar la solicitud.'),
      ];
    }

    // Navegación.
    $form['actions'] = ['#type' => 'actions'];

    if ($step > 1) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Atrás'),
        '#submit' => ['::backSubmit'],
        '#limit_validation_errors' => [],
      ];
    }

    if ($step < 5) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Guardar y continuar'),
        '#submit' => ['::nextSubmit'],
      ];

      $form['actions']['save_exit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Guardar y salir'),
        '#submit' => ['::saveExitSubmit'],
        '#limit_validation_errors' => [],
      ];
    } else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Enviar solicitud'),
        '#submit' => ['::submitSolicitud'],
      ];
    }

    return $form;
  }

  public function backSubmit(array &$form, FormStateInterface $form_state): void
  {
    $step = (int) $form_state->get('step');
    $step = max(1, $step - 1);
    $form_state->set('step', $step);
    $form_state->setRebuild(TRUE);
  }

  public function nextSubmit(array &$form, FormStateInterface $form_state): void
  {
    $this->persistWizardValues($form_state);
    $step = (int) $form_state->get('step');
    $step = min(5, $step + 1);
    $form_state->set('step', $step);
    $form_state->setRebuild(TRUE);
  }

  public function saveExitSubmit(array &$form, FormStateInterface $form_state): void
  {
    $this->persistWizardValues($form_state);
    $this->messenger()->addStatus($this->t('Borrador guardado. Puedes continuar luego.'));
    $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
  }

  /**
   * Método requerido por FormBase.
   * No se utiliza porque el flujo está controlado por botones con submit handlers específicos.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    // Se usa submit por botones.
  }

  private function persistWizardValues(FormStateInterface $form_state): void
  {
    $step = (int) $form_state->get('step');
    $wizard_values = (array) $form_state->get('wizard_values');

    // Guardar valores del paso actual.
    $input = (array) $form_state->getValues();

    if ($step === 1) {
      $wizard_values['general'] = [
        'tipo_asociado' => $input['general']['tipo_asociado'] ?? NULL,
        'nombre1' => $input['general']['nombre1'] ?? '',
        'nombre2' => $input['general']['nombre2'] ?? '',
        'apellido1' => $input['general']['apellido1'] ?? '',
        'apellido2' => $input['general']['apellido2'] ?? '',
        'fecha_nacimiento' => $input['general']['fecha_nacimiento'] ?? '',
        'estado_civil' => $input['general']['estado_civil'] ?? NULL,
        'sexo' => $input['general']['sexo'] ?? NULL,
        'tipo_documento' => $input['general']['tipo_documento'] ?? NULL,
        'numero_documento' => $input['general']['numero_documento'] ?? '',
        'registro_medico' => $input['general']['registro_medico'] ?? '',
        'pais' => $input['general']['pais'] ?? '',
        'ciudad_ejercicio' => $input['general']['ciudad_ejercicio'] ?? '',
      ];
    }

    if ($step === 2) {
      $wizard_values['contacto'] = [
        'direccion' => $input['contacto']['direccion'] ?? '',
        'telefono' => $input['contacto']['telefono'] ?? '',
        'celular' => $input['contacto']['celular'] ?? '',
        'email' => $input['contacto']['email'] ?? '',
      ];
    }

    if ($step === 3) {
      $wizard_values['profesional'] = [
        'universidad' => $input['profesional']['universidad'] ?? '',
        'titulo' => $input['profesional']['titulo'] ?? '',
        'fecha_grado' => $input['profesional']['fecha_grado'] ?? '',
        'especialidad' => $input['profesional']['especialidad'] ?? '',
        'subespecialidad' => $input['profesional']['subespecialidad'] ?? '',
        'lugar_trabajo' => $input['profesional']['lugar_trabajo'] ?? '',
      ];
    }

    if ($step === 4) {
      $wizard_values['adjuntos'] = [
        'adj_id' => $input['adjuntos']['adj_id'] ?? NULL,
        'adj_hv' => $input['adjuntos']['adj_hv'] ?? NULL,
        'adj_rut' => $input['adjuntos']['adj_rut'] ?? NULL,
        'adj_rethus' => $input['adjuntos']['adj_rethus'] ?? NULL,
        'adj_diploma_medico' => $input['adjuntos']['adj_diploma_medico'] ?? NULL,
        'adj_diploma_dermatologo' => $input['adjuntos']['adj_diploma_dermatologo'] ?? NULL,
        'adj_cert_publicacion' => $input['adjuntos']['adj_cert_publicacion'] ?? NULL,
        'adj_aut_verificacion' => $input['adjuntos']['adj_aut_verificacion'] ?? NULL,
        'adj_carta_1' => $input['adjuntos']['adj_carta_1'] ?? NULL,
        'adj_carta_2' => $input['adjuntos']['adj_carta_2'] ?? NULL,
      ];
    }

    if ($step === 5) {
      $wizard_values['confirm'] = [
        'terms' => (int) ($input['confirm']['terms'] ?? 0),
      ];
    }

    $form_state->set('wizard_values', $wizard_values);

    // Persistir en tempstore.
    $this->getTempStore()->set(self::TEMPSTORE_KEY, [
      'step' => $step,
      'values' => $wizard_values,
    ]);
  }

  public function submitSolicitud(array &$form, FormStateInterface $form_state): void
  {
    $this->persistWizardValues($form_state);

    $account = $this->currentUser();
    $wizard_values = (array) $form_state->get('wizard_values');

    if ($this->getSolicitudManager()->hasActiveSolicitud((int) $account->id())) {
      $this->messenger()->addError($this->t('Ya tienes una solicitud activa. No puedes crear otra.'));
      $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
      return;
    }

    $doc = (string) ($wizard_values['general']['numero_documento'] ?? '');
    $id_solicitud = $this->getIdGenerator()->generate($doc);

    // Estado por defecto (taxonomía): "En trámite".
    try {
      $estado_tid = $this->getTidEstadoEnTramite();
    } catch (\Throwable $e) {
      \Drupal::logger('asocolderma_inscription')->error('No se pudo resolver el estado por defecto "En trámite": @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('No fue posible crear la solicitud porque falta la configuración del estado por defecto. Contacta al administrador.'));
      $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
      return;
    }

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $node = $node_storage->create([
      'type' => 'solicitud_ingreso',
      'title' => $id_solicitud,
      'uid' => (int) $account->id(),
      'status' => 1,

      'field_solicitud_id' => $id_solicitud,
      // field_state ahora es entity_reference a taxonomy_term.
      'field_state' => ['target_id' => $estado_tid],
      'field_terms_accepted' => 1,

      'field_tipo_asociado' => $wizard_values['general']['tipo_asociado'],
      'field_nombre1' => $wizard_values['general']['nombre1'],
      'field_nombre2' => $wizard_values['general']['nombre2'] ?? '',
      'field_apellido1' => $wizard_values['general']['apellido1'],
      'field_apellido2' => $wizard_values['general']['apellido2'] ?? '',
      'field_fecha_nacimiento' => $wizard_values['general']['fecha_nacimiento'],
      'field_estado_civil' => $wizard_values['general']['estado_civil'],
      'field_sexo' => $wizard_values['general']['sexo'],
      'field_tipo_documento' => $wizard_values['general']['tipo_documento'],
      'field_numero_documento' => $wizard_values['general']['numero_documento'],
      'field_registro_medico' => $wizard_values['general']['registro_medico'],
      'field_pais' => $wizard_values['general']['pais'],
      'field_ciudad_ejercicio' => $wizard_values['general']['ciudad_ejercicio'],

      'field_direccion' => $wizard_values['contacto']['direccion'],
      'field_telefono' => $wizard_values['contacto']['telefono'],
      'field_celular' => $wizard_values['contacto']['celular'],
      'field_email' => $wizard_values['contacto']['email'],

      'field_universidad' => $wizard_values['profesional']['universidad'],
      'field_titulo' => $wizard_values['profesional']['titulo'],
      'field_fecha_grado' => $wizard_values['profesional']['fecha_grado'],
      'field_especialidad' => $wizard_values['profesional']['especialidad'],
      'field_subespecialidad' => $wizard_values['profesional']['subespecialidad'] ?? '',
      'field_lugar_trabajo' => $wizard_values['profesional']['lugar_trabajo'],
    ]);

    // Adjuntos: convertir a permanentes y asignar.
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $adj = $wizard_values['adjuntos'] ?? [];

    $map = [
      'adj_id' => 'field_adj_id',
      'adj_hv' => 'field_adj_hv',
      'adj_rut' => 'field_adj_rut',
      'adj_rethus' => 'field_adj_rethus',
      'adj_diploma_medico' => 'field_adj_diploma_medico',
      'adj_diploma_dermatologo' => 'field_adj_diploma_dermatologo',
      'adj_cert_publicacion' => 'field_adj_cert_publicacion',
      'adj_aut_verificacion' => 'field_adj_aut_verificacion',
      'adj_carta_1' => 'field_adj_carta_1',
      'adj_carta_2' => 'field_adj_carta_2',
    ];

    foreach ($map as $k => $field_name) {
      if (empty($adj[$k]) || empty($adj[$k][0])) {
        continue;
      }
      $fid = (int) $adj[$k][0];
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $file_storage->load($fid);
      if (!$file) {
        continue;
      }
      $file->setPermanent();
      $file->save();

      if ($node->hasField($field_name)) {
        $node->set($field_name, [
          'target_id' => $fid,
        ]);
      }
    }

    $node->save();

    // Limpieza draft.
    $this->getTempStore()->delete(self::TEMPSTORE_KEY);

    $this->messenger()->addStatus($this->t('Solicitud creada correctamente con ID: @id', ['@id' => $id_solicitud]));
    $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
  }
}
