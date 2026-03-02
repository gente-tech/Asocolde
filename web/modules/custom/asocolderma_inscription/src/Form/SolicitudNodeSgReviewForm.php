<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SolicitudNodeSgReviewForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
    );
  }

  public function getFormId(): string {
    return 'asocolderma_solicitud_node_sg_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      $form['msg'] = ['#markup' => $this->t('Solicitud no encontrada o inválida.')];
      return $form;
    }

    $solicitud_id = (string) ($node->get('field_solicitud_id')->value ?? ('NID ' . $node->id()));
    $state_value = (string) ($node->get('field_state')->value ?? '');
    $is_active = $this->isActiveSolicitud($state_value);

    $owner = $node->getOwner();
    $owner_label = $owner ? $owner->getEmail() : $this->t('N/A');

    $form['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Solicitud'),
      '#markup' => $this->t('ID: @id | Aspirante: @user | Estado actual: @state', [
        '@id' => $solicitud_id,
        '@user' => (string) $owner_label,
        '@state' => $state_value ?: $this->t('N/A'),
      ]),
    ];

    // Render completo del node (incluye todos los campos visibles en Manage display).
    $form['detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Detalle de la solicitud'),
      '#open' => TRUE,
    ];
    $form['detail']['node_view'] = $this->etm
      ->getViewBuilder('node')
      ->view($node, 'default');

    // Adjuntos: detecta file/image en el node (y también entity_reference a file/media).
    $form['attachments'] = [
      '#type' => 'details',
      '#title' => $this->t('Archivos adjuntos'),
      '#open' => TRUE,
    ];
    $form['attachments']['items'] = [
      '#theme' => 'item_list',
      '#items' => $this->buildAttachmentItemsFromNode($node),
      '#empty' => $this->t('No hay adjuntos cargados en la solicitud.'),
    ];

    // Cambio de estado SG.
    $form['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Decisión (Secretaría General)'),
      '#required' => TRUE,
      '#options' => [
        'sg_approved' => $this->t('Aprobar (pasa a Junta Directiva)'),
        'needs_clarification' => $this->t('Pendiente aclaración'),
        'rejected' => $this->t('Rechazar'),
      ],
      '#default_value' => $state_value ?: 'in_progress',
      '#disabled' => !$is_active,
      '#description' => $is_active
        ? NULL
        : $this->t('Esta solicitud no está activa, por lo tanto no se puede cambiar de estado.'),
    ];

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo / observación'),
      '#description' => $this->t('Obligatorio si rechazas o pides aclaración.'),
      '#default_value' => (string) ($node->get('field_sg_reason')->value ?? ''),
      '#disabled' => !$is_active,
    ];

    $form['nid'] = [
      '#type' => 'value',
      '#value' => (int) $node->id(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar'),
      '#disabled' => !$is_active,
    ];

    $form['#cache'] = [
      'contexts' => ['user.roles'],
      'tags' => $node->getCacheTags(),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->getValue('nid');
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->etm->getStorage('node')->load($nid);

    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      $form_state->setErrorByName('nid', $this->t('No se pudo cargar la solicitud.'));
      return;
    }

    $current_state = (string) ($node->get('field_state')->value ?? '');
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

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->getValue('nid');
    $decision = (string) $form_state->getValue('decision');
    $reason = trim((string) $form_state->getValue('reason'));

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->etm->getStorage('node')->load($nid);

    if (!$node || $node->bundle() !== 'solicitud_ingreso') {
      $this->messenger()->addError($this->t('No se pudo cargar la solicitud.'));
      return;
    }

    $current_state = (string) ($node->get('field_state')->value ?? '');
    if (!$this->isActiveSolicitud($current_state)) {
      $this->messenger()->addError($this->t('Esta solicitud no está activa y no se puede modificar.'));
      $form_state->setRedirect('asocolderma_inscription.sg_list');
      return;
    }

    $node->set('field_state', $decision);

    if (in_array($decision, ['needs_clarification', 'rejected'], TRUE)) {
      $node->set('field_sg_reason', $reason);
    }
    else {
      $node->set('field_sg_reason', '');
    }

    $node->save();

    $this->messenger()->addStatus($this->t('Estado actualizado.'));
    $form_state->setRedirect('asocolderma_inscription.sg_list');
  }

  private function isActiveSolicitud(string $state): bool {
    $closed = ['rejected', 'active_member'];
    return $state === '' ? TRUE : !in_array($state, $closed, TRUE);
  }

  /**
   * Lista adjuntos desde el NODE:
   * - file/image directos
   * - entity_reference a file
   * - entity_reference a media (y dentro de media busca file/image)
   */
  private function buildAttachmentItemsFromNode(NodeInterface $node): array {
    $items = [];

    foreach ($node->getFieldDefinitions() as $field_name => $definition) {
      if (!$node->hasField($field_name)) {
        continue;
      }
      $field = $node->get($field_name);
      if ($field->isEmpty()) {
        continue;
      }

      $type = (string) $definition->getType();
      $label = (string) $definition->getLabel();

      // file/image directos.
      if (in_array($type, ['file', 'image'], TRUE)) {
        foreach ($field as $item) {
          $fid = (int) ($item->target_id ?? 0);
          if (!$fid) continue;

          /** @var \Drupal\file\FileInterface|null $file */
          $file = $this->etm->getStorage('file')->load($fid);
          if (!$file instanceof FileInterface) continue;

          $items[] = $this->fileItemRenderable($label, $file);
        }
        continue;
      }

      // entity_reference a file/media.
      if ($type === 'entity_reference') {
        $target = (string) ($definition->getSetting('target_type') ?? '');

        // target = file
        if ($target === 'file') {
          foreach ($field as $item) {
            $fid = (int) ($item->target_id ?? 0);
            if (!$fid) continue;

            /** @var \Drupal\file\FileInterface|null $file */
            $file = $this->etm->getStorage('file')->load($fid);
            if (!$file instanceof FileInterface) continue;

            $items[] = $this->fileItemRenderable($label, $file);
          }
        }

        // target = media
        if ($target === 'media') {
          foreach ($field as $item) {
            $mid = (int) ($item->target_id ?? 0);
            if (!$mid) continue;

            $media = $this->etm->getStorage('media')->load($mid);
            if (!$media) continue;

            $file = $this->extractFileFromMedia($media);
            if (!$file) continue;

            $items[] = $this->fileItemRenderable($label, $file, $media->label() ?: $file->getFilename());
          }
        }
      }
    }

    return $items;
  }

  private function extractFileFromMedia($media): ?FileInterface {
    foreach ($media->getFieldDefinitions() as $mfield_name => $mdef) {
      $mtype = (string) $mdef->getType();
      if (!in_array($mtype, ['file', 'image'], TRUE)) {
        continue;
      }
      if ($media->get($mfield_name)->isEmpty()) {
        continue;
      }
      $fid = (int) $media->get($mfield_name)->target_id;
      if (!$fid) {
        continue;
      }
      $file = $this->etm->getStorage('file')->load($fid);
      return $file instanceof FileInterface ? $file : NULL;
    }
    return NULL;
  }

  private function fileItemRenderable(string $label, FileInterface $file, ?string $link_text = NULL): array {
    $url_string = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    $text = $link_text ?: $file->getFilename();

    $link = Link::fromTextAndUrl($text, Url::fromUri($url_string))->toRenderable();

    return [
      '#type' => 'inline_template',
      '#template' => '<strong>{{ label }}</strong>: {{ link }}',
      '#context' => [
        'label' => $label,
        'link' => $link,
      ],
    ];
  }

}