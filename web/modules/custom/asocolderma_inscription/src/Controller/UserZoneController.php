<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\asocolderma_inscription\Service\SolicitudManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UserZoneController extends ControllerBase
{

  private const TEMPSTORE_COLLECTION = 'asocolderma_inscription_wizard';
  private const TEMPSTORE_KEY = 'solicitud_ingreso_draft';

  private EntityTypeManagerInterface $etm;
  private SolicitudManager $manager;
  private PrivateTempStoreFactory $tempStoreFactory;

  public function __construct(
    EntityTypeManagerInterface $etm,
    SolicitudManager $manager,
    PrivateTempStoreFactory $temp_store_factory
  ) {
    $this->etm = $etm;
    $this->manager = $manager;
    $this->tempStoreFactory = $temp_store_factory;
  }

  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('asocolderma_inscription.solicitud_manager'),
      $container->get('tempstore.private'),
    );
  }

  public function redirectDefault(): RedirectResponse
  {
    return $this->redirect('asocolderma_inscription.user_zone_profile');
  }

  public function profile(): array
  {
    $account = $this->currentUser();
    $storage = $this->etm->getStorage('profile');

    $profiles = $storage->loadByProperties([
      'uid' => $account->id(),
      'type' => 'aspirante',
    ]);

    $profile = $profiles ? reset($profiles) : NULL;

    if (!$profile) {
      return ['#markup' => $this->t('No se encontró un perfil asociado a tu cuenta.')];
    }

    $view_builder = $this->etm->getViewBuilder('profile');

    return [
      'profile' => $view_builder->view($profile, 'default'),
      'actions' => [
        '#type' => 'link',
        '#title' => $this->t('Editar perfil'),
        '#url' => \Drupal\Core\Url::fromRoute('asocolderma_inscription.user_zone_profile_edit'),
        '#attributes' => ['class' => ['button']],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $profile->getCacheTags(),
      ],
    ];
  }

  public function profileEdit(): RedirectResponse
  {
    $account = $this->currentUser();
    $storage = $this->etm->getStorage('profile');

    $profiles = $storage->loadByProperties([
      'uid' => $account->id(),
      'type' => 'aspirante',
    ]);

    $profile = $profiles ? reset($profiles) : NULL;

    if (!$profile) {
      $this->messenger()->addError($this->t('No se encontró un perfil para editar.'));
      return $this->redirect('asocolderma_inscription.user_zone_profile');
    }

    return $this->redirect('entity.profile.edit_form', [
      'profile' => $profile->id(),
    ]);
  }

  public function requests(): array
  {
    $account = $this->currentUser();

    $storage = $this->etm->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'solicitud_ingreso')
      ->condition('uid', $account->id())
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $storage->loadMultiple($ids);

    $rows = [];
    foreach ($nodes as $n) {
      $term = $n->get('field_state')->entity;
      $estado_label = $term ? (string) $term->label() : '-';

      $action = '';

      $actions = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['solicitud-user-actions'],
        ],
      ];

      if ($estado_label === 'Pendiente aclaración') {
        $actions['edit'] = [
          '#type' => 'link',
          '#title' => $this->t('Editar solicitud'),
          '#url' => \Drupal\Core\Url::fromRoute(
            'asocolderma_inscription.solicitud_edit',
            ['node' => $n->id()]
          ),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }

      if ($estado_label === 'Pendiente firma de documentos') {
        $actions['sign'] = [
          '#type' => 'link',
          '#title' => $this->t('Firmar documentos'),
          '#url' => \Drupal\Core\Url::fromRoute(
            'asocolderma_inscription.solicitud_sign_redirect',
            ['node' => $n->id()]
          ),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }

      $action = !empty($actions['edit']) || !empty($actions['sign'])
        ? ['data' => $actions]
        : '-';

      $solicitud_id = $n->get('field_solicitud_id')->value ?? ('NID ' . $n->id());

      $rows[] = [
        'id' => [
          'data' => [
            '#type' => 'link',
            '#title' => $solicitud_id,
            '#url' => Url::fromRoute('asocolderma_inscription.user_zone_request_detail', [
              'node' => $n->id(),
            ]),
            '#attributes' => [
              'class' => ['solicitud-id-link'],
            ],
          ],
        ],
        'state' => $estado_label,
        'created' => \Drupal::service('date.formatter')->format((int) $n->getCreatedTime(), 'short'),
        'actions' => $action,
      ];
    }

    $has_active = $this->manager->hasActiveSolicitud((int) $account->id());

    $tempStore = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $draft = $tempStore->get(self::TEMPSTORE_KEY);
    $has_draft = !empty($draft) && is_array($draft);

    $build = [];

    if ($has_active) {
      $build['active_notice'] = [
        '#type' => 'status_messages',
      ];
      $this->messenger()->addStatus($this->t('Tienes una solicitud activa en curso. No puedes crear otra hasta que finalice.'));
    } else {
      if ($has_draft) {
        $build['continue'] = [
          '#type' => 'link',
          '#title' => $this->t('Continuar solicitud'),
          '#url' => \Drupal\Core\Url::fromRoute('asocolderma_inscription.solicitud_create'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      } else {
        $build['create'] = [
          '#type' => 'link',
          '#title' => $this->t('Crear solicitud'),
          '#url' => \Drupal\Core\Url::fromRoute('asocolderma_inscription.solicitud_create'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Estado'),
        $this->t('Creada'),
        $this->t('Acciones'),
      ],
      '#rows' => array_map(
        static fn($r) => [
          $r['id'],
          $r['state'],
          $r['created'],
          $r['actions'],
        ],
        $rows
      ),
      '#empty' => $this->t('Aún no has creado solicitudes.'),
    ];

    $build['#cache'] = [
      'max-age' => 0,
    ];

    $build['#attached']['library'][] = 'asocolderma_inscription/user_zone';

    return $build;
  }

  public function requestDetail(NodeInterface $node): array
  {
    $account = $this->currentUser();

    if ($node->bundle() !== 'solicitud_ingreso') {
      throw new NotFoundHttpException();
    }

    if (!in_array('aspirante', $account->getRoles(), TRUE)) {
      throw new NotFoundHttpException();
    }

    if ((int) $node->getOwnerId() !== (int) $account->id()) {
      throw new NotFoundHttpException();
    }

    $view_builder = $this->etm->getViewBuilder('node');

    $build = [];

    $build['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Volver a mis solicitudes'),
      '#url' => Url::fromRoute('asocolderma_inscription.user_zone_requests'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    $build['detail'] = $view_builder->view($node, 'full');

    $build['#cache'] = [
      'contexts' => ['user'],
      'tags' => $node->getCacheTags(),
    ];

    return $build;
  }
}
