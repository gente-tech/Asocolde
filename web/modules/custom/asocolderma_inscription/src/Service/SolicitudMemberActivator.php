<?php

namespace Drupal\asocolderma_inscription\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Convierte el usuario aspirante en dermatólogo activo.
 */
final class SolicitudMemberActivator
{

	private const ASPIRANTE_ROLE = 'aspirante';
	private const DERMATOLOGIST_ROLE = 'dermatologist';

	public function __construct(
		private readonly EntityTypeManagerInterface $entityTypeManager,
		private readonly LoggerChannelInterface $logger,
	) {}

	/**
	 * Activa el propietario de la solicitud como dermatólogo.
	 */
	public function activateFromSolicitud(NodeInterface $solicitud): void
	{
		if ($solicitud->bundle() !== 'solicitud_ingreso') {
			throw new \InvalidArgumentException('La entidad recibida no es una solicitud de ingreso.');
		}

		$uid = (int) $solicitud->getOwnerId();
		if ($uid <= 0) {
			throw new \RuntimeException('La solicitud no tiene un usuario propietario válido.');
		}

		/** @var \Drupal\user\UserInterface|null $user */
		$user = $this->entityTypeManager->getStorage('user')->load($uid);
		if (!$user instanceof UserInterface) {
			throw new \RuntimeException(sprintf('No fue posible cargar el usuario propietario de la solicitud %s.', $solicitud->id()));
		}

		$this->copySolicitudDataToUser($solicitud, $user);
		$this->activateRoles($user);

		$user->activate();
		$user->save();

		$this->deleteAspiranteProfiles($user);

		$this->logger->notice('Usuario @uid activado como dermatologist desde la solicitud @nid.', [
			'@uid' => $user->id(),
			'@nid' => $solicitud->id(),
		]);
	}

	/**
	 * Remueve aspirante y asigna dermatologist.
	 */
	private function activateRoles(UserInterface $user): void
	{
		if ($user->hasRole(self::ASPIRANTE_ROLE)) {
			$user->removeRole(self::ASPIRANTE_ROLE);
		}

		if (!$user->hasRole(self::DERMATOLOGIST_ROLE)) {
			$user->addRole(self::DERMATOLOGIST_ROLE);
		}
	}

	/**
	 * Copia campos compatibles de solicitud_ingreso al usuario.
	 */
	private function copySolicitudDataToUser(NodeInterface $solicitud, UserInterface $user): void
	{
		$this->copyScalar($solicitud, $user, 'field_nombre1', 'field_first_name');
		$this->copyScalar($solicitud, $user, 'field_nombre2', 'field_second_name');
		$this->copyScalar($solicitud, $user, 'field_apellido1', 'field_first_surname');
		$this->copyScalar($solicitud, $user, 'field_apellido2', 'field_second_surname');

		$this->copyScalar($solicitud, $user, 'field_fecha_nacimiento', 'field_birthday');
		$this->copyScalar($solicitud, $user, 'field_numero_documento', 'field_document_number');
		$this->copyScalar($solicitud, $user, 'field_registro_medico', 'field_rethus');

		$this->copyScalar($solicitud, $user, 'field_email_principal', 'field_mail_personal');
		$this->copyScalarIfEmpty($solicitud, $user, 'field_email_principal', 'field_mail_public');

		$this->copyScalar($solicitud, $user, 'field_celular', 'field_phone_mobile');

		$this->copyScalar($solicitud, $user, 'field_correspondencia_fisica', 'field_address_home');
		$this->copyScalar($solicitud, $user, 'field_direccion_institucional', 'field_address_office');

		$this->copyBoolean($solicitud, $user, 'field_terms_accepted', 'field_legal_terms');
		$this->copyBoolean($solicitud, $user, 'field_recertificacion_camec', 'field_recertificate');

		$this->copyEntityReference($solicitud, $user, 'field_pais', 'field_country');
		$this->copyEntityReference($solicitud, $user, 'field_ciudad_ejercicio', 'field_city');
		$this->copyEntityReference($solicitud, $user, 'field_titulo_universitario', 'field_university_degree');
		$this->copyEntityReference($solicitud, $user, 'field_facultad_pregrado', 'field_university_undergraduate');
		$this->copyEntityReference($solicitud, $user, 'field_universidad_residencia', 'field_university_residence');

		$this->copyReferencedLabelToText($solicitud, $user, 'field_departamento', 'field_department');
		$this->copyReferencedLabelToText($solicitud, $user, 'field_estado_civil', 'field_civil_status');

		$this->copyReferencedLabelToList($solicitud, $user, 'field_sexo', 'field_sex');
		$this->copyReferencedLabelToList($solicitud, $user, 'field_tipo_documento', 'field_document_type');
		$this->copyReferencedLabelToList($solicitud, $user, 'field_tipo_asociado', 'field_type_associated');
		$this->copyReferencedLabelToList($solicitud, $user, 'field_lugar_correspondencia', 'field_correspondence');

		$this->copyEntityReferenceAppend($solicitud, $user, 'field_subespecialidad_cual', 'field_services_specialties');

		$this->copyFile($solicitud, $user, 'field_adj_carta_ingreso', 'field_admission_application');
		$this->copyFile($solicitud, $user, 'field_adj_id', 'field_document_file');
		$this->copyFile($solicitud, $user, 'field_adj_hv', 'field_cv');
		$this->copyFile($solicitud, $user, 'field_adj_rut', 'field_rut');
		$this->copyFile($solicitud, $user, 'field_adj_diploma_medico', 'field_undergraduate_diploma');
		$this->copyFile($solicitud, $user, 'field_adj_diploma_dermatologo', 'field_postgraduate_diploma');
		$this->copyFile($solicitud, $user, 'field_adj_rethus', 'field_professional_card');
		$this->copyFile($solicitud, $user, 'field_adj_carta_1', 'field_recommendation_1');
		$this->copyFile($solicitud, $user, 'field_adj_carta_2', 'field_recommendation_2');
	}

	private function copyScalar(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$value = $source->get($source_field)->value;
		if ($value === NULL || $value === '') {
			return;
		}

		$target->set($target_field, $value);
	}

	private function copyScalarIfEmpty(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$target->hasField($target_field) || !$target->get($target_field)->isEmpty()) {
			return;
		}

		$this->copyScalar($source, $target, $source_field, $target_field);
	}

	private function copyBoolean(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$target->set($target_field, (int) (bool) $source->get($source_field)->value);
	}

	private function copyEntityReference(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$target_id = (int) ($source->get($source_field)->target_id ?? 0);
		if ($target_id <= 0) {
			return;
		}

		$target->set($target_field, ['target_id' => $target_id]);
	}

	private function copyEntityReferenceAppend(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$target_id = (int) ($source->get($source_field)->target_id ?? 0);
		if ($target_id <= 0) {
			return;
		}

		$values = $target->get($target_field)->getValue();

		foreach ($values as $item) {
			if ((int) ($item['target_id'] ?? 0) === $target_id) {
				return;
			}
		}

		$values[] = ['target_id' => $target_id];
		$target->set($target_field, $values);
	}

	private function copyReferencedLabelToText(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$entity = $source->get($source_field)->entity;
		if (!$entity) {
			return;
		}

		$target->set($target_field, (string) $entity->label());
	}

	private function copyReferencedLabelToList(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$entity = $source->get($source_field)->entity;
		if (!$entity) {
			return;
		}

		$matched_value = $this->matchAllowedListValue($target, $target_field, (string) $entity->label());

		if ($matched_value === NULL) {
			$this->logger->warning('No se pudo mapear el valor "@label" al campo lista @field del usuario @uid.', [
				'@label' => (string) $entity->label(),
				'@field' => $target_field,
				'@uid' => $target->id(),
			]);
			return;
		}

		$target->set($target_field, $matched_value);
	}

	private function copyFile(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): void
	{
		if (!$this->canCopy($source, $target, $source_field, $target_field)) {
			return;
		}

		$items = $source->get($source_field)->getValue();
		if (empty($items)) {
			return;
		}

		$values = [];

		foreach ($items as $item) {
			$fid = (int) ($item['target_id'] ?? 0);
			if ($fid > 0) {
				$values[] = ['target_id' => $fid];
			}
		}

		if ($values) {
			$target->set($target_field, $values);
		}
	}

	private function canCopy(NodeInterface $source, UserInterface $target, string $source_field, string $target_field): bool
	{
		return $source->hasField($source_field)
			&& !$source->get($source_field)->isEmpty()
			&& $target->hasField($target_field);
	}

	private function matchAllowedListValue(UserInterface $user, string $field_name, string $label): ?string
	{
		$definition = $user->get($field_name)->getFieldDefinition();
		$allowed_values = $definition->getSetting('allowed_values') ?: [];

		if ($allowed_values === []) {
			return $label;
		}

		if (array_key_exists($label, $allowed_values)) {
			return $label;
		}

		$normalized_label = $this->normalize($label);

		foreach ($allowed_values as $key => $allowed_label) {
			if ($this->normalize((string) $key) === $normalized_label) {
				return (string) $key;
			}

			if ($this->normalize((string) $allowed_label) === $normalized_label) {
				return (string) $key;
			}
		}

		return NULL;
	}

	private function normalize(string $value): string
	{
		$value = trim(mb_strtolower($value, 'UTF-8'));
		$value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
		$value = preg_replace('/\s+/', ' ', $value);

		return trim((string) $value);
	}

	/**
	 * Elimina el profile aspirante si existe.
	 */
	private function deleteAspiranteProfiles(UserInterface $user): void
	{
		if (!$this->entityTypeManager->hasDefinition('profile')) {
			return;
		}

		$storage = $this->entityTypeManager->getStorage('profile');
		$profiles = $storage->loadByProperties([
			'uid' => (int) $user->id(),
			'type' => 'aspirante',
		]);

		foreach ($profiles as $profile) {
			$profile->delete();
		}
	}
}
