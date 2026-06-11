<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudStateManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\asocolderma_inscription\Service\SolicitudClarificationManager;

final class SolicitudStateInlineForm extends FormBase
{

  protected EntityTypeManagerInterface $etm;
  protected SolicitudStateManager $stateManager;
  protected SolicitudClarificationManager $clarificationManager;

  public function __construct(
    EntityTypeManagerInterface $etm,
    SolicitudStateManager $stateManager,
    SolicitudClarificationManager $clarification_manager,
    RequestStack $request_stack,
  ) {
    $this->etm = $etm;
    $this->stateManager = $stateManager;
    $this->clarificationManager = $clarification_manager;
    $this->requestStack = $request_stack;
  }
  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_state_manager'),
      $container->get('asocolderma_inscription.solicitud_clarification_manager'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string
  {
    return 'asocolderma_solicitud_state_inline_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL): array
  {
    if (
      !$this->currentUser()->hasRole('secretaria_general') &&
      !$this->currentUser()->hasRole('coordinacion_administrativa')
    ) {
      throw new AccessDeniedHttpException();
    }

    $node = $this->etm->getStorage('node')->load((int) $nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      throw new AccessDeniedHttpException();
    }

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);
    $allowed_names = $this->getAllowedStateNamesByCurrentState($current_name);

    $request = $this->requestStack->getCurrentRequest();
    $session = $request ? $request->getSession() : NULL;
    $destination = $session ? $session->get('asocolderma_inscription.solicitud_return_url', '/') : '/';

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'asocolderma_inscription/solicitud_state_confirm';

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => (int) $node->id(),
    ];

    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => $destination,
    ];

    if (empty($allowed_names)) {
      $form['#access'] = FALSE;
      $form['#cache']['max-age'] = 0;
      return $form;
    }

    $options = $this->resolveTidsByNames('estado_solicitud_ingreso', $allowed_names);

    $selected_tid = (int) ($form_state->getValue('state_tid') ?? 0);
    $selected_state_name = $selected_tid > 0 ? $this->resolveTermNameByTid($selected_tid) : NULL;
    $is_pending_clarification = $this->isPendingClarificationState($selected_state_name);
    $requires_motivo = $selected_tid > 0 && !$is_pending_clarification
      ? $this->termRequiresMotivo($selected_tid)
      : FALSE;

    $form['state_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'asocolderma-state-wrapper',
      ],
    ];

    $form['state_wrapper']['state_tid'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => $options,
      '#default_value' => $selected_tid ?: NULL,
      '#empty_option' => $this->t('- Seleccione -'),
      '#required' => TRUE,
      '#parents' => ['state_tid'],
      '#ajax' => [
        'callback' => '::refreshStateFields',
        'wrapper' => 'asocolderma-state-wrapper',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Validando estado...'),
        ],
      ],
    ];

    $form['state_wrapper']['motivo'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo'),
      '#description' => $this->t('Este estado requiere registrar un motivo administrativo. Mínimo 10 caracteres.'),
      '#required' => $requires_motivo,
      '#default_value' => (string) ($form_state->getValue('motivo') ?? ''),
      '#rows' => 4,
      '#parents' => ['motivo'],
      '#access' => $requires_motivo,
    ];

    $form['state_wrapper']['clarification_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Campos que debe corregir el aspirante'),
      '#description' => $this->t('Seleccione únicamente los campos que el aspirante podrá editar durante la aclaración.'),
      '#options' => $this->getClarificationFieldOptions($node),
      '#default_value' => $this->getSelectedClarificationFields($form_state),
      '#parents' => ['clarification_fields'],
      '#access' => $is_pending_clarification,
    ];

    $form['state_wrapper']['clarification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mensaje para el aspirante'),
      '#description' => $this->t('Explique con claridad qué debe corregir o adjuntar nuevamente. Mínimo 10 caracteres.'),
      '#required' => $is_pending_clarification,
      '#default_value' => (string) ($form_state->getValue('clarification_message') ?? ''),
      '#rows' => 5,
      '#parents' => ['clarification_message'],
      '#access' => $is_pending_clarification,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['open_confirm'] = [
      '#type' => 'button',
      '#value' => $this->t('Guardar'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::openConfirmModal',
        'event' => 'click',
        'progress' => ['type' => 'none'],
        'disable-refocus' => TRUE,
      ],
    ];

    $form['actions']['hidden_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar oculto'),
      '#attributes' => [
        'style' => 'display:none;',
        'id' => 'asocolderma-solicitud-hidden-submit',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancelar'),
      '#attributes' => [
        'id' => 'asocolderma-cancel-button',
        'class' => ['button'],
        'data-destination' => $destination ?: '/',
      ],
    ];

    $form['#cache']['max-age'] = 0;

    return $form;
  }

  public function refreshStateFields(array &$form, FormStateInterface $form_state): array
  {
    $form_state->setRebuild(TRUE);
    return $form['state_wrapper'];
  }

  public function openConfirmModal(array &$form, FormStateInterface $form_state): AjaxResponse
  {
    if (
      !$this->currentUser()->hasRole('secretaria_general') &&
      !$this->currentUser()->hasRole('coordinacion_administrativa')
    ) {
      throw new AccessDeniedHttpException();
    }

    $response = new AjaxResponse();

    $nid = (int) $form_state->getValue('nid');
    $to_tid = (int) $form_state->getValue('state_tid');
    $destination = (string) $form_state->getValue('destination');

    $node = $this->etm->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      return $response;
    }

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);
    $allowed_names = $this->getAllowedStateNamesByCurrentState($current_name);
    $allowed_options = $this->resolveTidsByNames('estado_solicitud_ingreso', $allowed_names);

    if (empty($to_tid) || !isset($allowed_options[$to_tid])) {
      $this->messenger()->addError($this->t('Debe seleccionar un estado válido.'));
      return $response;
    }

    $to_state_name = $this->resolveTermNameByTid($to_tid);
    $is_pending_clarification = $this->isPendingClarificationState($to_state_name);

    if ($is_pending_clarification) {
      $requested_fields = $this->getSelectedClarificationFields($form_state);
      $clarification_message = trim((string) $form_state->getValue('clarification_message'));

      if (empty($requested_fields)) {
        $this->messenger()->addError($this->t('Debe seleccionar al menos un campo para solicitar aclaración.'));
        return $response;
      }

      if ($clarification_message === '') {
        $this->messenger()->addError($this->t('Debe escribir el mensaje de aclaración para el aspirante.'));
        return $response;
      }

      if (mb_strlen($clarification_message) < 10) {
        $this->messenger()->addError($this->t('El mensaje de aclaración debe tener mínimo 10 caracteres.'));
        return $response;
      }
    } elseif ($this->termRequiresMotivo($to_tid)) {
      $motivo = trim((string) $form_state->getValue('motivo'));

      if ($motivo === '') {
        $this->messenger()->addError($this->t('Debes registrar un motivo para este estado.'));
        return $response;
      }

      if (mb_strlen($motivo) < 10) {
        $this->messenger()->addError($this->t('El motivo debe tener mínimo 10 caracteres.'));
        return $response;
      }
    }

    $to_label = (string) ($allowed_options[$to_tid] ?? '');

    if ($to_label === '') {
      $to_label = $this->resolveTermNameByTid($to_tid) ?? '';
    }

    $dialog_content = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['asocolderma-confirm-dialog'],
      ],
      'message' => [
        '#markup' => '<p>' . $this->t('¿Está seguro de cambiar el estado de esta solicitud a <strong>@estado</strong>?', [
          '@estado' => $to_label,
        ]) . '</p>',
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['confirmation-actions']],
        'yes' => [
          '#type' => 'button',
          '#value' => $this->t('Sí'),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'asocolderma-confirm-yes'],
            'id' => 'asocolderma-confirm-yes',
          ],
        ],
        'no' => [
          '#type' => 'button',
          '#value' => $this->t('No'),
          '#attributes' => [
            'class' => ['button', 'asocolderma-confirm-no'],
            'id' => 'asocolderma-confirm-no',
            'data-destination' => $destination ?: '/',
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'asocolderma_inscription/solicitud_state_confirm',
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Confirmar cambio de estado'),
      $dialog_content,
      ['width' => '500']
    ));

    return $response;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    if (
      !$this->currentUser()->hasRole('secretaria_general') &&
      !$this->currentUser()->hasRole('coordinacion_administrativa')
    ) {
      throw new AccessDeniedHttpException();
    }

    $nid = (int) $form_state->getValue('nid');
    $to_tid = (int) $form_state->getValue('state_tid');
    $destination = (string) $form_state->getValue('destination');

    $node = $this->etm->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      $this->messenger()->addError($this->t('No se pudo cargar la solicitud.'));
      $form_state->setRedirectUrl(Url::fromUserInput($destination ?: '/'));
      return;
    }

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);
    $allowed_names = $this->getAllowedStateNamesByCurrentState($current_name);
    $allowed_options = $this->resolveTidsByNames('estado_solicitud_ingreso', $allowed_names);

    if (!isset($allowed_options[$to_tid])) {
      $this->messenger()->addError($this->t('Opción de estado no permitida para el estado actual.'));
      $form_state->setRedirectUrl(Url::fromUserInput($destination ?: '/'));
      return;
    }

    $to_state_name = $this->resolveTermNameByTid($to_tid);
    $is_pending_clarification = $this->isPendingClarificationState($to_state_name);

    $motivo = trim((string) $form_state->getValue('motivo'));
    $clarification_message = trim((string) $form_state->getValue('clarification_message'));
    $requested_fields = $this->getSelectedClarificationFields($form_state);

    $requires_motivo = !$is_pending_clarification && $this->termRequiresMotivo($to_tid);

    $comment = $is_pending_clarification
      ? $clarification_message
      : ($requires_motivo ? $motivo : 'Cambio de estado desde modal de confirmación');

    $metadata = [
      'nid' => $nid,
      'from_state' => $current_name,
      'to_tid' => $to_tid,
      'actor_roles' => $this->currentUser()->getRoles(),
      'requires_motivo' => $requires_motivo,
    ];

    if ($is_pending_clarification) {
      $metadata['clarification_requested_fields'] = $requested_fields;
      $metadata['clarification_message'] = $clarification_message;
    }

    $this->stateManager->transitionByTid(
      $node,
      $to_tid,
      'node_view_inline_select',
      $comment,
      $metadata
    );

    if ($is_pending_clarification) {
      $this->clarificationManager->createClarification(
        $node,
        $requested_fields,
        $clarification_message,
        (int) $this->currentUser()->id()
      );
    }

    $to_label = (string) ($allowed_options[$to_tid] ?? '');

    if ($to_label === '') {
      $to_label = $this->resolveTermNameByTid($to_tid) ?? '';
    }

    $this->messenger()->addStatus($this->t('Estado actualizado a @estado.', [
      '@estado' => $to_label,
    ]));

    $form_state->setRedirectUrl(Url::fromUserInput($destination ?: '/'));
  }

  private function getAllowedStateNamesByCurrentState(?string $current_name): array
  {
    if ($this->currentUser()->hasRole('secretaria_general')) {
      return match ($current_name) {
        'En trámite' => ['Pendiente aclaración', 'Aprobada', 'Rechazada'],
        'Aprobada' => ['Aprobado Junta D.', 'Rechazado Junta D.'],
        'Aprobado Junta D.' => ['Aprobado Asamblea G.', 'Rechazado Asamblea G.'],
        default => [],
      };
    }

    if ($this->currentUser()->hasRole('coordinacion_administrativa')) {
      return match ($current_name) {
        'Aprobado Asamblea G.' => ['Documentos enviados'],
        'Documentos enviados' => ['Pendiente pago de ingreso'],
        'Pendiente pago de ingreso' => ['Activar miembro nuevo'],
        default => [],
      };
    }

    return [];
  }

  private function resolveTidsByNames(string $vid, array $names): array
  {
    $storage = $this->etm->getStorage('taxonomy_term');
    $options = [];

    foreach ($names as $name) {
      $terms = $storage->loadByProperties([
        'vid' => $vid,
        'name' => $name,
      ]);

      if ($terms) {
        $term = reset($terms);
        $label = (string) $term->getName();

        if (
          $vid === 'estado_solicitud_ingreso' &&
          function_exists('asocolderma_inscription_get_state_visual_label_from_term')
        ) {
          $label = \asocolderma_inscription_get_state_visual_label_from_term($term);
        }

        $options[(int) $term->id()] = $label;
      }
    }

    return $options;
  }

  private function resolveTermNameByTid(int $tid): ?string
  {
    if ($tid <= 0) {
      return NULL;
    }

    $term = $this->etm->getStorage('taxonomy_term')->load($tid);
    return $term ? (string) $term->getName() : NULL;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    parent::validateForm($form, $form_state);

    $to_tid = (int) $form_state->getValue('state_tid');

    if ($to_tid <= 0) {
      return;
    }

    $to_state_name = $this->resolveTermNameByTid($to_tid);

    if ($this->isPendingClarificationState($to_state_name)) {
      $requested_fields = $this->getSelectedClarificationFields($form_state);
      $clarification_message = trim((string) $form_state->getValue('clarification_message'));

      if (empty($requested_fields)) {
        $form_state->setErrorByName('clarification_fields', $this->t('Debe seleccionar al menos un campo para solicitar aclaración.'));
        return;
      }

      if ($clarification_message === '') {
        $form_state->setErrorByName('clarification_message', $this->t('Debe escribir el mensaje de aclaración para el aspirante.'));
        return;
      }

      if (mb_strlen($clarification_message) < 10) {
        $form_state->setErrorByName('clarification_message', $this->t('El mensaje de aclaración debe tener mínimo 10 caracteres.'));
      }

      return;
    }

    if (!$this->termRequiresMotivo($to_tid)) {
      return;
    }

    $motivo = trim((string) $form_state->getValue('motivo'));

    if ($motivo === '') {
      $form_state->setErrorByName('motivo', $this->t('Debes registrar un motivo para este estado.'));
      return;
    }

    if (mb_strlen($motivo) < 10) {
      $form_state->setErrorByName('motivo', $this->t('El motivo debe tener mínimo 10 caracteres.'));
    }
  }

  private function termRequiresMotivo(int $tid): bool
  {
    if ($tid <= 0) {
      return FALSE;
    }

    $term = $this->etm->getStorage('taxonomy_term')->load($tid);

    if (!$term) {
      return FALSE;
    }

    if (!$term->hasField('field_motivo') || $term->get('field_motivo')->isEmpty()) {
      return FALSE;
    }

    return (bool) $term->get('field_motivo')->value;
  }

  private function isPendingClarificationState(?string $state_name): bool
  {
    return $this->normalizeStateName($state_name) === 'pendiente aclaracion';
  }

  private function normalizeStateName(?string $state_name): string
  {
    $state_name = trim((string) $state_name);
    $state_name = mb_strtolower($state_name);

    $replacements = [
      'á' => 'a',
      'é' => 'e',
      'í' => 'i',
      'ó' => 'o',
      'ú' => 'u',
      'ü' => 'u',
      'ñ' => 'n',
    ];

    return strtr($state_name, $replacements);
  }

  private function getSelectedClarificationFields(FormStateInterface $form_state): array
  {
    $values = (array) ($form_state->getValue('clarification_fields') ?? []);
    $selected = [];

    foreach ($values as $field_name => $value) {
      if ($value === 0 || $value === '0' || $value === NULL || $value === FALSE || $value === '') {
        continue;
      }

      $field_name = trim((string) $field_name);

      if ($field_name !== '') {
        $selected[] = $field_name;
      }
    }

    $selected = array_values(array_unique($selected));
    sort($selected);

    return $selected;
  }

  private function getClarificationFieldOptions(NodeInterface $node): array
  {
    $candidate_options = [
      'field_tipo_asociado' => $this->t('Tipo de asociado al que aspira'),
      'field_nombre1' => $this->t('Primer nombre'),
      'field_nombre2' => $this->t('Segundo nombre'),
      'field_apellido1' => $this->t('Primer apellido'),
      'field_apellido2' => $this->t('Segundo apellido'),
      'field_fecha_nacimiento' => $this->t('Fecha de nacimiento'),
      'field_estado_civil' => $this->t('Estado civil'),
      'field_sexo' => $this->t('Sexo'),
      'field_tipo_documento' => $this->t('Tipo de documento'),
      'field_numero_documento' => $this->t('Número de documento'),
      'field_registro_medico' => $this->t('Registro médico'),
      'field_pais' => $this->t('País'),
      'field_departamento' => $this->t('Departamento'),
      'field_ciudad_ejercicio' => $this->t('Ciudad de ejercicio'),

      'field_correspondencia_fisica' => $this->t('Dirección física principal'),
      'field_direccion_institucional' => $this->t('Dirección institucional'),
      'field_celular' => $this->t('Teléfono celular de contacto'),
      'field_lugar_correspondencia' => $this->t('Lugar de correspondencia'),

      'field_facultad_pregrado' => $this->t('Facultad de medicina – Pregrado'),
      'field_pais_pregrado' => $this->t('País donde realizó el pregrado en medicina'),
      'field_titulo_universitario' => $this->t('Título universitario'),
      'field_universidad_residencia' => $this->t('Universidad de residencia'),
      'field_pais_residencia' => $this->t('País donde realizó la residencia'),
      'field_tiene_subespecialidad' => $this->t('Tiene subespecialidad'),
      'field_subespecialidad_cual' => $this->t('Subespecialidad'),

      'field_adj_carta_1' => $this->t('Adjunto: Carta 1'),
      'field_adj_carta_2' => $this->t('Adjunto: Carta 2'),
      'field_adj_rut' => $this->t('Adjunto: RUT'),
      'field_adj_id' => $this->t('Adjunto: Documento de identidad'),
      'field_adj_carta_ingreso' => $this->t('Adjunto: Carta de solicitud de ingreso'),
      'field_adj_hv' => $this->t('Adjunto: Hoja de vida'),
      'field_adj_diploma_medico' => $this->t('Adjunto: Diploma médico'),
      'field_adj_diploma_dermatologo' => $this->t('Adjunto: Diploma dermatólogo'),
      'field_adj_rethus' => $this->t('Adjunto: RETHUS'),
      'field_adj_aut_verificacion' => $this->t('Adjunto: Autorización de verificación'),
      'field_adj_cert_publicacion' => $this->t('Adjunto: Certificación de publicaciones'),
      'field_adj_acta_grado_medico' => $this->t('Adjunto: Acta de grado como médico general'),
      'field_adj_acta_grado_dermatologo' => $this->t('Adjunto: Acta de grado como dermatólogo'),
      'field_adj_convalidacion' => $this->t('Adjunto: Copia de la resolución de convalidación'),
      'field_adj_pensum_academico' => $this->t('Adjunto: Copia del pénsum académico'),
      'field_adj_notas_dermatologia' => $this->t('Adjunto: Notas de especialización en dermatología'),
    ];

    $options = [];

    foreach ($candidate_options as $field_name => $label) {
      if ($node->hasField($field_name)) {
        $options[$field_name] = $label;
      }
    }

    return $options;
  }
}
