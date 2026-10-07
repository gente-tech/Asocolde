<?php

namespace Drupal\asocolderma_data_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;

/**
 * Registra en Data Core un asociado creado desde una solicitud de ingreso.
 */
final class AsociadoFromSolicitudRegistrar
{

	private const TABLE_NAME = 'asocolderma_import_asociados';

	public function __construct(
		private readonly Connection $database,
		private readonly DateFormatterInterface $dateFormatter,
		private readonly DataCoreHubspotSyncService $hubspotSync,
		private readonly LoggerChannelInterface $logger,
	) {}

	/**
	 * Crea o actualiza un asociado desde una solicitud de ingreso.
	 */
	public function registerFromSolicitud(NodeInterface $solicitud): void
	{
		if ($solicitud->bundle() !== 'solicitud_ingreso') {
			return;
		}

		$record = $this->buildRecord($solicitud);

		if (empty($record['numero_documento']) && empty($record['correo_principal'])) {
			$this->logger->warning('No se pudo registrar asociado desde solicitud @nid porque no tiene documento ni correo.', [
				'@nid' => $solicitud->id(),
			]);
			return;
		}

		try {
			$existing_id = $this->findExistingRecordId($record);

			if ($existing_id) {
				$this->database
					->update(self::TABLE_NAME)
					->fields($record)
					->condition('id', $existing_id)
					->execute();

				$record['id'] = $existing_id;

				$this->logger->notice('Asociado actualizado desde solicitud @nid. Registro Data Core: @id.', [
					'@nid' => $solicitud->id(),
					'@id' => $existing_id,
				]);
			} else {
				$record['batch_id'] = 0;
				$record['row_number'] = 0;
				$record['created'] = \Drupal::time()->getRequestTime();

				$existing_max_row = (int) $this->database
					->select(self::TABLE_NAME, 'a')
					->fields('a', ['row_number'])
					->orderBy('row_number', 'DESC')
					->range(0, 1)
					->execute()
					->fetchField();

				$record['row_number'] = $existing_max_row + 1;

				$new_id = (int) $this->database
					->insert(self::TABLE_NAME)
					->fields($record)
					->execute();

				$record['id'] = $new_id;

				$this->logger->notice('Asociado creado desde solicitud @nid. Registro Data Core: @id.', [
					'@nid' => $solicitud->id(),
					'@id' => $new_id,
				]);
			}

			$this->hubspotSync->syncCreatedRecord(self::TABLE_NAME, $record);
		} catch (\Throwable $e) {
			$this->logger->error('Error registrando asociado desde solicitud @nid. Error: @error', [
				'@nid' => $solicitud->id(),
				'@error' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Construye el registro compatible con asocolderma_import_asociados.
	 */
	private function buildRecord(NodeInterface $solicitud): array
	{
		$request_time = \Drupal::time()->getRequestTime();

		return [
			'is_active' => 1,
			'created_by' => (int) $solicitud->getOwnerId(),
			'updated_by' => (int) $solicitud->getOwnerId(),
			'updated' => $request_time,

			'id_asocolderma' => $this->value($solicitud, 'field_solicitud_id'),
			'estado_afiliacion' => 'Activo',
			'tipo_asociado' => $this->referencedLabel($solicitud, 'field_tipo_asociado'),
			'fecha_ingreso_asocolderma' => $this->dateFormatter->format($request_time, 'custom', 'Y-m-d'),

			'primer_nombre' => $this->value($solicitud, 'field_nombre1'),
			'segundo_nombre' => $this->value($solicitud, 'field_nombre2'),
			'primer_apellido' => $this->value($solicitud, 'field_apellido1'),
			'segundo_apellido' => $this->value($solicitud, 'field_apellido2'),
			'fecha_nacimiento' => $this->value($solicitud, 'field_fecha_nacimiento'),
			'estado_civil' => $this->referencedLabel($solicitud, 'field_estado_civil'),
			'sexo' => $this->referencedLabel($solicitud, 'field_sexo'),
			'tipo_documento' => $this->referencedLabel($solicitud, 'field_tipo_documento'),
			'numero_documento' => $this->value($solicitud, 'field_numero_documento'),
			'pais_nacimiento' => $this->referencedLabel($solicitud, 'field_pais'),

			'direccion_principal' => $this->value($solicitud, 'field_correspondencia_fisica'),
			'correspondencia_en' => $this->referencedLabel($solicitud, 'field_lugar_correspondencia'),
			'correo_principal' => $this->value($solicitud, 'field_email_principal'),
			'telefono_celular' => $this->value($solicitud, 'field_celular'),

			'registro_medico_tarjeta' => $this->value($solicitud, 'field_registro_medico'),
			'pais_ejercicio' => $this->referencedLabel($solicitud, 'field_pais'),
			'ciudad_ejercicio' => $this->referencedLabel($solicitud, 'field_ciudad_ejercicio'),
			'departamento' => $this->referencedLabel($solicitud, 'field_departamento'),

			'facultad_pregrado' => $this->referencedLabel($solicitud, 'field_facultad_pregrado'),
			'pais_pregrado' => $this->referencedLabel($solicitud, 'field_pais_pregrado'),
			'titulo_universitario' => $this->referencedLabel($solicitud, 'field_titulo_universitario'),
			'universidad_residencia' => $this->referencedLabel($solicitud, 'field_universidad_residencia'),
			'pais_residencia' => $this->referencedLabel($solicitud, 'field_pais_residencia'),
			'especialidad' => 'Dermatología',
			'subespecialidad' => $this->referencedLabel($solicitud, 'field_subespecialidad_cual'),
			'recertificado_camec' => $this->booleanLabel($solicitud, 'field_recertificacion_camec'),

			'carta_presentacion_1' => $this->fileUri($solicitud, 'field_adj_carta_1'),
			'carta_presentacion_2' => $this->fileUri($solicitud, 'field_adj_carta_2'),
			'copia_rut' => $this->fileUri($solicitud, 'field_adj_rut'),
			'copia_cedula_id' => $this->fileUri($solicitud, 'field_adj_id'),
			'carta_solicitud_ingreso' => $this->fileUri($solicitud, 'field_adj_carta_ingreso'),
			'hoja_vida' => $this->fileUri($solicitud, 'field_adj_hv'),
			'copia_diploma_medico' => $this->fileUri($solicitud, 'field_adj_diploma_medico'),
			'copia_diploma_dermatologo' => $this->fileUri($solicitud, 'field_adj_diploma_dermatologo'),
			'registro_rethus' => $this->fileUri($solicitud, 'field_adj_rethus'),
			'carta_autorizacion_verificacion' => $this->fileUri($solicitud, 'field_adj_aut_verificacion'),
			'certificacion_congreso_publicacion' => $this->fileUri($solicitud, 'field_adj_cert_publicacion'),

			'raw_payload' => json_encode([
				'source' => 'solicitud_ingreso',
				'solicitud_nid' => (int) $solicitud->id(),
				'solicitud_id' => $this->value($solicitud, 'field_solicitud_id'),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'validation_status' => 'valid',
			'validation_errors' => NULL,
		];
	}

	/**
	 * Busca si ya existe un asociado por documento o correo.
	 */
	private function findExistingRecordId(array $record): ?int
	{
		$query = $this->database
			->select(self::TABLE_NAME, 'a')
			->fields('a', ['id'])
			->range(0, 1);

		$or = $query->orConditionGroup();

		if (!empty($record['numero_documento'])) {
			$or->condition('numero_documento', $record['numero_documento']);
		}

		if (!empty($record['correo_principal'])) {
			$or->condition('correo_principal', $record['correo_principal']);
		}

		if (!$or->count()) {
			return NULL;
		}

		$query->condition($or);

		$id = $query->execute()->fetchField();

		return $id ? (int) $id : NULL;
	}

	/**
	 * Retorna valor escalar de un campo.
	 */
	private function value(NodeInterface $node, string $field_name): string
	{
		if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
			return '';
		}

		return trim((string) $node->get($field_name)->value);
	}

	/**
	 * Retorna label de entity reference.
	 */
	private function referencedLabel(NodeInterface $node, string $field_name): string
	{
		if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
			return '';
		}

		$entity = $node->get($field_name)->entity;

		return $entity ? trim((string) $entity->label()) : '';
	}

	/**
	 * Retorna Sí/No desde campo booleano.
	 */
	private function booleanLabel(NodeInterface $node, string $field_name): string
	{
		if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
			return 'No';
		}

		return (bool) $node->get($field_name)->value ? 'Sí' : 'No';
	}

	/**
	 * Retorna URI del primer archivo adjunto.
	 */
	private function fileUri(NodeInterface $node, string $field_name): string
	{
		if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
			return '';
		}

		$file = $node->get($field_name)->entity;

		return $file ? (string) $file->getFileUri() : '';
	}
}
