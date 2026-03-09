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

final class SolicitudStateInlineForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly SolicitudStateManager $stateManager,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_state_manager'),
    );
  }

  public function getFormId(): string {
    return 'asocolderma_solicitud_state_inline_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL): array {
    if (!$this->currentUser()->hasRole('secretaria_general')) {
      throw new AccessDeniedHttpException();
    }

    $node = $this->etm->getStorage('node')->load((int) $nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      throw new AccessDeniedHttpException();
    }

    $vid = 'estado_solicitud_ingreso';

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);

    if ($current_name === 'Aprobada') {
      $allowed_names = ['Rechazado Junta D.', 'Aprobado Junta D.'];
    } else {
      $allowed_names = ['Pendiente aclaración', 'Aprobada', 'Rechazada'];
    }

    $options = $this->resolveTidsByNames($vid, $allowed_names);

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => (int) $node->id(),
    ];

    $form['state_tid'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => $options,
      '#default_value' => $current_tid ?: NULL,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar'),
      '#button_type' => 'primary',
    ];

    $form['#cache']['max-age'] = 0;

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasRole('secretaria_general')) {
      throw new AccessDeniedHttpException();
    }

    $nid = (int) $form_state->getValue('nid');
    $to_tid = (int) $form_state->getValue('state_tid');

    $node = $this->etm->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      $this->messenger()->addError($this->t('No se pudo cargar la solicitud.'));
      $form_state->setRedirectUrl(Url::fromUserInput('/solicitudes-aspirantes'));
      return;
    }

    $current_tid = (int) ($node->get('field_state')->target_id ?? 0);
    $current_name = $this->resolveTermNameByTid($current_tid);

    if ($current_name === 'Aprobada') {
      $allowed = $this->resolveTidsByNames('estado_solicitud_ingreso', ['Rechazado Junta D.', 'Aprobado Junta D.']);
    } else {
      $allowed = $this->resolveTidsByNames('estado_solicitud_ingreso', ['Pendiente aclaración', 'Aprobada', 'Rechazada']);
    }

    if (!isset($allowed[$to_tid])) {
      $this->messenger()->addError($this->t('Opción de estado no permitida para el estado actual.'));
      $form_state->setRedirectUrl(\Drupal\Core\Url::fromUserInput('/solicitudes-aspirantes'));
      return;
    }

    $this->stateManager->transitionByTid(
      $node,
      $to_tid,
      'node_view_inline_select',
      'Cambio de estado por Secretaría General desde el detalle',
      ['nid' => $nid]
    );

    $this->messenger()->addStatus($this->t('Estado actualizado.'));
    $form_state->setRedirectUrl(Url::fromUserInput('/solicitudes-aspirantes'));
  }

  private function resolveTidsByNames(string $vid, array $names): array {
    $storage = $this->etm->getStorage('taxonomy_term');
    $options = [];

    foreach ($names as $name) {
      $terms = $storage->loadByProperties(['vid' => $vid, 'name' => $name]);
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
}
