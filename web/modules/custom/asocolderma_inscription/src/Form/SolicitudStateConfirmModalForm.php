<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\SolicitudStateManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SolicitudStateConfirmModalForm extends FormBase {

  protected EntityTypeManagerInterface $etm;
  protected SolicitudStateManager $stateManager;

  public function __construct(
    EntityTypeManagerInterface $etm,
    SolicitudStateManager $stateManager,
  ) {
    $this->etm = $etm;
    $this->stateManager = $stateManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_state_manager'),
    );
  }

  public function getFormId(): string {
    return 'asocolderma_solicitud_state_confirm_modal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL, $to_tid = NULL, $destination = '/'): array {
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => (int) $nid,
    ];

    $form['to_tid'] = [
      '#type' => 'hidden',
      '#value' => (int) $to_tid,
    ];

    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => (string) $destination,
    ];

    $form['message'] = [
      '#markup' => '<p>' . $this->t('¿Está seguro de cambiar el estado de esta solicitud?') . '</p>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['yes'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sí'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxConfirmSubmit',
      ],
    ];

    $form['actions']['no'] = [
      '#type' => 'submit',
      '#value' => $this->t('No'),
      '#limit_validation_errors' => [],
      '#submit' => ['::noSubmit'],
      '#ajax' => [
        'callback' => '::ajaxNoSubmit',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // La lógica se maneja en callbacks AJAX.
  }

  public function noSubmit(array &$form, FormStateInterface $form_state): void {
    // Intencionalmente vacío.
  }

  public function ajaxConfirmSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    if (
      !$this->currentUser()->hasRole('secretaria_general') &&
      !$this->currentUser()->hasRole('coordinacion_administrativa')
    ) {
      throw new AccessDeniedHttpException();
    }

    $nid = (int) $form_state->getValue('nid');
    $to_tid = (int) $form_state->getValue('to_tid');
    $destination = (string) $form_state->getValue('destination');

    $node = $this->etm->getStorage('node')->load($nid);

    if (!$node instanceof NodeInterface || $node->bundle() !== 'solicitud_ingreso') {
      $response = new AjaxResponse();
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($destination ?: '/'));
      return $response;
    }

    $this->stateManager->transitionByTid(
      $node,
      $to_tid,
      'modal_confirm',
      'Cambio de estado confirmado desde modal',
      [
        'nid' => $nid,
        'to_tid' => $to_tid,
        'actor_roles' => $this->currentUser()->getRoles(),
      ]
    );

    $this->messenger()->addStatus($this->t('Estado actualizado.'));

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RedirectCommand($destination ?: '/'));

    return $response;
  }

  public function ajaxNoSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $destination = (string) $form_state->getValue('destination');

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RedirectCommand($destination ?: '/'));

    return $response;
  }

}