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

final class SolicitudStateInlineForm extends FormBase
{

  protected EntityTypeManagerInterface $etm;
  protected SolicitudStateManager $stateManager;

  public function __construct(
    EntityTypeManagerInterface $etm,
    SolicitudStateManager $stateManager,
    RequestStack $request_stack,
  ) {
    $this->etm = $etm;
    $this->stateManager = $stateManager;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_state_manager'),
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
    $requires_motivo = $selected_tid > 0 ? $this->termRequiresMotivo($selected_tid) : FALSE;

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

    if ($requires_motivo) {
      $form['state_wrapper']['motivo'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Motivo'),
        '#description' => $this->t('Este estado requiere registrar un motivo administrativo. Mínimo 10 caracteres.'),
        '#required' => TRUE,
        '#default_value' => (string) ($form_state->getValue('motivo') ?? ''),
        '#rows' => 4,
      ];
    } else {
      $form['state_wrapper']['motivo'] = [
        '#type' => 'hidden',
        '#value' => '',
      ];
    }

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

    if ($this->termRequiresMotivo($to_tid)) {
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

    $dialog_content = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['asocolderma-confirm-dialog'],
      ],
      'message' => [
        '#markup' => '<p>' . $this->t('¿Está seguro de cambiar el estado de esta solicitud?') . '</p>',
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

    $motivo = trim((string) $form_state->getValue('motivo'));
    $requires_motivo = $this->termRequiresMotivo($to_tid);

    $comment = $requires_motivo
      ? $motivo
      : 'Cambio de estado desde modal de confirmación';

    $this->stateManager->transitionByTid(
      $node,
      $to_tid,
      'node_view_inline_select',
      $comment,
      [
        'nid' => $nid,
        'from_state' => $current_name,
        'to_tid' => $to_tid,
        'actor_roles' => $this->currentUser()->getRoles(),
        'requires_motivo' => $requires_motivo,
      ]
    );

    $this->messenger()->addStatus($this->t('Estado actualizado.'));
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
        'Aprobado Asamblea G.' => ['Pendiente pago de ingreso'],
        'Pendiente pago de ingreso' => ['Pendiente firma de documentos'],
        'Pendiente firma de documentos' => [],
        'Documentos firmados' => ['Miembro activo'],
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
        $options[(int) $term->id()] = $term->getName();
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
}
