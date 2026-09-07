<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Define y resuelve las variables disponibles para Zoho Sign.
 */
final class SolicitudZohoVariableManager
{

	private const BUNDLE = 'solicitud_ingreso';

	public function __construct(
		private readonly EntityFieldManagerInterface $entityFieldManager,
	) {}

	/**
	 * Retorna el diccionario completo de variables.
	 *
	 * La clave de cada campo corresponde al nombre técnico de Drupal
	 * sin el prefijo "field_".
	 */
	public function getDefinitions(): array
	{
		$definitions = [];

		$field_definitions = $this->entityFieldManager
			->getFieldDefinitions('node', self::BUNDLE);

		foreach ($field_definitions as $field_name => $definition) {
			if (!str_starts_with($field_name, 'field_')) {
				continue;
			}

			$variable_key = substr($field_name, 6);

			$definitions[$variable_key] = [
				'label' => (string) $definition->getLabel(),
				'field' => $field_name,
				'type' => $definition->getType(),
				'source' => 'solicitud',
			];
		}

		$definitions = array_merge(
			$this->getCalculatedDefinitions(),
			$definitions,
		);

		ksort($definitions);

		return $definitions;
	}

	/**
	 * Resuelve todas las variables de una solicitud como texto.
	 */
	public function resolveAll(NodeInterface $node): array
	{
		if ($node->bundle() !== self::BUNDLE) {
			throw new \InvalidArgumentException(
				'El nodo recibido no es una solicitud de ingreso.',
			);
		}

		$values = [];

		foreach ($this->getDefinitions() as $variable_key => $definition) {
			if (($definition['source'] ?? '') === 'calculated') {
				$values[$variable_key] = $this->resolveCalculatedValue(
					$variable_key,
					$node,
				);
				continue;
			}

			$values[$variable_key] = $this->resolveFieldValue(
				$node,
				(string) $definition['field'],
				(string) $definition['type'],
			);
		}

		return $values;
	}

	/**
	 * Retorna las variables calculadas que no corresponden a un solo campo.
	 */
	private function getCalculatedDefinitions(): array
	{
		return [
			'aspirante_correo_cuenta' => [
				'label' => 'Correo de la cuenta del aspirante',
				'field' => '-',
				'type' => 'calculated',
				'source' => 'calculated',
			],
			'aspirante_nombre_completo' => [
				'label' => 'Nombre completo del aspirante',
				'field' => 'field_nombre1 + field_nombre2 + field_apellido1 + field_apellido2',
				'type' => 'calculated',
				'source' => 'calculated',
			],
			'solicitud_fecha_creacion' => [
				'label' => 'Fecha de creación de la solicitud',
				'field' => 'created',
				'type' => 'calculated',
				'source' => 'calculated',
			],
			'solicitud_nid' => [
				'label' => 'Identificador interno de la solicitud',
				'field' => 'nid',
				'type' => 'calculated',
				'source' => 'calculated',
			],
		];
	}

	/**
	 * Resuelve el valor de un campo según su tipo.
	 */
	private function resolveFieldValue(
		NodeInterface $node,
		string $field_name,
		string $field_type,
	): string {
		if (
			!$node->hasField($field_name)
			|| $node->get($field_name)->isEmpty()
		) {
			return '';
		}

		$field = $node->get($field_name);

		return match ($field_type) {
			'entity_reference' => $this->resolveEntityReference($node, $field_name),
			'file' => $this->resolveFile($node, $field_name),
			'boolean' => ((bool) $field->value) ? 'Sí' : 'No',
			'datetime' => $this->formatDate((string) $field->value),
			default => trim((string) $field->value),
		};
	}

	/**
	 * Convierte una referencia de entidad en su etiqueta.
	 */
	private function resolveEntityReference(
		NodeInterface $node,
		string $field_name,
	): string {
		$labels = [];

		foreach ($node->get($field_name)->referencedEntities() as $entity) {
			$labels[] = trim((string) $entity->label());
		}

		return implode(', ', array_filter($labels));
	}

	/**
	 * Convierte un campo de archivo en el nombre original del archivo.
	 */
	private function resolveFile(
		NodeInterface $node,
		string $field_name,
	): string {
		$filenames = [];

		foreach ($node->get($field_name)->referencedEntities() as $file) {
			if (method_exists($file, 'getFilename')) {
				$filenames[] = trim((string) $file->getFilename());
			} else {
				$filenames[] = trim((string) $file->label());
			}
		}

		return implode(', ', array_filter($filenames));
	}

	/**
	 * Resuelve variables calculadas.
	 */
	private function resolveCalculatedValue(
		string $variable_key,
		NodeInterface $node,
	): string {
		return match ($variable_key) {
			'aspirante_nombre_completo' => $this->resolveFullName($node),
			'aspirante_correo_cuenta' => $this->resolveAccountEmail($node),
			'solicitud_fecha_creacion' => date(
				'd/m/Y',
				(int) $node->getCreatedTime(),
			),
			'solicitud_nid' => (string) $node->id(),
			default => '',
		};
	}

	/**
	 * Construye el nombre completo del aspirante.
	 */
	private function resolveFullName(NodeInterface $node): string
	{
		$parts = [];

		foreach (
			[
				'field_nombre1',
				'field_nombre2',
				'field_apellido1',
				'field_apellido2',
			] as $field_name
		) {
			if (
				$node->hasField($field_name)
				&& !$node->get($field_name)->isEmpty()
			) {
				$parts[] = trim((string) $node->get($field_name)->value);
			}
		}

		$full_name = trim(implode(' ', array_filter($parts)));

		if ($full_name !== '') {
			return $full_name;
		}

		$owner = $node->getOwner();

		return $owner
			? trim((string) $owner->getDisplayName())
			: '';
	}

	/**
	 * Obtiene el correo de la cuenta propietaria de la solicitud.
	 */
	private function resolveAccountEmail(NodeInterface $node): string
	{
		$owner = $node->getOwner();

		return $owner && $owner->getEmail()
			? trim((string) $owner->getEmail())
			: '';
	}

	/**
	 * Formatea una fecha almacenada por Drupal.
	 */
	private function formatDate(string $value): string
	{
		if ($value === '') {
			return '';
		}

		try {
			$date = new \DateTimeImmutable($value);
			return $date->format('d/m/Y');
		} catch (\Throwable) {
			return $value;
		}
	}
}
