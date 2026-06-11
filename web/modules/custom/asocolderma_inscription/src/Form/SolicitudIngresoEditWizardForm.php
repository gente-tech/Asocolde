<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudClarificationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de edición de solicitud por aclaración para aspirantes.
 */
final class SolicitudIngresoEditWizardForm extends FormBase
{

  protected SolicitudClarificationManager $clarificationManager;

  public function __construct(SolicitudClarificationManager $clarification_manager)
  {
    $this->clarificationManager = $clarification_manager;
  }

  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('asocolderma_inscription.solicitud_clarification_manager')
    );
  }

  public function getFormId(): string
  {
    return 'asocolderma_solicitud_ingreso_edit_wizard';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array
  {
    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      return [
        '#markup' => $this->t('Solicitud no encontrada.'),
      ];
    }

    $active_clarification = $this->clarificationManager->getActiveClarification((int) $node->id());

    if (!$active_clarification) {
      return [
        '#markup' => '<div class="messages messages--warning">' . $this->t('No existe una aclaración activa para esta solicitud.') . '</div>',
      ];
    }

    $requested_fields = $this->filterRequestedFieldsForNode(
      $node,
      (array) ($active_clarification['requested_fields'] ?? [])
    );

    if (empty($requested_fields)) {
      return [
        '#markup' => '<div class="messages messages--warning">' . $this->t('La aclaración activa no tiene campos válidos configurados para edición.') . '</div>',
      ];
    }

    $form_state->set('solicitud_node', $node);
    $form_state->set('active_clarification', $active_clarification);
    $form_state->set('clarification_requested_fields', $requested_fields);

    $aspirante_email = trim((string) $this->currentUser()->getEmail());

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'asocolderma_inscription/intl_phone_field';
    $form['#attached']['library'][] = 'asocolderma_inscription/user_zone';

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['solicitud-aclaracion-intro'],
      ],
    ];

    $form['intro']['title'] = [
      '#markup' => '<h2>' . $this->t('Editar solicitud por aclaración') . '</h2>',
    ];

    $form['intro']['description'] = [
      '#markup' => '<p>' . $this->t('Actualice únicamente la información requerida por la Asociación. Al guardar los cambios deberá marcar la opción “Ajustes realizados” para que la solicitud vuelva al flujo de revisión institucional.') . '</p>',
    ];

    $clarification = trim((string) ($active_clarification['message'] ?? ''));

    $form['intro']['clarification_reason'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['solicitud-aclaracion-motivo'],
      ],
    ];

    $form['intro']['clarification_reason']['title'] = [
      '#markup' => '<h3>' . $this->t('Motivo de la aclaración') . '</h3>',
    ];

    $form['intro']['clarification_reason']['content'] = [
      '#markup' => $clarification !== ''
        ? '<div class="solicitud-aclaracion-motivo__content">' . nl2br(htmlspecialchars($clarification, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>'
        : '<div class="solicitud-aclaracion-motivo__content solicitud-aclaracion-motivo__content--empty">' . $this->t('No se encontró un motivo registrado para esta aclaración.') . '</div>',
    ];

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('Información general'),
      '#open' => TRUE,
    ];

    $form['general']['tipo_asociado'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de asociado al que aspira'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('tipo_de_asociado'),
      '#default_value' => $this->getTargetId($node, 'field_tipo_asociado'),
    ];

    $form['general']['nombre1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Primer nombre'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_nombre1'),
    ];

    $form['general']['nombre2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Segundo nombre'),
      '#default_value' => $this->getStringValue($node, 'field_nombre2'),
    ];

    $form['general']['apellido1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Primer apellido'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_apellido1'),
    ];

    $form['general']['apellido2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Segundo apellido'),
      '#default_value' => $this->getStringValue($node, 'field_apellido2'),
    ];

    $max_birth_date = (new \DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');

    $form['general']['fecha_nacimiento'] = [
      '#type' => 'date',
      '#title' => $this->t('Fecha de nacimiento'),
      '#description' => $this->t('Debes ser mayor de edad para continuar con el proceso.'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_fecha_nacimiento'),
      '#attributes' => [
        'max' => $max_birth_date,
        'data-user-zone-birthdate-field' => '1',
      ],
    ];

    $form['general']['estado_civil'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado civil'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('estado_civil'),
      '#default_value' => $this->getTargetId($node, 'field_estado_civil'),
    ];

    $form['general']['sexo'] = [
      '#type' => 'select',
      '#title' => $this->t('Sexo'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('sexo'),
      '#default_value' => $this->getTargetId($node, 'field_sexo'),
    ];

    $form['general']['tipo_documento'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de documento'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('tipo_de_documento'),
      '#default_value' => $this->getTargetId($node, 'field_tipo_documento'),
    ];

    $form['general']['numero_documento'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Número de documento'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_numero_documento'),
    ];

    $form['general']['registro_medico'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Registro médico'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_registro_medico'),
    ];

    $form['general']['pais'] = [
      '#type' => 'select',
      '#title' => $this->t('País'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('country'),
      '#default_value' => $this->getTargetId($node, 'field_pais'),
    ];

    $form['general']['departamento'] = [
      '#type' => 'select',
      '#title' => $this->t('Departamento'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('departametos'),
      '#default_value' => $this->getTargetId($node, 'field_departamento'),
    ];

    $form['general']['ciudad_ejercicio'] = [
      '#type' => 'select',
      '#title' => $this->t('Ciudad de ejercicio'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('city'),
      '#default_value' => $this->getTargetId($node, 'field_ciudad_ejercicio'),
    ];

    $phone = $this->splitPhone($this->getStringValue($node, 'field_celular'));

    $form['contacto'] = [
      '#type' => 'details',
      '#title' => $this->t('Información de contacto'),
      '#open' => TRUE,
    ];

    $form['contacto']['direccion'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dirección física principal'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_correspondencia_fisica'),
    ];

    $form['contacto']['correspondencia_fisica'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dirección institucional'),
      '#required' => TRUE,
      '#default_value' => $this->getStringValue($node, 'field_direccion_institucional'),
    ];

    $form['contacto']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Correo electrónico principal'),
      '#description' => $this->t('Este correo corresponde al usado para registrar tu cuenta de aspirante y no puede modificarse desde la solicitud.'),
      '#required' => FALSE,
      '#disabled' => TRUE,
      '#default_value' => $aspirante_email,
      '#attributes' => [
        'class' => ['user-zone-readonly-field'],
      ],
    ];

    $form['contacto']['celular_indicativo'] = [
      '#type' => 'hidden',
      '#default_value' => $phone['indicativo'],
      '#attributes' => [
        'id' => 'edit-contacto-celular-indicativo',
      ],
    ];

    $form['contacto']['celular_full'] = [
      '#type' => 'hidden',
      '#default_value' => $phone['full'],
      '#attributes' => [
        'id' => 'edit-contacto-celular-full',
      ],
    ];

    $form['contacto']['celular'] = [
      '#type' => 'tel',
      '#title' => $this->t('Teléfono celular de contacto'),
      '#required' => TRUE,
      '#default_value' => $phone['national'],
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
      '#default_value' => $this->getTargetId($node, 'field_lugar_correspondencia'),
    ];

    $form['profesional'] = [
      '#type' => 'details',
      '#title' => $this->t('Información académica'),
      '#open' => TRUE,
    ];

    $form['profesional']['facultad_pregrado'] = [
      '#type' => 'select',
      '#title' => $this->t('Facultad de medicina – Pregrado'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('university_undergraduate'),
      '#default_value' => $this->getTargetId($node, 'field_facultad_pregrado'),
    ];

    $form['profesional']['pais_pregrado'] = [
      '#type' => 'select',
      '#title' => $this->t('País donde realizó el pregrado en medicina'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('country'),
      '#default_value' => $this->getTargetId($node, 'field_pais_pregrado'),
    ];

    $form['profesional']['titulo_universitario'] = [
      '#type' => 'select',
      '#title' => $this->t('Título universitario'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('university_degree'),
      '#default_value' => $this->getTargetId($node, 'field_titulo_universitario'),
    ];

    $form['profesional']['universidad_residencia'] = [
      '#type' => 'select',
      '#title' => $this->t('Universidad de residencia'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('university_residence'),
      '#default_value' => $this->getTargetId($node, 'field_universidad_residencia'),
    ];

    $form['profesional']['pais_residencia'] = [
      '#type' => 'select',
      '#title' => $this->t('País donde realizó la residencia'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('country'),
      '#default_value' => $this->getTargetId($node, 'field_pais_residencia'),
    ];

    $form['profesional']['tiene_subespecialidad'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tiene una Subespecialidad?'),
      '#required' => TRUE,
      '#options' => [
        1 => $this->t('Sí'),
        0 => $this->t('No'),
      ],
      '#default_value' => $this->getBooleanValue($node, 'field_tiene_subespecialidad'),
    ];

    $form['profesional']['subespecialidad_cual'] = [
      '#type' => 'select',
      '#title' => $this->t('Subespecialidad'),
      '#empty_option' => $this->t('- Seleccione -'),
      '#options' => $this->getTaxonomyOptions('services_specialties'),
      '#default_value' => $this->getTargetId($node, 'field_subespecialidad_cual'),
      '#states' => [
        'visible' => [
          ':input[name="profesional[tiene_subespecialidad]"]' => ['value' => '1'],
        ],
        'required' => [
          ':input[name="profesional[tiene_subespecialidad]"]' => ['value' => '1'],
        ],
      ],
    ];

    $form['adjuntos'] = [
      '#type' => 'details',
      '#title' => $this->t('Información societaria / Adjuntos'),
      '#open' => TRUE,
    ];

    $this->buildFileField($form, $node, 'adj_carta_1', 'field_adj_carta_1', 'Carta 1', 'private://solicitud_ingreso/carta_1/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_carta_2', 'field_adj_carta_2', 'Carta 2', 'private://solicitud_ingreso/carta_2/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_rut', 'field_adj_rut', 'RUT', 'private://solicitud_ingreso/rut/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_id', 'field_adj_id', 'Documento de identidad', 'private://solicitud_ingreso/id/', 'pdf jpg jpeg png', TRUE);
    $this->buildFileField($form, $node, 'adj_carta_ingreso', 'field_adj_carta_ingreso', 'Carta de solicitud de ingreso', 'private://solicitud_ingreso/carta_ingreso/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_hv', 'field_adj_hv', 'Hoja de vida', 'private://solicitud_ingreso/hv/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_diploma_medico', 'field_adj_diploma_medico', 'Diploma médico', 'private://solicitud_ingreso/diploma_medico/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_diploma_dermatologo', 'field_adj_diploma_dermatologo', 'Diploma dermatólogo', 'private://solicitud_ingreso/diploma_dermatologo/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_rethus', 'field_adj_rethus', 'RETHUS', 'private://solicitud_ingreso/rethus/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_aut_verificacion', 'field_adj_aut_verificacion', 'Autorización de verificación', 'private://solicitud_ingreso/aut_verificacion/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_cert_publicacion', 'field_adj_cert_publicacion', 'Certificación de publicaciones', 'private://solicitud_ingreso/cert_publicacion/', 'pdf', FALSE);
    $this->buildFileField($form, $node, 'adj_acta_grado_medico', 'field_adj_acta_grado_medico', 'Acta de grado como médico general', 'private://solicitud_ingreso/acta_grado_medico/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_acta_grado_dermatologo', 'field_adj_acta_grado_dermatologo', 'Acta de grado como dermatólogo', 'private://solicitud_ingreso/acta_grado_dermatologo/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_convalidacion', 'field_adj_convalidacion', 'Copia de la resolución de la convalidación', 'private://solicitud_ingreso/convalidacion/', 'pdf', FALSE);
    $this->buildFileField($form, $node, 'adj_pensum_academico', 'field_adj_pensum_academico', 'Copia del pénsum académico', 'private://solicitud_ingreso/pensum_academico/', 'pdf', TRUE);
    $this->buildFileField($form, $node, 'adj_notas_dermatologia', 'field_adj_notas_dermatologia', 'Notas obtenidas en la especialización en dermatología', 'private://solicitud_ingreso/notas_dermatologia/', 'pdf', TRUE);
    $this->applyClarificationRestrictions($form, $requested_fields);

    $form['confirmacion'] = [
      '#type' => 'details',
      '#title' => $this->t('Confirmación de ajustes'),
      '#open' => TRUE,
    ];

    $form['confirmacion']['ajustes_realizados'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ajustes realizados'),
      '#description' => $this->t('Declaro que realicé los ajustes o aclaraciones solicitadas y autorizo que la solicitud vuelva al flujo de revisión institucional.'),
      '#required' => TRUE,
      '#default_value' => 0,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar ajustes y reenviar solicitud'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => \Drupal\Core\Url::fromRoute('asocolderma_inscription.user_zone_requests'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    if ($form_state->get('show_underage_modal')) {
      $form['#attached']['drupalSettings']['asocoldermaInscription']['underageModal'] = [
        'show' => TRUE,
        'title' => $this->t('No puedes continuar'),
        'message' => $this->t('Para realizar la solicitud de ingreso debes ser mayor de edad. Verifica la fecha de nacimiento registrada.'),
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {

    if ($this->isManagedFileInternalSubmit($form_state)) {
      return;
    }
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $form_state->get('solicitud_node');

    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      $form_state->setErrorByName('form', $this->t('No fue posible validar la solicitud.'));
      return;
    }

    $active_clarification = $this->clarificationManager->getActiveClarification((int) $node->id());

    if (!$active_clarification) {
      $form_state->setErrorByName('form', $this->t('No existe una aclaración activa para esta solicitud.'));
      return;
    }

    $requested_fields = $this->filterRequestedFieldsForNode(
      $node,
      (array) ($active_clarification['requested_fields'] ?? [])
    );

    if (empty($requested_fields)) {
      $form_state->setErrorByName('form', $this->t('No hay campos válidos configurados para esta aclaración.'));
      return;
    }

    $values = (array) $form_state->getValues();

    if (in_array('field_fecha_nacimiento', $requested_fields, TRUE)) {
      $general = (array) ($values['general'] ?? []);
      $fecha_nacimiento = trim((string) ($general['fecha_nacimiento'] ?? ''));

      if (!$this->isAdultBirthDate($fecha_nacimiento)) {
        $form_state->setErrorByName(
          'general][fecha_nacimiento',
          $this->t('No puedes reenviar la solicitud porque la fecha de nacimiento corresponde a una persona menor de edad.')
        );

        $form_state->set('show_underage_modal', TRUE);
        return;
      }
    }

    if (in_array('field_celular', $requested_fields, TRUE)) {
      $contacto = (array) ($values['contacto'] ?? []);
      $indicativo = trim((string) ($contacto['celular_indicativo'] ?? ''));
      $celular_nacional = preg_replace('/\D+/', '', (string) ($contacto['celular'] ?? ''));
      $celular_full = trim((string) ($contacto['celular_full'] ?? ''));

      if ($indicativo === '') {
        $form_state->setErrorByName('contacto][celular', $this->t('Debe seleccionar el indicativo del país para el teléfono celular.'));
        return;
      }

      if (!preg_match('/^\+\d{1,4}$/', $indicativo)) {
        $form_state->setErrorByName('contacto][celular', $this->t('El indicativo del teléfono celular no tiene un formato válido.'));
        return;
      }

      if ($celular_nacional === '') {
        $form_state->setErrorByName('contacto][celular', $this->t('Debe ingresar el número celular.'));
        return;
      }

      if (strlen($celular_nacional) < 6 || strlen($celular_nacional) > 15) {
        $form_state->setErrorByName('contacto][celular', $this->t('El número celular debe tener entre 6 y 15 dígitos.'));
        return;
      }

      if ($celular_full === '') {
        $celular_full = $indicativo . $celular_nacional;
      }

      if (!preg_match('/^\+\d{7,18}$/', $celular_full)) {
        $form_state->setErrorByName('contacto][celular', $this->t('El teléfono celular completo no tiene un formato internacional válido.'));
        return;
      }
    }

    $profesional = (array) ($values['profesional'] ?? []);
    $tiene_subespecialidad = in_array('field_tiene_subespecialidad', $requested_fields, TRUE)
      ? (int) ($profesional['tiene_subespecialidad'] ?? 0)
      : $this->getBooleanValue($node, 'field_tiene_subespecialidad');

    if (
      $tiene_subespecialidad === 1
      && in_array('field_subespecialidad_cual', $requested_fields, TRUE)
      && empty($profesional['subespecialidad_cual'])
    ) {
      $form_state->setErrorByName('profesional][subespecialidad_cual', $this->t('Debe seleccionar la subespecialidad.'));
      return;
    }

    $unchanged_fields = $this->getUnchangedRequestedFields($node, $values, $requested_fields);

    if (!empty($unchanged_fields)) {
      foreach ($unchanged_fields as $field_name) {
        $form_path = $this->getFormPathByFieldName($field_name);
        $error_name = $form_path ? implode('][', $form_path) : 'form';

        $message = $this->isFileFieldName($field_name)
          ? $this->t('Debe cargar un archivo diferente al que ya estaba registrado.')
          : $this->t('Debe modificar este campo para responder la aclaración.');

        $form_state->setErrorByName($error_name, $message);
      }

      return;
    }

    $confirmacion = (array) ($values['confirmacion'] ?? []);
    if (empty($confirmacion['ajustes_realizados'])) {
      $form_state->setErrorByName('confirmacion][ajustes_realizados', $this->t('Debe marcar la opción Ajustes realizados para reenviar la solicitud.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $form_state->get('solicitud_node');

    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      $this->messenger()->addError($this->t('No fue posible actualizar la solicitud.'));
      $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
      return;
    }

    $active_clarification = $this->clarificationManager->getActiveClarification((int) $node->id());

    if (!$active_clarification) {
      $this->messenger()->addError($this->t('No existe una aclaración activa para esta solicitud.'));
      $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
      return;
    }

    $requested_fields = $this->filterRequestedFieldsForNode(
      $node,
      (array) ($active_clarification['requested_fields'] ?? [])
    );

    if (empty($requested_fields)) {
      $this->messenger()->addError($this->t('No hay campos válidos configurados para esta aclaración.'));
      $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
      return;
    }

    $values = (array) $form_state->getValues();
    $changed_fields = $this->getChangedRequestedFields($node, $values, $requested_fields);

    $general = (array) ($values['general'] ?? []);
    $contacto = (array) ($values['contacto'] ?? []);
    $aspirante_email = trim((string) $this->currentUser()->getEmail());
    $profesional = (array) ($values['profesional'] ?? []);
    $adjuntos = (array) ($values['adjuntos'] ?? []);

    $celular_indicativo = trim((string) ($contacto['celular_indicativo'] ?? '+57'));
    $celular_nacional = preg_replace('/\D+/', '', (string) ($contacto['celular'] ?? ''));
    $celular_full = trim((string) ($contacto['celular_full'] ?? ''));

    if ($celular_full === '' && $celular_nacional !== '') {
      $celular_full = $celular_indicativo . $celular_nacional;
    }

    $this->setFieldIfRequested($node, $requested_fields, 'field_tipo_asociado', ['target_id' => (int) ($general['tipo_asociado'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_nombre1', $general['nombre1'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_nombre2', $general['nombre2'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_apellido1', $general['apellido1'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_apellido2', $general['apellido2'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_fecha_nacimiento', $general['fecha_nacimiento'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_estado_civil', ['target_id' => (int) ($general['estado_civil'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_sexo', ['target_id' => (int) ($general['sexo'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_tipo_documento', ['target_id' => (int) ($general['tipo_documento'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_numero_documento', $general['numero_documento'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_registro_medico', $general['registro_medico'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_pais', ['target_id' => (int) ($general['pais'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_departamento', ['target_id' => (int) ($general['departamento'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_ciudad_ejercicio', ['target_id' => (int) ($general['ciudad_ejercicio'] ?? 0)]);

    $this->setFieldIfRequested($node, $requested_fields, 'field_correspondencia_fisica', $contacto['direccion'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_direccion_institucional', $contacto['correspondencia_fisica'] ?? '');
    $this->setFieldIfRequested($node, $requested_fields, 'field_celular', $celular_full);

    if (in_array('field_lugar_correspondencia', $requested_fields, TRUE)) {
      if (!empty($contacto['lugar_correspondencia'])) {
        $node->set('field_lugar_correspondencia', ['target_id' => (int) $contacto['lugar_correspondencia']]);
      } else {
        $node->set('field_lugar_correspondencia', NULL);
      }
    }

    $this->setFieldIfRequested($node, $requested_fields, 'field_facultad_pregrado', ['target_id' => (int) ($profesional['facultad_pregrado'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_pais_pregrado', ['target_id' => (int) ($profesional['pais_pregrado'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_titulo_universitario', ['target_id' => (int) ($profesional['titulo_universitario'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_universidad_residencia', ['target_id' => (int) ($profesional['universidad_residencia'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_pais_residencia', ['target_id' => (int) ($profesional['pais_residencia'] ?? 0)]);
    $this->setFieldIfRequested($node, $requested_fields, 'field_tiene_subespecialidad', !empty($profesional['tiene_subespecialidad']) ? 1 : 0);

    if (in_array('field_subespecialidad_cual', $requested_fields, TRUE)) {
      if (!empty($profesional['subespecialidad_cual'])) {
        $node->set('field_subespecialidad_cual', ['target_id' => (int) $profesional['subespecialidad_cual']]);
      } else {
        $node->set('field_subespecialidad_cual', NULL);
      }
    }

    $file_map = [
      'adj_carta_1' => 'field_adj_carta_1',
      'adj_carta_2' => 'field_adj_carta_2',
      'adj_rut' => 'field_adj_rut',
      'adj_id' => 'field_adj_id',
      'adj_carta_ingreso' => 'field_adj_carta_ingreso',
      'adj_hv' => 'field_adj_hv',
      'adj_diploma_medico' => 'field_adj_diploma_medico',
      'adj_diploma_dermatologo' => 'field_adj_diploma_dermatologo',
      'adj_rethus' => 'field_adj_rethus',
      'adj_aut_verificacion' => 'field_adj_aut_verificacion',
      'adj_cert_publicacion' => 'field_adj_cert_publicacion',
      'adj_acta_grado_medico' => 'field_adj_acta_grado_medico',
      'adj_acta_grado_dermatologo' => 'field_adj_acta_grado_dermatologo',
      'adj_convalidacion' => 'field_adj_convalidacion',
      'adj_pensum_academico' => 'field_adj_pensum_academico',
      'adj_notas_dermatologia' => 'field_adj_notas_dermatologia',
    ];

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');

    foreach ($file_map as $form_key => $field_name) {
      if (!$node->hasField($field_name)) {
        continue;
      }

      if (!in_array($field_name, $requested_fields, TRUE)) {
        continue;
      }

      if (empty($adjuntos[$form_key]) || empty($adjuntos[$form_key][0])) {
        if ($field_name === 'field_adj_cert_publicacion') {
          $node->set($field_name, NULL);
        }
        continue;
      }

      $fid = (int) $adjuntos[$form_key][0];
      $file = $file_storage->load($fid);

      if ($file) {
        $file->setPermanent();
        $file->save();

        $node->set($field_name, [
          'target_id' => $fid,
        ]);
      }
    }

    if (method_exists($node, 'setNewRevision')) {
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node->setRevisionUserId((int) $this->currentUser()->id());

      if (method_exists($node, 'setRevisionLogMessage')) {
        $node->setRevisionLogMessage('Ajustes realizados por el aspirante desde el formulario de aclaración.');
      }
    }

    $node->save();

    $estado_en_tramite_tid = $this->getTidByName('estado_solicitud_ingreso', 'En trámite');

    \Drupal::service('asocolderma_inscription.solicitud_state_manager')->transitionByTid(
      $node,
      $estado_en_tramite_tid,
      'aspirante_ajustes_realizados',
      'El aspirante corrigió los campos solicitados y reenvió la solicitud al flujo institucional.',
      [
        'accion' => 'ajustes_realizados',
        'form_id' => $this->getFormId(),
        'clarification_id' => (int) $active_clarification['id'],
        'requested_fields' => $requested_fields,
        'changed_fields' => $changed_fields,
      ]
    );

    $this->clarificationManager->markAnswered(
      (int) $active_clarification['id'],
      (int) $this->currentUser()->id(),
      [
        'form_id' => $this->getFormId(),
        'requested_fields' => $requested_fields,
        'changed_fields' => $changed_fields,
        'answered_from' => 'aspirante_clarification_form',
      ]
    );

    $this->messenger()->addStatus($this->t('Los ajustes fueron guardados correctamente. La solicitud volvió al estado En trámite para continuar la revisión institucional.'));

    $form_state->setRedirect('asocolderma_inscription.user_zone_requests');
  }

  private function buildFileField(
    array &$form,
    NodeInterface $node,
    string $form_key,
    string $field_name,
    string $title,
    string $upload_location,
    string $extensions,
    bool $required
  ): void {
    $description = '';

    if (!$node->get($field_name)->isEmpty()) {
      $description = $this->t('Debe cargar un archivo nuevo si este campo fue solicitado en la aclaración.');
    }

    $form['adjuntos'][$form_key] = [
      '#type' => 'managed_file',
      '#title' => $this->t($title),
      '#required' => FALSE,
      '#description' => $description,
      '#upload_location' => $upload_location,
      '#default_value' => $this->getFileDefaultValue($node, $field_name),
      '#upload_validators' => [
        'file_validate_extensions' => [$extensions],
        'file_validate_size' => [10 * 1024 * 1024],
      ],
    ];
  }

  private function getTaxonomyOptions(string $vid): array
  {
    $options = [];

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vid, 0, NULL, TRUE);

    foreach ($terms as $term) {
      $options[(int) $term->id()] = $term->label();
    }

    return $options;
  }

  private function getTidByName(string $vid, string $name): int
  {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => $vid,
        'name' => $name,
      ]);

    if (empty($terms)) {
      throw new \RuntimeException(sprintf('No existe el término "%s" en el vocabulario "%s".', $name, $vid));
    }

    $term = reset($terms);
    return (int) $term->id();
  }

  private function getStringValue(NodeInterface $node, string $field_name): string
  {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    return (string) $node->get($field_name)->value;
  }

  private function getBooleanValue(NodeInterface $node, string $field_name): int
  {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return 0;
    }

    return !empty($node->get($field_name)->value) ? 1 : 0;
  }

  private function getTargetId(NodeInterface $node, string $field_name): ?int
  {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    return (int) $node->get($field_name)->target_id;
  }

  private function getFileDefaultValue(NodeInterface $node, string $field_name): array
  {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return [];
    }

    $target_id = $node->get($field_name)->target_id;

    return $target_id ? [(int) $target_id] : [];
  }

  private function splitPhone(string $phone): array
  {
    $phone = trim($phone);

    if ($phone === '') {
      return [
        'indicativo' => '+57',
        'national' => '',
        'full' => '',
      ];
    }

    $clean = preg_replace('/[^\d\+]/', '', $phone);

    if (str_starts_with($clean, '+57')) {
      return [
        'indicativo' => '+57',
        'national' => substr($clean, 3),
        'full' => $clean,
      ];
    }

    if (preg_match('/^(\+\d{1,4})(\d{6,15})$/', $clean, $matches)) {
      return [
        'indicativo' => $matches[1],
        'national' => $matches[2],
        'full' => $clean,
      ];
    }

    return [
      'indicativo' => '+57',
      'national' => preg_replace('/\D+/', '', $clean),
      'full' => $clean,
    ];
  }

  private function getLatestClarificationComment(NodeInterface $node): string
  {
    $pending_clarification_tid = $this->getTidByName('estado_solicitud_ingreso', 'Pendiente aclaración');

    $record = \Drupal::database()
      ->select('asocolderma_solicitud_historial', 'h')
      ->fields('h', ['comment'])
      ->condition('h.solicitud_nid', (int) $node->id())
      ->condition('h.to_tid', $pending_clarification_tid)
      ->orderBy('h.created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (empty($record['comment'])) {
      return '';
    }

    return trim((string) $record['comment']);
  }

  private function isAdultBirthDate(?string $birth_date): bool
  {
    $birth_date = trim((string) $birth_date);

    if ($birth_date === '') {
      return FALSE;
    }

    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $birth_date);
    $errors = \DateTimeImmutable::getLastErrors();

    if ($date === FALSE || ($errors !== FALSE && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
      return FALSE;
    }

    $today = new \DateTimeImmutable('today');
    $minimum_birth_date = $today->modify('-18 years');

    return $date <= $minimum_birth_date;
  }

  private function applyClarificationRestrictions(array &$form, array $requested_fields): void
  {
    $field_form_map = $this->getFieldFormMap();

    foreach ($field_form_map as $field_name => $form_path) {
      [$section, $key] = $form_path;

      if (!isset($form[$section][$key])) {
        continue;
      }

      $allowed = in_array($field_name, $requested_fields, TRUE);

      $form[$section][$key]['#access'] = $allowed;

      if (!$allowed) {
        $form[$section][$key]['#required'] = FALSE;
      } elseif ($this->isFileFieldName($field_name)) {
        $form[$section][$key]['#required'] = FALSE;
      }
    }

    $show_phone = in_array('field_celular', $requested_fields, TRUE);
    if (isset($form['contacto']['celular_indicativo'])) {
      $form['contacto']['celular_indicativo']['#access'] = $show_phone;
    }
    if (isset($form['contacto']['celular_full'])) {
      $form['contacto']['celular_full']['#access'] = $show_phone;
    }
    if (isset($form['contacto']['email'])) {
      $form['contacto']['email']['#access'] = FALSE;
    }

    foreach (['general', 'contacto', 'profesional', 'adjuntos'] as $section) {
      $section_has_fields = FALSE;

      foreach ($field_form_map as $field_name => $form_path) {
        if ($form_path[0] === $section && in_array($field_name, $requested_fields, TRUE)) {
          $section_has_fields = TRUE;
          break;
        }
      }

      if (isset($form[$section])) {
        $form[$section]['#access'] = $section_has_fields;
        $form[$section]['#open'] = $section_has_fields;
      }
    }
  }

  private function filterRequestedFieldsForNode(NodeInterface $node, array $requested_fields): array
  {
    $allowed_fields = array_keys($this->getFieldFormMap());
    $filtered = [];

    foreach ($requested_fields as $field_name) {
      $field_name = trim((string) $field_name);

      if ($field_name === '') {
        continue;
      }

      if (!in_array($field_name, $allowed_fields, TRUE)) {
        continue;
      }

      if (!$node->hasField($field_name)) {
        continue;
      }

      $filtered[] = $field_name;
    }

    return array_values(array_unique($filtered));
  }

  private function getFieldFormMap(): array
  {
    return [
      'field_tipo_asociado' => ['general', 'tipo_asociado'],
      'field_nombre1' => ['general', 'nombre1'],
      'field_nombre2' => ['general', 'nombre2'],
      'field_apellido1' => ['general', 'apellido1'],
      'field_apellido2' => ['general', 'apellido2'],
      'field_fecha_nacimiento' => ['general', 'fecha_nacimiento'],
      'field_estado_civil' => ['general', 'estado_civil'],
      'field_sexo' => ['general', 'sexo'],
      'field_tipo_documento' => ['general', 'tipo_documento'],
      'field_numero_documento' => ['general', 'numero_documento'],
      'field_registro_medico' => ['general', 'registro_medico'],
      'field_pais' => ['general', 'pais'],
      'field_departamento' => ['general', 'departamento'],
      'field_ciudad_ejercicio' => ['general', 'ciudad_ejercicio'],

      'field_correspondencia_fisica' => ['contacto', 'direccion'],
      'field_direccion_institucional' => ['contacto', 'correspondencia_fisica'],
      'field_celular' => ['contacto', 'celular'],
      'field_lugar_correspondencia' => ['contacto', 'lugar_correspondencia'],

      'field_facultad_pregrado' => ['profesional', 'facultad_pregrado'],
      'field_pais_pregrado' => ['profesional', 'pais_pregrado'],
      'field_titulo_universitario' => ['profesional', 'titulo_universitario'],
      'field_universidad_residencia' => ['profesional', 'universidad_residencia'],
      'field_pais_residencia' => ['profesional', 'pais_residencia'],
      'field_tiene_subespecialidad' => ['profesional', 'tiene_subespecialidad'],
      'field_subespecialidad_cual' => ['profesional', 'subespecialidad_cual'],

      'field_adj_carta_1' => ['adjuntos', 'adj_carta_1'],
      'field_adj_carta_2' => ['adjuntos', 'adj_carta_2'],
      'field_adj_rut' => ['adjuntos', 'adj_rut'],
      'field_adj_id' => ['adjuntos', 'adj_id'],
      'field_adj_carta_ingreso' => ['adjuntos', 'adj_carta_ingreso'],
      'field_adj_hv' => ['adjuntos', 'adj_hv'],
      'field_adj_diploma_medico' => ['adjuntos', 'adj_diploma_medico'],
      'field_adj_diploma_dermatologo' => ['adjuntos', 'adj_diploma_dermatologo'],
      'field_adj_rethus' => ['adjuntos', 'adj_rethus'],
      'field_adj_aut_verificacion' => ['adjuntos', 'adj_aut_verificacion'],
      'field_adj_cert_publicacion' => ['adjuntos', 'adj_cert_publicacion'],
      'field_adj_acta_grado_medico' => ['adjuntos', 'adj_acta_grado_medico'],
      'field_adj_acta_grado_dermatologo' => ['adjuntos', 'adj_acta_grado_dermatologo'],
      'field_adj_convalidacion' => ['adjuntos', 'adj_convalidacion'],
      'field_adj_pensum_academico' => ['adjuntos', 'adj_pensum_academico'],
      'field_adj_notas_dermatologia' => ['adjuntos', 'adj_notas_dermatologia'],
    ];
  }

  private function getFormPathByFieldName(string $field_name): ?array
  {
    $map = $this->getFieldFormMap();

    return $map[$field_name] ?? NULL;
  }

  private function isFileFieldName(string $field_name): bool
  {
    return str_starts_with($field_name, 'field_adj_');
  }

  private function setFieldIfRequested(NodeInterface $node, array $requested_fields, string $field_name, mixed $value): void
  {
    if (!in_array($field_name, $requested_fields, TRUE)) {
      return;
    }

    if (!$node->hasField($field_name)) {
      return;
    }

    $node->set($field_name, $value);
  }

  private function getChangedRequestedFields(NodeInterface $node, array $values, array $requested_fields): array
  {
    $changed = [];

    foreach ($requested_fields as $field_name) {
      $current_value = $this->getComparableCurrentFieldValue($node, $field_name);
      $submitted_value = $this->getComparableSubmittedFieldValue($field_name, $values);

      if ($current_value !== $submitted_value) {
        $changed[] = $field_name;
      }
    }

    return $changed;
  }

  private function getUnchangedRequestedFields(NodeInterface $node, array $values, array $requested_fields): array
  {
    $changed = $this->getChangedRequestedFields($node, $values, $requested_fields);

    return array_values(array_diff($requested_fields, $changed));
  }

  private function getComparableCurrentFieldValue(NodeInterface $node, string $field_name): string
  {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    $field = $node->get($field_name);
    $definition = $field->getFieldDefinition();
    $type = $definition->getType();

    if (in_array($type, ['entity_reference', 'file', 'image'], TRUE)) {
      return (string) ((int) ($field->target_id ?? 0));
    }

    if ($type === 'boolean') {
      return !empty($field->value) ? '1' : '0';
    }

    return trim((string) ($field->value ?? ''));
  }

  private function getComparableSubmittedFieldValue(string $field_name, array $values): string
  {
    $path = $this->getFormPathByFieldName($field_name);

    if (!$path) {
      return '';
    }

    [$section, $key] = $path;

    if ($field_name === 'field_celular') {
      $contacto = (array) ($values['contacto'] ?? []);
      $celular_full = trim((string) ($contacto['celular_full'] ?? ''));

      if ($celular_full !== '') {
        return $celular_full;
      }

      $indicativo = trim((string) ($contacto['celular_indicativo'] ?? '+57'));
      $celular_nacional = preg_replace('/\D+/', '', (string) ($contacto['celular'] ?? ''));

      return $celular_nacional !== '' ? $indicativo . $celular_nacional : '';
    }

    $value = $values[$section][$key] ?? '';

    if ($this->isFileFieldName($field_name)) {
      if (is_array($value)) {
        return (string) ((int) ($value[0] ?? 0));
      }

      return (string) ((int) $value);
    }

    if (is_array($value)) {
      if (isset($value['target_id'])) {
        return (string) ((int) $value['target_id']);
      }

      $first = reset($value);
      return is_scalar($first) ? trim((string) $first) : '';
    }

    if ($field_name === 'field_tiene_subespecialidad') {
      return !empty($value) ? '1' : '0';
    }

    return trim((string) $value);
  }

  private function isManagedFileInternalSubmit(FormStateInterface $form_state): bool
  {
    $trigger = $form_state->getTriggeringElement();

    if (empty($trigger) || !is_array($trigger)) {
      return FALSE;
    }

    $array_parents = $trigger['#array_parents'] ?? [];
    $parents = $trigger['#parents'] ?? [];
    $name = (string) ($trigger['#name'] ?? '');
    $value = (string) ($trigger['#value'] ?? '');

    $haystack = mb_strtolower(implode(' ', array_merge($array_parents, $parents)) . ' ' . $name . ' ' . $value);

    return str_contains($haystack, 'upload_button')
      || str_contains($haystack, 'remove_button')
      || str_contains($haystack, 'subir')
      || str_contains($haystack, 'upload')
      || str_contains($haystack, 'remover')
      || str_contains($haystack, 'remove');
  }
}
