<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Centraliza los textos configurables del formulario de solicitud.
 */
final class SolicitudFormTextManager
{

	private const CONFIG_NAME = 'asocolderma_inscription.solicitud_form_settings';

	public function __construct(
		private readonly ConfigFactoryInterface $configFactory,
	) {}

	/**
	 * Retorna toda la configuración de campos.
	 */
	public function getFields(): array
	{
		$fields = $this->configFactory
			->get(self::CONFIG_NAME)
			->get('fields');

		return is_array($fields) ? $fields : [];
	}

	/**
	 * Retorna la configuración de un campo.
	 */
	public function getFieldSettings(string $group, string $field): array
	{
		$fields = $this->getFields();
		$settings = $fields[$group][$field] ?? [];

		return is_array($settings) ? $settings : [];
	}

	/**
	 * Retorna el label configurado.
	 */
	public function getLabel(
		string $group,
		string $field,
		string $fallback = '',
	): string {
		$settings = $this->getFieldSettings($group, $field);
		$label = trim((string) ($settings['label'] ?? ''));

		return $label !== '' ? $label : $fallback;
	}

	/**
	 * Retorna el placeholder configurado.
	 */
	public function getPlaceholder(
		string $group,
		string $field,
		string $fallback = '',
	): string {
		$settings = $this->getFieldSettings($group, $field);

		if (!array_key_exists('placeholder', $settings)) {
			return $fallback;
		}

		return trim((string) $settings['placeholder']);
	}

	/**
	 * Retorna la descripción configurada.
	 */
	public function getDescription(
		string $group,
		string $field,
		string $fallback = '',
	): string {
		$settings = $this->getFieldSettings($group, $field);

		if (!array_key_exists('description', $settings)) {
			return $fallback;
		}

		return trim((string) $settings['description']);
	}

	/**
	 * Aplica label, placeholder y descripción a un elemento Form API.
	 */
	public function applyToElement(
		array &$element,
		string $group,
		string $field,
	): void {
		$settings = $this->getFieldSettings($group, $field);

		if (!$settings) {
			return;
		}

		$label = trim((string) ($settings['label'] ?? ''));

		if ($label !== '') {
			$element['#title'] = $label;
		}

		if (array_key_exists('description', $settings)) {
			$description = trim((string) $settings['description']);

			if ($description !== '') {
				$element['#description'] = $description;
			} else {
				unset($element['#description']);
			}
		}

		if (array_key_exists('placeholder', $settings)) {
			$placeholder = trim((string) $settings['placeholder']);

			if ($placeholder !== '') {
				$element['#attributes']['placeholder'] = $placeholder;
			} else {
				unset($element['#attributes']['placeholder']);
			}
		}
	}

	/**
	 * Aplica la configuración disponible a todos los campos presentes del formulario.
	 */
	public function applyToForm(array &$form): void
	{
		foreach ($this->getFields() as $group => $fields) {
			if (
				!isset($form[$group]) ||
				!is_array($form[$group]) ||
				!is_array($fields)
			) {
				continue;
			}

			foreach ($fields as $field => $settings) {
				if (
					!isset($form[$group][$field]) ||
					!is_array($form[$group][$field])
				) {
					continue;
				}

				$this->applyToElement(
					$form[$group][$field],
					(string) $group,
					(string) $field,
				);
			}
		}
	}
}
