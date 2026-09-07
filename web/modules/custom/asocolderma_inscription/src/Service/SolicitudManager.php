<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

final class SolicitudManager
{

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Determina si el usuario tiene alguna solicitud no cancelada.
   *
   * Solo se permite crear una nueva solicitud cuando todas las solicitudes
   * anteriores están en un estado funcional de rechazo o cancelación.
   */
  public function hasActiveSolicitud(int $uid): bool
  {
    if ($uid <= 0) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $nids = $storage->getQuery()
      ->condition('type', 'solicitud_ingreso')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      return FALSE;
    }

    $nodes = $storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      if (
        !$node->hasField('field_state') ||
        $node->get('field_state')->isEmpty()
      ) {
        return TRUE;
      }

      $term = $node->get('field_state')->entity;

      if (!$term instanceof TermInterface) {
        return TRUE;
      }

      $functional_key =
        \asocolderma_inscription_get_state_functional_key_from_term($term);

      if ($functional_key === '') {
        $functional_key =
          \asocolderma_inscription_get_functional_key_from_state_name(
            (string) $term->label()
          );
      }

      if (!in_array($functional_key, [
        'sg_rechazado',
        'junta_rechazado',
        'asamblea_rechazado',
      ], TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }
}
