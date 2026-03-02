<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SolicitudSgController extends ControllerBase
{

  public function __construct(private readonly EntityTypeManagerInterface $etm) {}

  public static function create(ContainerInterface $container): self
  {
    return new self($container->get('entity_type.manager'));
  }

  public function list(): array
  {
    $storage = $this->etm->getStorage('node');

    $inactive_states = ['rejected', 'active_member'];

    $ids = $storage->getQuery()
      ->condition('type', 'solicitud_ingreso')
      ->condition('field_state', $inactive_states, 'NOT IN')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $storage->loadMultiple($ids);

    $rows = [];
    foreach ($nodes as $n) {
      $owner = $n->getOwner();
      $rows[] = [
        $n->get('field_solicitud_id')->value ?? ('NID ' . $n->id()),
        $owner ? $owner->getEmail() : $this->t('N/A'),
        $n->get('field_state')->getString(),
        \Drupal::service('date.formatter')->format((int) $n->getCreatedTime(), 'short'),
        Link::fromTextAndUrl(
          $this->t('Revisar'),
          Url::fromRoute('asocolderma_inscription.sg_review', ['node' => $n->id()])
        )->toString(),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Aspirante'),
        $this->t('Estado'),
        $this->t('Creada'),
        $this->t('Acción'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No hay solicitudes activas.'),
      '#cache' => ['max-age' => 0],
    ];
  }
}
