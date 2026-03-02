<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asocolderma_inscription\Entity\SolicitudIngreso;

final class SolicitudIngresoEditWizardForm extends FormBase {

  public function getFormId(): string {
    return 'asocolderma_solicitud_ingreso_edit_wizard';
  }

  public function buildForm(array $form, FormStateInterface $form_state, SolicitudIngreso $solicitud_ingreso = NULL): array {
    if (!$solicitud_ingreso) {
      return ['#markup' => $this->t('Solicitud no encontrada.')];
    }

    if ((int) $solicitud_ingreso->getOwnerId() !== (int) $this->currentUser()->id()) {
      return ['#markup' => $this->t('No tienes acceso a esta solicitud.')];
    }

    if ((string) $solicitud_ingreso->get('state')->value !== 'needs_clarification') {
      return ['#markup' => $this->t('Esta solicitud no está en estado de aclaración; no es editable.')];
    }

    $form['msg'] = [
      '#markup' => $this->t('Edición por aclaración pendiente de implementar (mapeo de campos).'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No-op.
  }

}