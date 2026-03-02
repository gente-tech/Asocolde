<?php

namespace Drupal\asocol\Plugin\Block;

use Drupal\Core\Link;
use Drupal\Core\Cache\Cache;
use Drupal\file\Entity\File;
use Drupal\Core\Block\BlockBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a 'UserMenuList' block.
 *
 * @Block(
 *   id = "user_menu_list",
 *   admin_label = @Translation("User Menu List block"),
 * )
 */
class UserMenuList extends BlockBase implements ContainerFactoryPluginInterface
{

  /**
   * Stores an entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity storage for User entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface;
   */
  protected $userStorage;

  /**
   * Stores the current logged in user or anonymous account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentAccount;

  /**
   * Stores the current user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $currentUser;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * Creates a BLockUserInfo instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   An instance of the entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_account
   *   An instance of the current logged in user or anonymous account.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current request.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_account,
    MenuLinkTreeInterface $menu_tree
  ) {
    // Get default values.
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Get user entity tools.
    $this->entityTypeManager = $entity_type_manager;
    $this->userStorage = $entity_type_manager->getStorage('user');

    // Get user info.
    $this->currentAccount = $current_account;
    $this->currentUser = $this->userStorage->load($this->currentAccount->id());
    $this->menuTree = $menu_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('menu.link_tree')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $build = [
      '#cache' => [
        'context' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
        'max-age' => $this->getCacheMaxAge(),
      ],
      '#theme' => 'user_menu_list',
    ];

    if ($this->currentUser != NULL) {
      if ($this->currentUser->hasField('user_picture')  &&  !$this->currentUser->user_picture->isEmpty()) {
        $image_uri = $this->currentUser->user_picture->entity->getFileUri();
      }

      if (empty($image_uri)) {
        /* var $field_config FieldConfig */
        $field_config = FieldConfig::loadByName('user', 'user', 'user_picture');
        $file_uuid = $field_config->getSetting('default_image')['uuid'];
        if ($file_uuid) {
          $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $file_uuid);
          if ($file instanceof File) {
            $image_uri = $file->getFileUri();
          }
        }
      }

      $profiles = $this->entityTypeManager
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $this->currentUser->id(),
          'type' => 'aspirante',
        ]);

      $has_aspirante_profile = !empty($profiles);

      \Drupal::logger('debug')->notice('Profiles count: @count', [
        '@count' => count($profiles),
      ]);

      $fullname = NULL;
      if (!$this->currentUser->field_first_name->isEmpty()) {
        $fullname = $this->currentUser->field_first_name->getString();
      }

      $prefix = 'Dr.';
      if (!$this->currentUser->field_sex->isEmpty()  &&  $this->currentUser->field_sex->value == '1') {
        $prefix = 'Dra.';
      }

      if ($this->currentUser->hasRole('aspirante') && $has_aspirante_profile) {
        \Drupal::logger('debug')->notice('Es perfil aspirante');

        $profile = reset($profiles);

        $nombres = '';
        $apellidos = '';

        if ($profile->hasField('field_nombre') && !$profile->get('field_nombre')->isEmpty()) {
          $nombres = $profile->get('field_nombre')->value;
        }

        if ($profile->hasField('field_apellidos') && !$profile->get('field_apellidos')->isEmpty()) {
          $apellidos = $profile->get('field_apellidos')->value;
        }

        \Drupal::logger('asocolderma_inscription')->info(
          'Los datos del usuario son: @nombres y @apellidos',
          [
            '@nombres' => $nombres,
            '@apellidos' => $apellidos,
          ]
        );

        $nombre_completo = trim($nombres . ' ' . $apellidos);

        if (!empty($nombre_completo)) {
          $fullname = $nombre_completo;
          $prefix = ''; // Aspirante no lleva Dr/Dra
        } else {
          $fullname = 'Aspirante';
          $prefix = '';
        }
      }

      $build['#fullname'] = trim($prefix . ' ' . $fullname);

      if (!empty($image_uri)) {
        $build['#picture'] = [
          '#theme' => 'image_style',
          '#style_name' => 'thumbnail',
          '#uri' => $image_uri,
        ];
      }

      $menu_name = 'account';

      if (
        $this->currentUser->hasRole('aspirante') &&
        $has_aspirante_profile
      ) {
        $menu_name = 'account-aspirante';
      }

      $build['#menu'] = $this->getMenuItems($menu_name);

      \Drupal::logger('asocolderma_inscription')->info(
        'El menú que debe verse es: @menu_name',
        [
          '@menu_name' => $menu_name,
        ]
      );
    }

    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheTags(['user:' . $this->currentUser->id()]);

    if ($has_aspirante_profile) {
      $cache->addCacheTags($profile->getCacheTags());
    }

    $cache->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags()
  {
    $uid = (NULL != $this->currentUser) ? $this->currentUser->id() : FALSE;
    if ($uid) {
      $tags = [
        'user:' . $uid,
      ];
      return Cache::mergeTags(parent::getCacheTags(), $tags);
    } else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  public function getCacheContexts()
  {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  protected function getMenuItems($menu_name)
  {
    $menu_tree = \Drupal::menuTree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $parameters->setMinDepth(0);
    $parameters->onlyEnabledLinks();

    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $menu_tree->transform($tree, $manipulators);

    return $menu_tree->build($tree);
  }
}
