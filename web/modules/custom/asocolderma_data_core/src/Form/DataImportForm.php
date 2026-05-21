<?php

namespace Drupal\asocolderma_data_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DataImportForm extends FormBase
{

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_data_core_import_form';
	}

	/**
	 * Retorna las etiquetas legibles por tabla de importación.
	 */
	protected function getImportTypeLabels(): array
	{
		return [
			'asocolderma_import_patrocinadores' => 'Patrocinadores',
			'asocolderma_import_asociados' => 'Asociados',
			'asocolderma_import_residentes' => 'Residentes',
			'asocolderma_import_proveedores' => 'Proveedores',
			'asocolderma_import_empleados' => 'Empleados',
		];
	}

	/**
	 * Genera un UUID único para identificar el proceso de importación.
	 */
	protected function generateImportUuid(): string
	{
		return \Drupal::service('uuid')->generate();
	}

	/**
	 * Construye la referencia principal del registro importado.
	 */
	protected function buildReferenceLabel(string $table, array $values): string
	{
		if (in_array($table, [
			'asocolderma_import_asociados',
			'asocolderma_import_residentes',
			'asocolderma_import_empleados',
		], TRUE)) {
			$parts = [
				$values['primer_nombre'] ?? '',
				$values['segundo_nombre'] ?? '',
				$values['primer_apellido'] ?? '',
				$values['segundo_apellido'] ?? '',
			];

			$parts = array_filter(array_map('trim', $parts));

			return trim(implode(' ', $parts));
		}

		if (in_array($table, [
			'asocolderma_import_patrocinadores',
			'asocolderma_import_proveedores',
		], TRUE)) {
			return trim((string) ($values['razon_social'] ?? ''));
		}

		return '';
	}

	/**
	 * Obtiene el documento o NIT del registro importado.
	 */
	protected function buildReferenceDocument(string $table, array $values): string
	{
		if (in_array($table, [
			'asocolderma_import_patrocinadores',
			'asocolderma_import_proveedores',
		], TRUE)) {
			return trim((string) ($values['nit'] ?? ''));
		}

		if (in_array($table, [
			'asocolderma_import_asociados',
			'asocolderma_import_residentes',
			'asocolderma_import_empleados',
		], TRUE)) {
			return trim((string) ($values['numero_documento'] ?? ''));
		}

		return '';
	}

	/**
	 * Retorna las columnas esperadas por tabla de importación.
	 */
	protected function getExpectedColumns(): array
	{
		return [
			'asocolderma_import_patrocinadores' => [
				'id_asocolderma',
				'estado_patrocinador',
				'tipo_patrocinador',
				'anios_vinculacion',
				'razon_social',
				'nombre_comercial',
				'nit',
				'pais',
				'ciudad_sede_principal',
				'direccion_fiscal',
				'sitio_web_corporativo',
				'nombre_contacto_principal',
				'cargo_contacto',
				'correo_corporativo',
				'telefono_corporativo',
				'celular_contacto',
				'correo_contacto_2',
				'nombre_contacto_2',
				'cargo_contacto_2',
				'estado_pago',
				'valor_comprometido_evento_1',
				'valor_pagado_evento_1',
				'valor_comprometido_evento_2',
				'valor_pagado_evento_2',
				'valor_comprometido_evento_3',
				'valor_pagado_evento_3',
				'metodo_pago',
				'iva_aplicable',
				'datos_bancarios',
				'carta_compromiso_evento_1',
				'carta_compromiso_evento_2',
				'carta_compromiso_evento_3',
				'contrato_evento_1',
				'contrato_evento_2',
				'contrato_evento_3',
				'copia_rut',
				'copia_camara_comercio',
				'copia_cedula_representante',
				'copia_certificacion_bancaria',
				'otros_documentos_adjuntos',
				'observaciones_generales',
				'eventos_vinculados',
				'beneficios_pactados',
				'contacto_comercial_asocolderma',
			],

			'asocolderma_import_asociados' => [
				'id_asocolderma',
				'estado_afiliacion',
				'tipo_asociado',
				'fecha_ingreso_asocolderma',
				'edad',
				'miembro_numero_1',
				'miembro_numero_2',
				'capitulo_regional',
				'primer_nombre',
				'segundo_nombre',
				'primer_apellido',
				'segundo_apellido',
				'fecha_nacimiento',
				'estado_civil',
				'sexo',
				'tipo_documento',
				'numero_documento',
				'pais_nacimiento',
				'direccion_principal',
				'correspondencia_en',
				'correo_principal',
				'telefono_celular',
				'registro_medico_tarjeta',
				'pais_ejercicio',
				'ciudad_ejercicio',
				'departamento',
				'facultad_pregrado',
				'pais_pregrado',
				'titulo_universitario',
				'universidad_residencia',
				'pais_residencia',
				'especialidad',
				'subespecialidad',
				'pertenece_filial',
				'grupos_estudio',
				'recertificado_camec',
				'fecha_inicio_recertificacion',
				'fecha_fin_recertificacion',
				'mostrar_perfil_publico',
				'correo_publico',
				'sitio_web_publico',
				'horario_atencion',
				'telefono_publico_1',
				'telefono_publico_2',
				'perfil_profesional',
				'direccion_consultorio',
				'imagenes_consultorio',
				'palabras_clave_tags',
				'capilaroscopia',
				'cicatrices_acne',
				'dermatologia_general',
				'dermatologia_oncologica',
				'dermatologia_pediatrica',
				'dermatopatologia',
				'fototerapia',
				'laser',
				'microdermoabrasion',
				'peeling',
				'inyectables',
				'trasplante_pelo',
				'otros_servicios',
				'estado_cuota',
				'valor_cuota_vigente',
				'fecha_ultimo_pago',
				'comprobante_pago',
				'carta_presentacion_1',
				'carta_presentacion_2',
				'copia_rut',
				'copia_cedula_id',
				'carta_solicitud_ingreso',
				'hoja_vida',
				'copia_diploma_medico',
				'copia_diploma_dermatologo',
				'registro_rethus',
				'carta_autorizacion_verificacion',
				'certificacion_congreso_publicacion',
				'otros_documentos_adjuntos',
			],

			'asocolderma_import_residentes' => [
				'id_asocolderma',
				'estado_residente',
				'primer_nombre',
				'segundo_nombre',
				'primer_apellido',
				'segundo_apellido',
				'fecha_nacimiento',
				'estado_civil',
				'sexo',
				'tipo_documento',
				'numero_documento',
				'pais',
				'ciudad',
				'departamento',
				'direccion_fisica_institucional',
				'correo_principal',
				'telefono_celular',
				'registro_medico_tarjeta',
				'universidad_residencia',
				'programa_especialidad',
				'anio_residencia',
				'fecha_estimada_finalizacion',
				'aspira_asociarse',
				'eventos_participados',
				'ponencia_presentacion',
				'observaciones',
				'copia_cedula_id',
				'carta_aval_universidad',
				'otros_documentos_adjuntos',
			],

			'asocolderma_import_proveedores' => [
				'id_asocolderma',
				'estado_proveedor',
				'tipo_proveedor',
				'anios_vinculacion_desde',
				'razon_social',
				'nombre_comercial',
				'nit',
				'pais',
				'ciudad_sede_principal',
				'direccion_fiscal',
				'sitio_web_corporativo',
				'nombre_contacto_principal',
				'cargo_contacto',
				'correo_corporativo',
				'telefono_corporativo',
				'celular_contacto',
				'descripcion_servicio_prestado',
				'eventos_proyectos_vinculados',
				'responsable_asocolderma_gestiona',
				'observaciones_generales',
				'estado_pago',
				'valor_contrato_vigente',
				'valor_pagado',
				'metodo_pago',
				'iva',
				'datos_bancarios',
				'copia_contrato_vigente',
				'copia_rut',
				'copia_camara_comercio',
				'copia_cedula_representante',
				'copia_certificacion_bancaria',
				'otros_documentos_adjuntos',
			],

			'asocolderma_import_empleados' => [
				'id_asocolderma',
				'estado_empleado',
				'cargo',
				'area_dependencia',
				'tipo_contrato',
				'fecha_ingreso',
				'fecha_retiro',
				'primer_nombre',
				'segundo_nombre',
				'primer_apellido',
				'segundo_apellido',
				'fecha_nacimiento',
				'estado_civil',
				'sexo',
				'tipo_documento',
				'numero_documento',
				'pais',
				'ciudad_residencia',
				'departamento',
				'direccion_fisica_personal',
				'correo_personal',
				'telefono_celular_personal',
				'correo_corporativo',
				'observaciones_administrativas',
				'contacto_emergencia_nombre',
				'contacto_emergencia_telefono',
				'contacto_emergencia_relacion',
				'salario_mensual',
				'tipo_pago',
				'eps',
				'fondo_pensiones',
				'arl',
				'caja_compensacion',
				'datos_bancarios_banco',
				'datos_bancarios_tipo_cuenta',
				'datos_bancarios_numero_cuenta',
				'copia_contrato_vigente',
				'copia_rut',
				'copia_cedula',
				'copia_certificacion_bancaria',
				'copia_hoja_vida',
				'otros_documentos_adjuntos',
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$form['#attributes']['enctype'] = 'multipart/form-data';

		$form['import_type'] = [
			'#type' => 'select',
			'#title' => $this->t('Tabla a importar'),
			'#description' => $this->t('Seleccione el tipo de información que desea importar desde el Excel.'),
			'#required' => TRUE,
			'#empty_option' => $this->t('Seleccione una tabla'),
			'#options' => [
				'asocolderma_import_patrocinadores' => $this->t('Patrocinadores'),
				'asocolderma_import_asociados' => $this->t('Asociados'),
				'asocolderma_import_residentes' => $this->t('Residentes'),
				'asocolderma_import_proveedores' => $this->t('Proveedores'),
				'asocolderma_import_empleados' => $this->t('Empleados'),
			],
		];

		$form['excel_file'] = [
			'#type' => 'file',
			'#title' => $this->t('Archivo Excel'),
			'#description' => $this->t('Cargue un archivo .xlsx con la información a importar.'),
			'#required' => TRUE,
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Importar'),
			'#button_type' => 'primary',
		];

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$validators = [
			'file_validate_extensions' => ['xlsx'],
		];

		$directory = 'public://imports/data_core';
		\Drupal::service('file_system')->prepareDirectory(
			$directory,
			\Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
		);

		$file = file_save_upload('excel_file', $validators, $directory, 0);

		if (!$file) {
			$form_state->setErrorByName('excel_file', $this->t('Debe cargar un archivo Excel válido con extensión .xlsx.'));
			return;
		}

		$file->setPermanent();
		$file->save();

		$form_state->setValue('uploaded_file', $file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$table = $form_state->getValue('import_type');
		$file = $form_state->getValue('uploaded_file');
		$import_uuid = $this->generateImportUuid();
		$import_labels = $this->getImportTypeLabels();
		$target_label = $import_labels[$table] ?? $table;
		$request_time = \Drupal::time()->getRequestTime();

		if (!$file) {
			$this->messenger()->addError($this->t('No fue posible procesar el archivo cargado.'));
			return;
		}

		$expected_columns = $this->getExpectedColumns();

		if (!isset($expected_columns[$table])) {
			$this->messenger()->addError($this->t('La tabla seleccionada no es válida.'));
			return;
		}

		$file_path = \Drupal::service('file_system')->realpath($file->getFileUri());

		try {
			$spreadsheet = IOFactory::load($file_path);
			$worksheet = $spreadsheet->getSheet(0);

			$rows = $worksheet->toArray(NULL, TRUE, TRUE, FALSE);

			if (empty($rows) || empty($rows[0])) {
				$this->messenger()->addError($this->t('El archivo Excel no contiene información válida.'));
				return;
			}

			$headers = array_map(function ($value) {
				return mb_strtolower(trim((string) $value));
			}, $rows[0]);

			$expected = $expected_columns[$table];

			if (count($headers) !== count($expected)) {
				$this->messenger()->addError($this->t(
					'La estructura del archivo no coincide con la tabla seleccionada. Se esperaban @expected columnas y se encontraron @found.',
					[
						'@expected' => count($expected),
						'@found' => count($headers),
					]
				));
				return;
			}

			$this->messenger()->addStatus($this->t(
				'Archivo validado correctamente. Se detectaron @count columnas válidas para la tabla @table.',
				[
					'@count' => count($headers),
					'@table' => $table,
				]
			));

			$database = \Drupal::database();
			$batch_id = $request_time;
			$created = $request_time;
			$started = $request_time;

			$current_user = \Drupal::currentUser();
			$request = \Drupal::request();

			$total_rows = 0;
			$inserted = 0;
			$failed = 0;

			foreach (array_slice($rows, 1) as $row) {
				$is_empty_row = TRUE;

				foreach ($row as $cell) {
					if (trim((string) $cell) !== '') {
						$is_empty_row = FALSE;
						break;
					}
				}

				if (!$is_empty_row) {
					$total_rows++;
				}
			}

			$database->insert('asocolderma_data_import_log')
				->fields([
					'import_uuid' => $import_uuid,
					'uid' => (int) $current_user->id(),
					'username' => $current_user->getAccountName(),
					'target_label' => $target_label,
					'original_filename' => $file->getFilename(),
					'stored_filename' => basename($file->getFileUri()),
					'file_uri' => $file->getFileUri(),
					'file_count' => 1,
					'total_rows' => $total_rows,
					'imported_rows' => 0,
					'failed_rows' => 0,
					'status' => 'processing',
					'message' => 'Importación en proceso.',
					'ip' => $request->getClientIp(),
					'user_agent' => $request->headers->get('User-Agent'),
					'created' => $created,
					'finished' => NULL,
					'duration_seconds' => NULL,
				])
				->execute();

			foreach (array_slice($rows, 1) as $index => $row) {
				$row_number = $index + 2;

				$is_empty_row = TRUE;
				foreach ($row as $cell) {
					if (trim((string) $cell) !== '') {
						$is_empty_row = FALSE;
						break;
					}
				}

				if ($is_empty_row) {
					continue;
				}

				$values = [
					'batch_id' => $batch_id,
					'row_number' => $row_number,
					'created' => $created,
					'raw_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
					'validation_status' => 'pending',
				];

				foreach ($expected as $position => $column_name) {
					$values[$column_name] = $row[$position] ?? NULL;
				}

				$database->insert($table)
					->fields($values)
					->execute();

				$inserted++;
			}

			$finished = \Drupal::time()->getRequestTime();
			$duration_seconds = $finished - $started;
			$status = $failed > 0 ? 'completed_with_errors' : 'completed';
			$message = $failed > 0
				? 'Importación finalizada con errores en algunas filas.'
				: 'Importación finalizada correctamente.';

			$database->update('asocolderma_data_import_log')
				->fields([
					'imported_rows' => $inserted,
					'failed_rows' => $failed,
					'status' => $status,
					'message' => $message,
					'finished' => $finished,
					'duration_seconds' => $duration_seconds,
				])
				->condition('import_uuid', $import_uuid)
				->execute();

			$this->messenger()->addStatus($this->t(
				'Importación completada. Se insertaron @count registros en la tabla @table.',
				[
					'@count' => $inserted,
					'@table' => $table,
				]
			));
		} catch (\Throwable $e) {
			$this->messenger()->addError($this->t('Error procesando el archivo: @message', [
				'@message' => $e->getMessage(),
			]));
		}
	}
}
