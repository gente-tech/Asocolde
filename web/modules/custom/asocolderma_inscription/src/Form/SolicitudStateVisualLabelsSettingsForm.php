<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuración de etiquetas visuales para estados de solicitud.
 */
final class SolicitudStateVisualLabelsSettingsForm extends ConfigFormBase
{

	private const VID = 'estado_solicitud_ingreso';

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames(): array
	{
		return [
			'asocolderma_inscription.state_visual_labels',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_inscription_state_visual_labels_settings_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$config = $this->config('asocolderma_inscription.state_visual_labels');
		$labels = $config->get('labels') ?? [];

		$form['description'] = [
			'#type' => 'item',
			'#markup' => '<p>Configure aquí la etiqueta visual de cada estado. La solicitud seguirá usando internamente el término real de la taxonomía <strong>estado_solicitud_ingreso</strong>.</p>',
		];

		$terms = $this->loadStateTerms();

		if (!$terms) {
			$form['empty'] = [
				'#type' => 'item',
				'#markup' => '<p>No hay términos creados en la taxonomía <strong>estado_solicitud_ingreso</strong>.</p>',
			];

			return parent::buildForm($form, $form_state);
		}

		$form['labels'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Estado real en taxonomía'),
				$this->t('UUID'),
				$this->t('Etiqueta visual'),
			],
			'#tree' => TRUE,
		];

		foreach ($terms as $term) {
			$uuid = $term->uuid();
			$real_label = $term->label();

			$form['labels'][$uuid]['real_label'] = [
				'#markup' => $real_label,
			];

			$form['labels'][$uuid]['uuid'] = [
				'#markup' => '<code>' . $uuid . '</code>',
			];

			$form['labels'][$uuid]['visual_label'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Etiqueta visual para @label', [
					'@label' => $real_label,
				]),
				'#title_display' => 'invisible',
				'#default_value' => $labels[$uuid] ?? $real_label,
				'#maxlength' => 255,
			];
		}

		$semaforo_labels = $config->get('semaforo_labels') ?? [];

		$form['semaforo_labels'] = [
			'#type' => 'details',
			'#title' => $this->t('Etiquetas del semáforo'),
			'#open' => TRUE,
			'#tree' => TRUE,
			'#description' => $this->t('Textos visuales para pasos del semáforo que no necesariamente corresponden directamente a un término actual de taxonomía.'),
		];

		foreach ($this->getSemaforoLabelDefaults() as $key => $default_label) {
			$form['semaforo_labels'][$key] = [
				'#type' => 'textfield',
				'#title' => $default_label,
				'#default_value' => $semaforo_labels[$key] ?? $default_label,
				'#maxlength' => 255,
			];
		}

		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$submitted_labels = $form_state->getValue('labels') ?? [];
		$labels = [];

		foreach ($this->loadStateTerms() as $term) {
			$uuid = $term->uuid();
			$real_label = $term->label();

			$visual_label = trim((string) ($submitted_labels[$uuid]['visual_label'] ?? ''));

			if ($visual_label === '') {
				$visual_label = $real_label;
			}

			$labels[$uuid] = $visual_label;
		}

		$submitted_semaforo_labels = $form_state->getValue('semaforo_labels') ?? [];
		$semaforo_labels = [];

		foreach ($this->getSemaforoLabelDefaults() as $key => $default_label) {
			$value = trim((string) ($submitted_semaforo_labels[$key] ?? ''));

			if ($value === '') {
				$value = $default_label;
			}

			$semaforo_labels[$key] = $value;
		}

		$this->configFactory
			->getEditable('asocolderma_inscription.state_visual_labels')
			->set('labels', $labels)
			->set('semaforo_labels', $semaforo_labels)
			->save();

		parent::submitForm($form, $form_state);
	}

	/**
	 * Carga los términos actuales de la taxonomía de estados.
	 */
	/**
	 * Carga los términos actuales de la taxonomía de estados.
	 */
	private function loadStateTerms(): array
	{
		$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

		$ids = $storage->getQuery()
			->condition('vid', self::VID)
			->sort('weight')
			->sort('name')
			->accessCheck(FALSE)
			->execute();

		if (!$ids) {
			return [];
		}

		return $storage->loadMultiple($ids);
	}

	/**
	 * Etiquetas configurables para pasos visuales del semáforo.
	 */
	private function getSemaforoLabelDefaults(): array
	{
		return [
			'pending_junta_directiva' => 'Pendiente Junta Directiva',
			'junta_directiva' => 'Junta Directiva',
			'pending_asamblea_general' => 'Pendiente Asamblea General',
			'asamblea_general' => 'Asamblea General',
			'coordinacion_administrativa' => 'Coordinación Administrativa',
			'envio_documentos' => 'Documentos enviados',
			'pago_ingreso' => 'Pago de ingreso',
			'activacion_miembro' => 'Activación de miembro',
			'miembro_activo' => 'Miembro activo',
		];
	}
}
