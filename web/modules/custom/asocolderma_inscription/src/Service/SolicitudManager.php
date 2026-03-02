<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

final class SolicitudManager
{

  private EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager)
  {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Determina si un usuario tiene una solicitud activa.
   *
   * Estados activos según taxonomía actual:
   * - En trámite
   * - Pendiente aclaración
   */
  public function hasActiveSolicitud(int $uid): bool
  {
    $activeTids = $this->getActiveEstadoTids();

    if (empty($activeTids)) {
      return FALSE;
    }

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'solicitud_ingreso')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->condition('field_state', $activeTids, 'IN')
      ->accessCheck(FALSE)
      ->range(0, 1);

    $nids = $query->execute();

    return !empty($nids);
  }

  /**
   * Obtiene los TID de los estados considerados activos.
   */
  private function getActiveEstadoTids(): array
  {
    $activeNames = [
      'En trámite',
      'Pendiente aclaración',
    ];

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $terms = $storage->loadByProperties([
      'vid' => 'estado_solicitud_ingreso',
    ]);

    if (empty($terms)) {
      return [];
    }

    $tids = [];

    foreach ($terms as $term) {
      if (in_array($term->getName(), $activeNames, TRUE)) {
        $tids[] = (int) $term->id();
      }
    }

    return $tids;
  }

}