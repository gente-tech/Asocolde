<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudIdGenerator;
use Drupal\asocolderma_inscription\Service\SolicitudManager;
use Drupal\asocolderma_inscription\Service\SolicitudNotificationManager;
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

  private function getNotificationManager(): SolicitudNotificationManager
  {
    return \Drupal::service('asocolderma_inscription.solicitud_notification_manager');
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

    $form['#attached']['library'][] = 'asocolderma_inscription/intl_phone_field';

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
        '#description' => $this->t('Seleccione la categoría de miembro a la cual desea postularse, de acuerdo con los criterios establecidos por la Asociación.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('tipo_de_asociado'),
        '#default_value' => $wizard_values['general']['tipo_asociado'] ?? NULL,
      ];

      $form['general']['nombre1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Primer nombre'),
        '#description' => $this->t('Digite su primer nombre exactamente como figura en su documento de identidad. Ejemplo: Carlos.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['nombre1'] ?? '',
      ];

      $form['general']['nombre2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Segundo nombre'),
        '#description' => $this->t('Digite su segundo nombre, si aplica, tal como aparece en su documento de identidad. Ejemplo: Andrés.'),
        '#default_value' => $wizard_values['general']['nombre2'] ?? '',
      ];

      $form['general']['apellido1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Primer apellido'),
        '#description' => $this->t('Digite su primer apellido exactamente como figura en su documento de identidad. Ejemplo: Gómez.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['apellido1'] ?? '',
      ];

      $form['general']['apellido2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Segundo apellido'),
        '#description' => $this->t('Digite su segundo apellido, si aplica, tal como aparece en su documento de identidad. Ejemplo: Rodríguez.'),
        '#default_value' => $wizard_values['general']['apellido2'] ?? '',
      ];

      $form['general']['fecha_nacimiento'] = [
        '#type' => 'date',
        '#title' => $this->t('Fecha de nacimiento'),
        '#description' => $this->t('Seleccione su fecha de nacimiento conforme a su documento oficial.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['fecha_nacimiento'] ?? '',
      ];

      $form['general']['estado_civil'] = [
        '#type' => 'select',
        '#title' => $this->t('Estado civil'),
        '#description' => $this->t('Seleccione su estado civil actual. Esta información será utilizada únicamente para fines administrativos del proceso.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('estado_civil'),
        '#default_value' => $wizard_values['general']['estado_civil'] ?? NULL,
      ];

      $form['general']['sexo'] = [
        '#type' => 'select',
        '#title' => $this->t('Sexo'),
        '#description' => $this->t('Seleccione la opción correspondiente según su información personal.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('sexo'),
        '#default_value' => $wizard_values['general']['sexo'] ?? NULL,
      ];

      $form['general']['tipo_documento'] = [
        '#type' => 'select',
        '#title' => $this->t('Tipo de documento'),
        '#description' => $this->t('Seleccione el tipo de documento con el cual se identifica formalmente.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('tipo_de_documento'),
        '#default_value' => $wizard_values['general']['tipo_documento'] ?? NULL,
      ];

      $form['general']['numero_documento'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Número de documento'),
        '#description' => $this->t('Ingrese el número del documento sin puntos, comas ni espacios. Ejemplo: 1234567890.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['numero_documento'] ?? '',
      ];

      $form['general']['registro_medico'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Registro médico'),
        '#description' => $this->t('Ingrese el número de su registro médico profesional vigente. Ejemplo: RM-12345 o el consecutivo oficial que corresponda.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['general']['registro_medico'] ?? '',
      ];

      $form['general']['pais'] = [
        '#type' => 'select',
        '#title' => $this->t('País'),
        '#description' => $this->t('Indique el país de residencia actual. Ejemplo: Colombia.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('country'),
        '#default_value' => $wizard_values['general']['pais'] ?? NULL,
      ];

      $form['general']['departamento'] = [
        '#type' => 'select',
        '#title' => $this->t('Departamento'),
        '#description' => $this->t('Seleccione el departamento de residencia actual.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('departametos'),
        '#default_value' => $wizard_values['general']['departamento'] ?? NULL,
      ];

      $form['general']['ciudad_ejercicio'] = [
        '#type' => 'select',
        '#title' => $this->t('Ciudad de ejercicio'),
        '#description' => $this->t('Digite la ciudad donde desarrolla actualmente su ejercicio profesional. Ejemplo: Bogotá.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('city'),
        '#default_value' => $wizard_values['general']['ciudad_ejercicio'] ?? NULL,
      ];
    }

    if ($step === 2) {
      $form['contacto'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 2: Información uso institucional'),
        '#open' => TRUE,
      ];

      $form['contacto']['direccion'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Dirección física principal'),
        '#description' => $this->t('Ingrese su dirección física principal. Ejemplo: Calle 123 # 45-67, Apartamento 201.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['direccion'] ?? '',
      ];

      $form['contacto']['correspondencia_fisica'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Dirección institucional'),
        '#description' => $this->t('Ingrese la dirección institucional o profesional asociada a su ejercicio médico.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['correspondencia_fisica'] ?? '',
      ];

      $form['contacto']['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Correo electrónico principal'),
        '#description' => $this->t('Ingrese el correo electrónico principal donde recibirá comunicaciones oficiales del proceso. Ejemplo: nombre@dominio.com.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['email'] ?? '',
      ];

      $form['contacto']['celular_indicativo'] = [
        '#type' => 'hidden',
        '#default_value' => $wizard_values['contacto']['celular_indicativo'] ?? '+57',
        '#attributes' => [
          'id' => 'edit-contacto-celular-indicativo',
        ],
      ];

      $form['contacto']['celular_full'] = [
        '#type' => 'hidden',
        '#default_value' => $wizard_values['contacto']['celular_full'] ?? $wizard_values['contacto']['celular'] ?? '',
        '#attributes' => [
          'id' => 'edit-contacto-celular-full',
        ],
      ];

      $form['contacto']['celular'] = [
        '#type' => 'tel',
        '#title' => $this->t('Teléfono celular de contacto'),
        '#description' => $this->t('Seleccione el indicativo del país e ingrese únicamente el número celular nacional. Ejemplo para Colombia: 3001234567.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['contacto']['celular_nacional'] ?? '',
        '#attributes' => [
          'class' => ['asocolderma-intl-phone'],
          'data-indicativo-target' => '#edit-contacto-celular-indicativo',
          'data-full-phone-target' => '#edit-contacto-celular-full',
          'autocomplete' => 'tel-national',
        ],
      ];

      $form['contacto']['lugar_correspondencia'] = [
        '#type' => 'select',
        '#title' => $this->t('En caso de ser ratificado ¿Dónde desea recibir la correspondencia física?'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('lugar_de_correspondencia'),
        '#default_value' => $wizard_values['contacto']['lugar_correspondencia'] ?? NULL,
      ];
    }

    if ($step === 3) {
      $form['profesional'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 3: Información académica'),
        '#open' => TRUE,
      ];

      $form['profesional']['facultad_pregrado'] = [
        '#type' => 'select',
        '#title' => $this->t('Facultad de medicina – Pregrado'),
        '#description' => $this->t('Seleccione la institución donde obtuvo su título de médico.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('university_undergraduate'),
        '#default_value' => $wizard_values['profesional']['facultad_pregrado'] ?? NULL,
      ];

      $form['profesional']['pais_pregrado'] = [
        '#type' => 'select',
        '#title' => $this->t('País donde realizó el pregrado en medicina'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('country'),
        '#default_value' => $wizard_values['profesional']['pais_pregrado'] ?? NULL,
      ];

      $form['profesional']['titulo_universitario'] = [
        '#type' => 'select',
        '#title' => $this->t('Título universitario'),
        '#description' => $this->t('Seleccione el título profesional obtenido.'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('university_degree'),
        '#default_value' => $wizard_values['profesional']['titulo_universitario'] ?? NULL,
      ];

      $form['profesional']['universidad_residencia'] = [
        '#type' => 'select',
        '#title' => $this->t('Universidad de residencia'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('university_residence'),
        '#default_value' => $wizard_values['profesional']['universidad_residencia'] ?? NULL,
      ];

      $form['profesional']['pais_residencia'] = [
        '#type' => 'select',
        '#title' => $this->t('País donde realizó la residencia'),
        '#required' => TRUE,
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('country'),
        '#default_value' => $wizard_values['profesional']['pais_residencia'] ?? NULL,
      ];

      $form['profesional']['recertificacion_camec'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Si es ratificado ¿le gustaría participar en el programa voluntario de Re-certificación médica en dermatología CAMEC?'),
        '#default_value' => !empty($wizard_values['profesional']['recertificacion_camec']) ? 1 : 0,
      ];

      $form['profesional']['tiene_subespecialidad'] = [
        '#type' => 'radios',
        '#title' => $this->t('Tiene una Subespecialidad?'),
        '#required' => TRUE,
        '#options' => [
          1 => $this->t('Sí'),
          0 => $this->t('No'),
        ],
        '#default_value' => isset($wizard_values['profesional']['tiene_subespecialidad'])
          ? (int) $wizard_values['profesional']['tiene_subespecialidad']
          : NULL,
      ];

      $form['profesional']['subespecialidad_cual'] = [
        '#type' => 'select',
        '#title' => $this->t('Subespecialidad'),
        '#description' => $this->t('Seleccione su subespecialidad, en caso de contar con ella.'),
        '#empty_option' => $this->t('- Seleccione -'),
        '#options' => $this->getTaxonomyOptions('services_specialties'),
        '#default_value' => $wizard_values['profesional']['subespecialidad_cual'] ?? NULL,
        '#states' => [
          'visible' => [
            ':input[name="profesional[tiene_subespecialidad]"]' => ['value' => '1'],
          ],
          'required' => [
            ':input[name="profesional[tiene_subespecialidad]"]' => ['value' => '1'],
          ],
        ],
      ];
    }

    if ($step === 4) {
      $form['adjuntos'] = [
        '#type' => 'details',
        '#title' => $this->t('Paso 4: Información societaria(Adjuntos)'),
        '#open' => TRUE,
      ];

      $form['adjuntos']['adj_carta_1'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Carta 1'),
        '#description' => $this->t('Adjunte la primera carta de presentación o recomendación en formato PDF, conforme a los requisitos del proceso de ingreso. Tamaño máximo: 10 MB.'),
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
        '#description' => $this->t('Adjunte la segunda carta de presentación o recomendación en formato PDF, conforme a los requisitos del proceso de ingreso. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/carta_2/',
        '#default_value' => $wizard_values['adjuntos']['adj_carta_2'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_rut'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('RUT'),
        '#description' => $this->t('Adjunte una copia actualizada del Registro Único Tributario (RUT) en formato PDF. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/rut/',
        '#default_value' => $wizard_values['adjuntos']['adj_rut'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_id'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Documento de identidad'),
        '#description' => $this->t('Adjunte una copia legible de su documento de identidad vigente. Formatos permitidos: PDF, JPG, JPEG o PNG. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/id/',
        '#default_value' => $wizard_values['adjuntos']['adj_id'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf jpg jpeg png'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_carta_ingreso'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Carta de solicitud de ingreso'),
        '#description' => $this->t('Adjunte la carta formal de solicitud de ingreso a la Asociación en formato PDF. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/carta_ingreso/',
        '#default_value' => $wizard_values['adjuntos']['adj_carta_ingreso'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_hv'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Hoja de vida'),
        '#description' => $this->t('Adjunte su hoja de vida actualizada en formato PDF. Se recomienda que el documento incluya formación académica, experiencia profesional y datos de contacto. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/hv/',
        '#default_value' => $wizard_values['adjuntos']['adj_hv'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_diploma_medico'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Diploma médico'),
        '#description' => $this->t('Adjunte el diploma que acredita su título profesional como médico en formato PDF. Tamaño máximo: 10 MB.'),
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
        '#description' => $this->t('Adjunte el diploma o soporte académico que acredita su especialidad en dermatología en formato PDF. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/diploma_dermatologo/',
        '#default_value' => $wizard_values['adjuntos']['adj_diploma_dermatologo'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_rethus'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('RETHUS'),
        '#description' => $this->t('Adjunte el certificado o soporte de inscripción en RETHUS en formato PDF. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/rethus/',
        '#default_value' => $wizard_values['adjuntos']['adj_rethus'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_aut_verificacion'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Autorización de verificación'),
        '#description' => $this->t('Adjunte el documento firmado mediante el cual autoriza la validación de la información suministrada. Formato PDF. Tamaño máximo: 10 MB.'),
        '#required' => TRUE,
        '#upload_location' => 'private://solicitud_ingreso/aut_verificacion/',
        '#default_value' => $wizard_values['adjuntos']['adj_aut_verificacion'] ?? NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [10 * 1024 * 1024],
        ],
      ];

      $form['adjuntos']['adj_cert_publicacion'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Certificación de publicaciones (si aplica)'),
        '#description' => $this->t('Si cuenta con publicaciones, adjunte los certificados o soportes correspondientes en formato PDF. Este campo es opcional. Tamaño máximo: 10 MB.'),
        '#upload_location' => 'private://solicitud_ingreso/cert_publicacion/',
        '#default_value' => $wizard_values['adjuntos']['adj_cert_publicacion'] ?? NULL,
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
        '#description' => $this->t('Declaro que la información suministrada es veraz y autorizo su validación dentro del proceso institucional de evaluación y admisión.'),
        '#required' => TRUE,
        '#default_value' => $wizard_values['confirm']['terms'] ?? 0,
      ];

      $form['confirm']['preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Resumen'),
        '#markup' => $this->t('Verifique cuidadosamente la información registrada antes de enviar su solicitud. Una vez remitida, será gestionada conforme al flujo institucional definido por la Asociación.'),
      ];
    }

    // Navegación.
    $form['actions'] = ['#type' => 'actions'];

    if ($step > 1) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#name' => 'back',
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
        '#name' => 'save_exit',
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

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    $step = (int) $form_state->get('step');
    $trigger = $form_state->getTriggeringElement();
    $trigger_name = $trigger['#name'] ?? '';

    // No validar el celular cuando el usuario va hacia atrás o guarda y sale.
    if (in_array($trigger_name, ['back', 'save_exit'], TRUE)) {
      return;
    }

    // Validación específica del paso 2: Información de contacto.
    if ($step !== 2) {
      return;
    }

    $values = (array) $form_state->getValues();
    $contacto = (array) ($values['contacto'] ?? []);

    $indicativo = trim((string) ($contacto['celular_indicativo'] ?? ''));
    $celular_nacional = preg_replace('/\D+/', '', (string) ($contacto['celular'] ?? ''));
    $celular_full = trim((string) ($contacto['celular_full'] ?? ''));

    if ($indicativo === '') {
      $form_state->setErrorByName(
        'contacto][celular',
        $this->t('Debe seleccionar el indicativo del país para el teléfono celular.')
      );
      return;
    }

    if (!preg_match('/^\+\d{1,4}$/', $indicativo)) {
      $form_state->setErrorByName(
        'contacto][celular',
        $this->t('El indicativo del teléfono celular no tiene un formato válido.')
      );
      return;
    }

    if ($celular_nacional === '') {
      $form_state->setErrorByName(
        'contacto][celular',
        $this->t('Debe ingresar el número celular.')
      );
      return;
    }

    if (strlen($celular_nacional) < 6 || strlen($celular_nacional) > 15) {
      $form_state->setErrorByName(
        'contacto][celular',
        $this->t('El número celular debe tener entre 6 y 15 dígitos.')
      );
      return;
    }

    if ($celular_full === '') {
      $celular_full = $indicativo . $celular_nacional;
    }

    if (!preg_match('/^\+\d{7,18}$/', $celular_full)) {
      $form_state->setErrorByName(
        'contacto][celular',
        $this->t('El teléfono celular completo no tiene un formato internacional válido.')
      );
    }
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
        'departamento' => $input['general']['departamento'] ?? NULL,
        'ciudad_ejercicio' => $input['general']['ciudad_ejercicio'] ?? '',
      ];
    }

    if ($step === 2) {
      $celular_indicativo = trim((string) ($input['contacto']['celular_indicativo'] ?? '+57'));
      $celular_nacional = preg_replace('/\D+/', '', (string) ($input['contacto']['celular'] ?? ''));
      $celular_full = trim((string) ($input['contacto']['celular_full'] ?? ''));

      if ($celular_full === '' && $celular_nacional !== '') {
        $celular_full = $celular_indicativo . $celular_nacional;
      }

      $wizard_values['contacto'] = [
        'direccion' => $input['contacto']['direccion'] ?? '',
        'correspondencia_fisica' => $input['contacto']['correspondencia_fisica'] ?? '',
        'email' => $input['contacto']['email'] ?? '',
        'celular_indicativo' => $celular_indicativo,
        'celular_nacional' => $celular_nacional,
        'celular_full' => $celular_full,
        'celular' => $celular_full,
        'lugar_correspondencia' => $input['contacto']['lugar_correspondencia'] ?? NULL,
      ];
    }

    if ($step === 3) {
      $wizard_values['profesional'] = [
        'facultad_pregrado' => $input['profesional']['facultad_pregrado'] ?? NULL,
        'pais_pregrado' => $input['profesional']['pais_pregrado'] ?? NULL,
        'titulo_universitario' => $input['profesional']['titulo_universitario'] ?? NULL,
        'universidad_residencia' => $input['profesional']['universidad_residencia'] ?? NULL,
        'pais_residencia' => $input['profesional']['pais_residencia'] ?? NULL,
        'recertificacion_camec' => !empty($input['profesional']['recertificacion_camec']) ? 1 : 0,
        'tiene_subespecialidad' => isset($input['profesional']['tiene_subespecialidad'])
          ? (int) $input['profesional']['tiene_subespecialidad']
          : 0,
        'subespecialidad_cual' => $input['profesional']['subespecialidad_cual'] ?? NULL,
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
        'adj_carta_ingreso' => $input['adjuntos']['adj_carta_ingreso'] ?? NULL,
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

      'field_tipo_asociado' => ['target_id' => (int) $wizard_values['general']['tipo_asociado'],],
      'field_nombre1' => $wizard_values['general']['nombre1'],
      'field_nombre2' => $wizard_values['general']['nombre2'] ?? '',
      'field_apellido1' => $wizard_values['general']['apellido1'],
      'field_apellido2' => $wizard_values['general']['apellido2'] ?? '',
      'field_fecha_nacimiento' => $wizard_values['general']['fecha_nacimiento'],
      'field_estado_civil' => ['target_id' => (int) $wizard_values['general']['estado_civil'],],
      'field_sexo' => ['target_id' => (int) $wizard_values['general']['sexo'],],
      'field_tipo_documento' => ['target_id' => (int) $wizard_values['general']['tipo_documento'],],
      'field_numero_documento' => $wizard_values['general']['numero_documento'],
      'field_registro_medico' => $wizard_values['general']['registro_medico'],
      'field_pais' => ['target_id' => (int) $wizard_values['general']['pais'],],
      'field_departamento' => ['target_id' => (int) $wizard_values['general']['departamento'],],
      'field_ciudad_ejercicio' => ['target_id' => (int) $wizard_values['general']['ciudad_ejercicio'],],

      'field_correspondencia_fisica' => $wizard_values['contacto']['direccion'] ?? '',
      'field_direccion_institucional' => $wizard_values['contacto']['correspondencia_fisica'] ?? '',
      'field_email_principal' => $wizard_values['contacto']['email'] ?? '',
      'field_celular' => $wizard_values['contacto']['celular_full'] ?? $wizard_values['contacto']['celular'] ?? '',
      'field_lugar_correspondencia' => !empty($wizard_values['contacto']['lugar_correspondencia'])
        ? ['target_id' => (int) $wizard_values['contacto']['lugar_correspondencia']]
        : NULL,

      'field_facultad_pregrado' => !empty($wizard_values['profesional']['facultad_pregrado'])
        ? ['target_id' => (int) $wizard_values['profesional']['facultad_pregrado']]
        : NULL,

      'field_pais_pregrado' => !empty($wizard_values['profesional']['pais_pregrado'])
        ? ['target_id' => (int) $wizard_values['profesional']['pais_pregrado']]
        : NULL,

      'field_titulo_universitario' => !empty($wizard_values['profesional']['titulo_universitario'])
        ? ['target_id' => (int) $wizard_values['profesional']['titulo_universitario']]
        : NULL,

      'field_universidad_residencia' => !empty($wizard_values['profesional']['universidad_residencia'])
        ? ['target_id' => (int) $wizard_values['profesional']['universidad_residencia']]
        : NULL,

      'field_pais_residencia' => !empty($wizard_values['profesional']['pais_residencia'])
        ? ['target_id' => (int) $wizard_values['profesional']['pais_residencia']]
        : NULL,

      'field_recertificacion_camec' => !empty($wizard_values['profesional']['recertificacion_camec']) ? 1 : 0,

      'field_tiene_subespecialidad' => !empty($wizard_values['profesional']['tiene_subespecialidad']) ? 1 : 0,

      'field_subespecialidad_cual' => !empty($wizard_values['profesional']['tiene_subespecialidad']) && !empty($wizard_values['profesional']['subespecialidad_cual'])
        ? ['target_id' => (int) $wizard_values['profesional']['subespecialidad_cual']]
        : NULL,
    ]);

    // Adjuntos: convertir a permanentes y asignar.
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $adj = $wizard_values['adjuntos'] ?? [];

    $map = [
      'adj_carta_ingreso' => 'field_adj_carta_ingreso',
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

    try {
      $this->getNotificationManager()->sendForPhase($node, 'solicitud_creada', [
        'subject' => 'Solicitud de ingreso creada - ' . $id_solicitud,
        'origin' => 'aspirante_wizard',
        'solicitud_id' => $id_solicitud,
      ]);
    } catch (\Throwable $e) {
      \Drupal::logger('asocolderma_inscription')->error(
        'Error enviando notificación de solicitud creada para la solicitud @id: @message',
        [
          '@id' => $id_solicitud,
          '@message' => $e->getMessage(),
        ]
      );
    }

    // Limpieza draft.
    $this->getTempStore()->delete(self::TEMPSTORE_KEY);

    $this->messenger()->addStatus($this->t('Solicitud creada correctamente con ID: @id', ['@id' => $id_solicitud]));
    $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
  }

  /**
   * Retorna opciones de términos publicados de un vocabulario.
   */
  private function getTaxonomyOptions(string $vocabulary): array
  {
    $options = [];

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => $vocabulary,
        'status' => 1,
      ]);

    uasort($terms, static function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }

    return $options;
  }
}
