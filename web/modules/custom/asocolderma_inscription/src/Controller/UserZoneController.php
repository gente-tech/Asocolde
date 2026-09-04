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
    if (!$this->isAspiranteUser()) {
      return $this->redirectOutOfAspiranteZone();
    }

    return $this->redirect('asocolderma_inscription.user_zone_profile');
  }

  public function profile(): array|RedirectResponse
  {
    if (!$this->isAspiranteUser()) {
      return $this->redirectOutOfAspiranteZone();
    }

    $account = $this->currentUser();
    $storage = $this->etm->getStorage('profile');

    $profiles = $storage->loadByProperties([
      'uid' => $account->id(),
      'type' => 'aspirante',
    ]);

    $profile = $profiles ? reset($profiles) : NULL;

    if (!$profile) {
      return [
        '#markup' => $this->t('No se encontró un perfil asociado a tu cuenta.'),
        '#attached' => [
          'library' => [
            'asocolderma_inscription/user_zone',
          ],
        ],
      ];
    }

    $view_builder = $this->etm->getViewBuilder('profile');

    $edit_link = [
      '#type' => 'link',
      '#title' => $this->t('Editar perfil'),
      '#url' => Url::fromRoute('asocolderma_inscription.user_zone_profile_edit'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
          'user-zone-button',
          'user-zone-button--primary',
        ],
      ],
    ];

    return [
      '#theme' => 'asocolderma_inscription_user_zone_profile',
      '#user_name' => $account->getDisplayName(),
      '#profile' => $view_builder->view($profile, 'default'),
      '#edit_link' => $edit_link,
      '#header_menu_items' => $this->buildAspiranteMenuItems(),
      '#logout_url' => Url::fromRoute('user.logout')->toString(),
      '#attached' => [
        'library' => [
          'asocolderma_inscription/user_zone',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $profile->getCacheTags(),
      ],
    ];
  }

  public function profileEdit(): RedirectResponse
  {
    if (!$this->isAspiranteUser()) {
      return $this->redirectOutOfAspiranteZone();
    }

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

  public function requests(): array|RedirectResponse
  {
    if (!$this->isAspiranteUser()) {
      return $this->redirectOutOfAspiranteZone();
    }

    $account = $this->currentUser();

    $storage = $this->etm->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'solicitud_ingreso')
      ->condition('uid', $account->id())
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $storage->loadMultiple($ids);

    $requests = [];

    foreach ($nodes as $n) {
      $term = $n->get('field_state')->entity;
      $estado_label = $term ? (string) $term->label() : '-';

      $actions = [];

      if ($estado_label === 'Pendiente aclaración') {
        $actions[] = [
          'label' => $this->t('Editar solicitud'),
          'url' => Url::fromRoute(
            'asocolderma_inscription.solicitud_edit',
            ['node' => $n->id()]
          )->toString(),
          'modifier' => 'primary',
        ];
      }

      $estado_functional_key = $estado_term
        ? \asocolderma_inscription_get_state_functional_key_from_term($estado_term)
        : '';

      if ($estado_functional_key === 'coord_documentos_enviados') {
        $actions[] = [
          'label' => $this->t('Firmar documentos'),
          'url' => Url::fromRoute(
            'asocolderma_inscription.solicitud_sign_redirect',
            ['node' => $n->id()]
          )->toString(),
          'modifier' => 'primary',
        ];
      }

      $solicitud_id = $n->get('field_solicitud_id')->value ?? ('NID ' . $n->id());

      $requests[] = [
        'id' => $n->id(),
        'code' => $solicitud_id,
        'state' => $estado_label,
        'created' => \Drupal::service('date.formatter')->format((int) $n->getCreatedTime(), 'custom', 'd/m/Y h:i A'),
        'detail_url' => Url::fromRoute('asocolderma_inscription.user_zone_request_detail', [
          'node' => $n->id(),
        ])->toString(),
        'actions' => $actions,
      ];
    }

    $has_active = $this->manager->hasActiveSolicitud((int) $account->id());

    $tempStore = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $draft = $tempStore->get(self::TEMPSTORE_KEY);
    $has_draft = !empty($draft) && is_array($draft);

    $primary_action = NULL;

    if (!$has_active) {
      $primary_action = [
        'label' => $has_draft ? $this->t('Continuar solicitud') : $this->t('Crear solicitud'),
        'url' => Url::fromRoute('asocolderma_inscription.solicitud_create')->toString(),
      ];
    }

    return [
      '#theme' => 'asocolderma_inscription_user_zone_requests',
      '#user_name' => $account->getDisplayName(),
      '#requests' => $requests,
      '#primary_action' => $primary_action,
      '#has_active' => $has_active,
      '#has_draft' => $has_draft,
      '#header_menu_items' => $this->buildAspiranteMenuItems(),
      '#logout_url' => Url::fromRoute('user.logout')->toString(),
      '#attached' => [
        'library' => [
          'asocolderma_inscription/user_zone',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }

  public function requestDetail(NodeInterface $node): array|RedirectResponse
  {
    $account = $this->currentUser();

    if ($node->bundle() !== 'solicitud_ingreso') {
      throw new NotFoundHttpException();
    }

    if (!$this->isAspiranteUser()) {
      return $this->redirectOutOfAspiranteZone();
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

  private function isAspiranteUser(): bool
  {
    return in_array('aspirante', $this->currentUser()->getRoles(), TRUE);
  }

  private function redirectOutOfAspiranteZone(): RedirectResponse
  {
    if ($this->currentUser()->hasRole('dermatologist')) {
      return new RedirectResponse('/dermatologos');
    }

    return new RedirectResponse('/user');
  }

  private function buildAspiranteMenuItems(): array
  {
    $menu_name = 'account-aspirante';
    $menu_tree = \Drupal::menuTree();

    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $parameters->onlyEnabledLinks();
    $parameters->setMinDepth(1);
    $parameters->setMaxDepth(1);

    $tree = $menu_tree->load($menu_name, $parameters);

    $tree = $menu_tree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $items = [];

    foreach ($tree as $element) {
      if (!$element->access->isAllowed()) {
        continue;
      }

      $url = $element->link->getUrlObject();
      $url_string = $url->toString();

      $items[] = [
        'title' => $element->link->getTitle(),
        'url' => $url_string,
        'active' => (bool) $element->inActiveTrail,
        'is_logout' => $element->link->getRouteName() === 'user.logout' || str_contains($url_string, '/user/logout'),
      ];
    }

    return $items;
  }
}
