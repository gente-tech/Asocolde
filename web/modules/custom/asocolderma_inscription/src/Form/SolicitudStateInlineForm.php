<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\asocolderma_inscription\Service\SolicitudStateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

final class SolicitudStateInlineForm extends FormBase {

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

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_state_manager'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'asocolderma_solicitud_state_inline_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL): array {
    if (!$this->currentUser()->hasRole('secretaria_general') && !$this->currentUser()->hasRole('coordinacion_administrativa')) {
      throw new AccessDeniedHttpException();
    }

    $node = $this->etm->getStorage('node')->load((int) $nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      throw new AccessDeniedHttpException();
    }

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);
    $allowed_names = $this->getAllowedStateNamesByCurrentState($current_name);

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => (int) $node->id(),
    ];

    $current_request = $this->requestStack->getCurrentRequest();
    $destination = $current_request ? $current_request->getRequestUri() : '/';

    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => $destination,
    ];

    if (empty($allowed_names)) {
      $form['state_text'] = [
        '#markup' => '<span>' . ($current_name ?: '-') . '</span>',
      ];
      $form['#cache']['max-age'] = 0;
      return $form;
    }

    $options = $this->resolveTidsByNames('estado_solicitud_ingreso', $allowed_names);

    $form['state_tid'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => $options,
      '#default_value' => NULL,
      '#empty_option' => $this->t('- Seleccione -'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancelar'),
      '#limit_validation_errors' => [],
      '#submit' => ['::cancelForm'],
    ];

    $form['#cache']['max-age'] = 0;

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasRole('secretaria_general') && !$this->currentUser()->hasRole('coordinacion_administrativa')) {
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

    $this->stateManager->transitionByTid(
      $node,
      $to_tid,
      'node_view_inline_select',
      'Cambio de estado desde el detalle',
      [
        'nid' => $nid,
        'from_state' => $current_name,
        'to_tid' => $to_tid,
        'actor_roles' => $this->currentUser()->getRoles(),
      ]
    );

    $this->messenger()->addStatus($this->t('Estado actualizado.'));
    $form_state->setRedirectUrl(Url::fromUserInput($destination ?: '/'));
  }

  private function getAllowedStateNamesByCurrentState(?string $current_name): array {
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

  private function resolveTidsByNames(string $vid, array $names): array {
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

  private function resolveTermNameByTid(int $tid): ?string {
    if ($tid <= 0) {
      return NULL;
    }

    $term = $this->etm->getStorage('taxonomy_term')->load($tid);
    return $term ? (string) $term->getName() : NULL;
  }

  public function cancelForm(array &$form, FormStateInterface $form_state): void {
    $destination = (string) $form_state->getValue('destination');
    $form_state->setRedirectUrl(Url::fromUserInput($destination ?: '/'));
  }

}