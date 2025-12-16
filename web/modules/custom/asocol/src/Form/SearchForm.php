<?php

namespace Drupal\asocol\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal;

 /**
 * Implements a Search Dermatologist Form.
 */
class SearchForm extends FormBase {

  /**
   * Stores an entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MenuLinkEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   An instance of the entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId() {
    return 'search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $keys = Drupal::request()->query->get('keys');

    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('search.view_node_search')->toString();
    $form['keys'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Buscar'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#default_value' => $keys,
      '#attributes' => [
        'placeholder' => $this->t('Buscar'),
        'autocomplete' => 'off',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Buscar'),
      '#attributes' => [
        'class' => ['visually-hidden'],
      ],
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
