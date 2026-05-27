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
	 * Retorna la columna única usada para detectar si un registro ya existe.
	 */
	protected function getUniqueIdentifierColumn(string $table): ?string
	{
		if (in_array($table, [
			'asocolderma_import_patrocinadores',
			'asocolderma_import_proveedores',
		], TRUE)) {
			return 'nit';
		}

		if (in_array($table, [
			'asocolderma_import_asociados',
			'asocolderma_import_residentes',
			'asocolderma_import_empleados',
		], TRUE)) {
			return 'numero_documento';
		}

		return NULL;
	}

	/**
	 * Normaliza valores para comparar datos provenientes del Excel contra la base de datos.
	 */
	protected function normalizeImportValue($value): string
	{
		if ($value === NULL) {
			return '';
		}

		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		$value = trim((string) $value);

		if ($value === '') {
			return '';
		}

		// Elimina espacios internos comunes en valores numéricos.
		$numeric_candidate = str_replace(' ', '', $value);

		// Normaliza formatos numéricos comunes:
		// 100000.00
		// 100000,00
		// 1.000.000,80
		// 1,000,000.80
		if (preg_match('/^-?[\d.,]+$/', $numeric_candidate)) {
			$has_comma = str_contains($numeric_candidate, ',');
			$has_dot = str_contains($numeric_candidate, '.');

			if ($has_comma && $has_dot) {
				$last_comma = strrpos($numeric_candidate, ',');
				$last_dot = strrpos($numeric_candidate, '.');

				if ($last_comma > $last_dot) {
					// Formato latino: 1.000.000,80
					$numeric_candidate = str_replace('.', '', $numeric_candidate);
					$numeric_candidate = str_replace(',', '.', $numeric_candidate);
				} else {
					// Formato inglés: 1,000,000.80
					$numeric_candidate = str_replace(',', '', $numeric_candidate);
				}
			} elseif ($has_comma && !$has_dot) {
				// Formato decimal latino simple: 100000,80
				$numeric_candidate = str_replace(',', '.', $numeric_candidate);
			}

			if (is_numeric($numeric_candidate)) {
				$normalized = rtrim(rtrim(number_format((float) $numeric_candidate, 6, '.', ''), '0'), '.');

				return $normalized === '-0' ? '0' : $normalized;
			}
		}

		return $value;
	}

	/**
	 * Busca un registro existente por la columna única definida para la tabla.
	 */
	protected function findExistingImportRecordId(string $table, string $unique_column, $unique_value): ?int
	{
		$unique_value = $this->normalizeImportValue($unique_value);

		if ($unique_value === '') {
			return NULL;
		}

		$record_id = \Drupal::database()
			->select($table, 't')
			->fields('t', ['id'])
			->condition($unique_column, $unique_value)
			->range(0, 1)
			->execute()
			->fetchField();

		return $record_id ? (int) $record_id : NULL;
	}

	/**
	 * Determina si los datos importados son diferentes a los datos existentes.
	 *
	 * No compara campos técnicos del proceso de importación, porque cambian en
	 * cada carga y generarían falsos positivos.
	 */
	protected function importRowHasChanges(string $table, int $record_id, array $values, array $expected_columns): bool
	{
		$excluded_columns = [
			'id',
			'batch_id',
			'created',
			'changed',
			'updated',
			'updated_at',
			'created_at',
		];

		$columns_to_compare = array_values(array_diff($expected_columns, $excluded_columns));

		if (empty($columns_to_compare)) {
			return FALSE;
		}

		$existing = \Drupal::database()
			->select($table, 't')
			->fields('t', $columns_to_compare)
			->condition('id', $record_id)
			->execute()
			->fetchAssoc();

		if (!$existing) {
			return TRUE;
		}

		foreach ($columns_to_compare as $column_name) {
			$new_value = $this->normalizeImportValue($values[$column_name] ?? NULL);
			$current_value = $this->normalizeImportValue($existing[$column_name] ?? NULL);

			if ($new_value !== $current_value) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Retorna los cambios detectados por columna para un registro existente.
	 *
	 * Excluye campos técnicos del proceso de importación para evitar falsos
	 * positivos en auditoría.
	 */
	protected function getImportRowChanges(string $table, int $record_id, array $values, array $expected_columns): array
	{
		$excluded_columns = [
			'id',
			'batch_id',
			'created',
			'changed',
			'updated',
			'updated_at',
			'created_at',
		];

		$columns_to_compare = array_values(array_diff($expected_columns, $excluded_columns));

		if (empty($columns_to_compare)) {
			return [];
		}

		$existing = \Drupal::database()
			->select($table, 't')
			->fields('t', $columns_to_compare)
			->condition('id', $record_id)
			->execute()
			->fetchAssoc();

		if (!$existing) {
			return [];
		}

		$changes = [];

		foreach ($columns_to_compare as $column_name) {
			$new_value = $this->normalizeImportValue($values[$column_name] ?? NULL);
			$current_value = $this->normalizeImportValue($existing[$column_name] ?? NULL);

			if ($new_value !== $current_value) {
				$changes[] = [
					'field_name' => $column_name,
					'old_value' => $existing[$column_name] ?? NULL,
					'new_value' => $values[$column_name] ?? NULL,
				];
			}
		}

		return $changes;
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

		$form['intro'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => [
					'data-core-page-intro',
				],
			],
		];

		$form['intro']['title'] = [
			'#markup' => '<h1 class="data-core-page-title">Importación de data</h1>',
		];

		$form['intro']['description'] = [
			'#markup' => '<p class="data-core-page-description">Carga archivos Excel institucionales para actualizar la información de patrocinadores, asociados, residentes, proveedores y empleados.</p>',
		];

		$form['import_layout'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => [
					'data-core-import-layout',
				],
			],
		];

		$form['import_layout']['templates'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => [
					'data-core-import-templates',
				],
			],
		];

		$form['import_layout']['templates']['title'] = [
			'#markup' => '<h2 class="data-core-import-templates__title">Plantillas de importación</h2>',
		];

		$form['import_layout']['templates']['description'] = [
			'#markup' => '<p class="data-core-import-templates__description">Descargue la plantilla correspondiente antes de cargar información. El archivo debe conservar los nombres de columnas definidos por el sistema.</p>',
		];

		$form['import_layout']['templates']['links'] = [
			'#markup' => '
				<div class="data-core-template-links">
				<a class="data-core-template-link" href="/gestion-data/importacion/plantilla/patrocinadores">Patrocinadores</a>
				<a class="data-core-template-link" href="/gestion-data/importacion/plantilla/proveedores">Proveedores</a>
				<a class="data-core-template-link" href="/gestion-data/importacion/plantilla/asociados">Asociados</a>
				<a class="data-core-template-link" href="/gestion-data/importacion/plantilla/residentes">Residentes</a>
				<a class="data-core-template-link" href="/gestion-data/importacion/plantilla/empleados">Empleados</a>
				</div>
			',
		];

		$form['import_layout']['content'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => [
					'data-core-import-form-card',
				],
			],
		];

		$form['import_layout']['content']['import_type'] = [
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

		$form['import_layout']['content']['excel_file'] = [
			'#type' => 'file',
			'#title' => $this->t('Archivo Excel'),
			'#description' => $this->t('Cargue un archivo .xlsx con la información a importar.'),
			'#required' => TRUE,
			'#attributes' => [
				'class' => [
					'data-core-file-input',
				],
			],
		];

		$form['import_layout']['content']['actions'] = [
			'#type' => 'actions',
		];

		$form['import_layout']['content']['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Importar'),
			'#button_type' => 'primary',
			'#attributes' => [
				'class' => [
					'data-core-btn',
					'data-core-btn--primary',
				],
			],
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
			$inserted_count = 0;
			$updated_count = 0;
			$skipped_count = 0;
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

				$unique_column = $this->getUniqueIdentifierColumn($table);
				$unique_value = $unique_column ? ($values[$unique_column] ?? NULL) : NULL;

				$reference_label = $this->buildReferenceLabel($table, $values);
				$reference_document = $this->buildReferenceDocument($table, $values);
				$target_record_id = NULL;
				$operation = 'error';
				$row_status = 'failed';
				$row_message = '';

				if (!$unique_column || $this->normalizeImportValue($unique_value) === '') {
					$failed++;
					$operation = 'error';
					$row_status = 'failed';
					$row_message = 'No se pudo procesar la fila porque no tiene identificador único.';
				} else {
					$existing_record_id = $this->findExistingImportRecordId($table, $unique_column, $unique_value);

					$target_record_id = NULL;
					$operation = 'error';
					$row_status = 'failed';
					$row_message = '';
					$row_changes = [];

					if ($existing_record_id) {
						$target_record_id = $existing_record_id;
						$row_changes = $this->getImportRowChanges($table, $existing_record_id, $values, $expected);

						if (!empty($row_changes)) {
							$update_values = $values;

							unset($update_values['id']);
							unset($update_values['created']);
							unset($update_values['created_at']);

							$database->update($table)
								->fields($update_values)
								->condition('id', $existing_record_id)
								->execute();

							$inserted++;
							$updated_count++;
							$operation = 'update';
							$row_status = 'success';
							$row_message = 'Registro existente actualizado correctamente.';
						} else {
							$skipped_count++;
							$operation = 'skip';
							$row_status = 'skipped';
							$row_message = 'Registro existente sin cambios. No fue actualizado.';
						}
					} else {
						$target_record_id = $database->insert($table)
							->fields($values)
							->execute();

						$inserted++;
						$inserted_count++;
						$operation = 'insert';
						$row_status = 'success';
						$row_message = 'Registro nuevo importado correctamente.';
					}
				}

				$import_log_item_id = $database->insert('asocolderma_data_import_log_item')
					->fields([
						'import_uuid' => $import_uuid,
						'row_number' => $row_number,
						'reference_label' => $reference_label,
						'reference_document' => $reference_document,
						'target_record_id' => $target_record_id ? (int) $target_record_id : NULL,
						'operation' => $operation,
						'status' => $row_status,
						'message' => $row_message,
						'created' => \Drupal::time()->getRequestTime(),
					])
					->execute();

				if ($operation === 'update' && !empty($row_changes)) {
					foreach ($row_changes as $change) {
						$database->insert('asocolderma_data_import_log_item_change')
							->fields([
								'import_uuid' => $import_uuid,
								'import_log_item_id' => (int) $import_log_item_id,
								'target_record_id' => $target_record_id ? (int) $target_record_id : NULL,
								'field_name' => $change['field_name'],
								'old_value' => $change['old_value'],
								'new_value' => $change['new_value'],
								'created' => \Drupal::time()->getRequestTime(),
							])
							->execute();
					}
				}
			}

			$finished = \Drupal::time()->getRequestTime();
			$duration_seconds = $finished - $started;
			$status = $failed > 0 ? 'completed_with_errors' : 'completed';
			$message = sprintf(
				'Importación finalizada. Insertados: %d. Actualizados: %d. Sin cambios: %d. Fallidos: %d.',
				$inserted_count,
				$updated_count,
				$skipped_count,
				$failed
			);

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
				'Importación completada en la tabla @table. Insertados: @inserted. Actualizados: @updated. Sin cambios: @skipped. Fallidos: @failed.',
				[
					'@table' => $table,
					'@inserted' => $inserted_count,
					'@updated' => $updated_count,
					'@skipped' => $skipped_count,
					'@failed' => $failed,
				]
			));
		} catch (\Throwable $e) {
			$this->messenger()->addError($this->t('Error procesando el archivo: @message', [
				'@message' => $e->getMessage(),
			]));
		}
	}
}
