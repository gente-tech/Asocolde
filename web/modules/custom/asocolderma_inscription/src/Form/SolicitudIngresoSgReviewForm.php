<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Entity\SolicitudIngreso;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SolicitudIngresoSgReviewForm extends FormBase
{

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  public static function create(ContainerInterface $container): self
  {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
    );
  }

  public function getFormId(): string
  {
    return 'asocolderma_solicitud_sg_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, SolicitudIngreso $solicitud_ingreso = NULL): array
  {
    if (!$solicitud_ingreso) {
      $form['msg'] = ['#markup' => $this->t('Solicitud no encontrada.')];
      return $form;
    }

    $state_value = (string) $solicitud_ingreso->get('state')->value;
    $is_active = $this->isActiveSolicitud($state_value);

    $owner = NULL;
    $owner_name = $this->t('N/A');
    if (method_exists($solicitud_ingreso, 'getOwner')) {
      $owner = $solicitud_ingreso->getOwner();
      if ($owner instanceof UserInterface) {
        $owner_name = $owner->getDisplayName();
      }
    }

    $form['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Solicitud'),
      '#markup' => $this->t('ID: @id | Aspirante: @user | Estado actual: @state', [
        '@id' => (string) $solicitud_ingreso->get('solicitud_id')->value,
        '@user' => (string) $owner_name,
        '@state' => $state_value,
      ]),
    ];

    // 1) Detalle de la SOLICITUD (si tiene campos visibles en Manage display).
    $form['detail_solicitud'] = [
      '#type' => 'details',
      '#title' => $this->t('Detalle de la solicitud'),
      '#open' => TRUE,
    ];
    $form['detail_solicitud']['entity_view'] = $this->etm
      ->getViewBuilder('solicitud_ingreso')
      ->view($solicitud_ingreso, 'default');

    // 2) Detalle del PERFIL del aspirante (bundle customer) + adjuntos del profile.
    $profile = NULL;
    if ($owner instanceof UserInterface) {
      $profile = NULL;
      if ($owner instanceof UserInterface) {
        $profiles = $this->etm->getStorage('profile')->loadByProperties([
          'uid' => $owner->id(),
        ]);
        if ($profiles) {
          $profile = reset($profiles);
        }
      }
    }

    $form['detail_profile'] = [
      '#type' => 'details',
      '#title' => $this->t('Perfil del aspirante'),
      '#open' => TRUE,
    ];

    if ($profile instanceof EntityInterface) {
      $form['detail_profile']['profile_view'] = $this->etm
        ->getViewBuilder('profile')
        ->view($profile, 'default');

      $form['attachments'] = [
        '#type' => 'details',
        '#title' => $this->t('Archivos adjuntos (del perfil)'),
        '#open' => TRUE,
      ];
      $form['attachments']['items'] = [
        '#theme' => 'item_list',
        '#items' => $this->buildAttachmentItemsFromEntity($profile),
        '#empty' => $this->t('No hay adjuntos cargados en el perfil.'),
      ];
    } else {
      $form['detail_profile']['msg'] = [
        '#markup' => $this->t('No se encontró el perfil del aspirante (bundle: customer).'),
      ];
    }

    // Bloque de decisión SG.
    $form['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Decisión (Secretaría General)'),
      '#required' => TRUE,
      '#options' => [
        'sg_approved' => $this->t('Aprobar (pasa a Junta Directiva)'),
        'needs_clarification' => $this->t('Pendiente aclaración'),
        'rejected' => $this->t('Rechazar'),
      ],
      '#default_value' => $state_value,
      '#disabled' => !$is_active,
      '#description' => $is_active
        ? NULL
        : $this->t('Esta solicitud no está activa, por lo tanto no se puede cambiar de estado.'),
    ];

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo / observación'),
      '#description' => $this->t('Obligatorio si rechazas o pides aclaración.'),
      '#disabled' => !$is_active,
    ];

    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $solicitud_ingreso->id(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar'),
      '#disabled' => !$is_active,
    ];

    $form['#cache'] = [
      'contexts' => ['user.roles'],
      'tags' => $solicitud_ingreso->getCacheTags(),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    $id = (int) $form_state->getValue('entity_id');

    /** @var \Drupal\asocolderma_inscription\Entity\SolicitudIngreso|null $entity */
    $entity = $this->etm->getStorage('solicitud_ingreso')->load($id);
    if (!$entity) {
      $form_state->setErrorByName('entity_id', $this->t('No se pudo cargar la solicitud.'));
      return;
    }

    $current_state = (string) $entity->get('state')->value;
    if (!$this->isActiveSolicitud($current_state)) {
      $form_state->setErrorByName('decision', $this->t('Esta solicitud no está activa y no se puede modificar.'));
      return;
    }

    $decision = (string) $form_state->getValue('decision');
    $reason = trim((string) $form_state->getValue('reason'));

    if (in_array($decision, ['needs_clarification', 'rejected'], TRUE) && $reason === '') {
      $form_state->setErrorByName('reason', $this->t('Debes registrar el motivo.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $id = (int) $form_state->getValue('entity_id');
    $decision = (string) $form_state->getValue('decision');

    /** @var \Drupal\asocolderma_inscription\Entity\SolicitudIngreso|null $entity */
    $entity = $this->etm->getStorage('solicitud_ingreso')->load($id);
    if (!$entity) {
      $this->messenger()->addError($this->t('No se pudo cargar la solicitud.'));
      return;
    }

    $current_state = (string) $entity->get('state')->value;
    if (!$this->isActiveSolicitud($current_state)) {
      $this->messenger()->addError($this->t('Esta solicitud no está activa y no se puede modificar.'));
      $form_state->setRedirect('asocolderma_inscription.sg_list');
      return;
    }

    $reason = trim((string) $form_state->getValue('reason'));

    $entity->set('state', $decision);

    if (in_array($decision, ['needs_clarification', 'rejected'], TRUE)) {
      $entity->set('sg_reason', $reason);
    } else {
      $entity->set('sg_reason', '');
    }

    $entity->save();

    $this->messenger()->addStatus($this->t('Estado actualizado.'));
    $form_state->setRedirect('asocolderma_inscription.sg_list');
  }

  private function isActiveSolicitud(string $state): bool
  {
    $closed = [
      'rejected',
      'active_member',
    ];
    return !in_array($state, $closed, TRUE);
  }

  /**
   * Carga el profile del aspirante para un bundle específico.
   */
  private function loadAspiranteProfile(UserInterface $account, string $bundle): ?EntityInterface
  {
    $profiles = $this->etm->getStorage('profile')->loadByProperties([
      'uid' => $account->id(),
      'type' => $bundle,
    ]);

    if (!$profiles) {
      return NULL;
    }

    return reset($profiles) ?: NULL;
  }

  /**
   * Construye un listado de adjuntos detectando file/image directos
   * y entity_reference a media/file dentro de una entidad (Profile en tu caso).
   */
  private function buildAttachmentItemsFromEntity(EntityInterface $entity): array
  {
    $items = [];

    $definitions = $entity->getFieldDefinitions();
    foreach ($definitions as $field_name => $definition) {
      if (!$entity->hasField($field_name)) {
        continue;
      }

      $field = $entity->get($field_name);
      if ($field->isEmpty()) {
        continue;
      }

      $type = (string) $definition->getType();
      $label = (string) $definition->getLabel();

      // Caso 1: file/image directos.
      if (in_array($type, ['file', 'image'], TRUE)) {
        foreach ($field as $item) {
          $fid = (int) ($item->target_id ?? 0);
          if (!$fid) {
            continue;
          }

          /** @var \Drupal\file\FileInterface|null $file */
          $file = $this->etm->getStorage('file')->load($fid);
          if (!$file instanceof FileInterface) {
            continue;
          }

          $url_string = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          $link = Link::fromTextAndUrl($file->getFilename(), Url::fromUri($url_string))->toRenderable();

          $items[] = [
            '#type' => 'inline_template',
            '#template' => '<strong>{{ label }}</strong>: {{ link }}',
            '#context' => ['label' => $label, 'link' => $link],
          ];
        }
        continue;
      }

      // Caso 2: entity_reference a media/file.
      if ($type === 'entity_reference') {
        $target = (string) ($definition->getSetting('target_type') ?? '');

        // 2a) target = file
        if ($target === 'file') {
          foreach ($field as $item) {
            $fid = (int) ($item->target_id ?? 0);
            if (!$fid) {
              continue;
            }
            /** @var \Drupal\file\FileInterface|null $file */
            $file = $this->etm->getStorage('file')->load($fid);
            if (!$file instanceof FileInterface) {
              continue;
            }
            $url_string = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            $link = Link::fromTextAndUrl($file->getFilename(), Url::fromUri($url_string))->toRenderable();

            $items[] = [
              '#type' => 'inline_template',
              '#template' => '<strong>{{ label }}</strong>: {{ link }}',
              '#context' => ['label' => $label, 'link' => $link],
            ];
          }
        }

        // 2b) target = media
        if ($target === 'media') {
          foreach ($field as $item) {
            $mid = (int) ($item->target_id ?? 0);
            if (!$mid) {
              continue;
            }

            /** @var \Drupal\Core\Entity\EntityInterface|null $media */
            $media = $this->etm->getStorage('media')->load($mid);
            if (!$media) {
              continue;
            }

            // Encuentra el primer file/image dentro del media.
            $media_file = NULL;
            foreach ($media->getFieldDefinitions() as $mfield_name => $mdef) {
              $mtype = (string) $mdef->getType();
              if (!in_array($mtype, ['file', 'image'], TRUE)) {
                continue;
              }
              if ($media->get($mfield_name)->isEmpty()) {
                continue;
              }

              $fid = (int) $media->get($mfield_name)->target_id;
              if ($fid) {
                $media_file = $this->etm->getStorage('file')->load($fid);
              }
              break;
            }

            if (!$media_file instanceof FileInterface) {
              continue;
            }

            $url_string = $this->fileUrlGenerator->generateAbsoluteString($media_file->getFileUri());
            $text = method_exists($media, 'label') ? ($media->label() ?: $media_file->getFilename()) : $media_file->getFilename();
            $link = Link::fromTextAndUrl($text, Url::fromUri($url_string))->toRenderable();

            $items[] = [
              '#type' => 'inline_template',
              '#template' => '<strong>{{ label }}</strong>: {{ link }}',
              '#context' => ['label' => $label, 'link' => $link],
            ];
          }
        }
      }
    }

    return $items;
  }
}
