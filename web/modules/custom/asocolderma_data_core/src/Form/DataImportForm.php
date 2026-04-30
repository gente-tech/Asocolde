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
				'iva',
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
			],

			'asocolderma_import_asociados' => [
				'id_asocolderma',
				'estado_afiliacion',
				'tipo_asociado',
				'fecha_ingreso',
				'edad',
				'miembro_presenta_1',
				'miembro_presenta_2',
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
				'recibir_correspondencia',
				'correo_principal',
				'telefono_celular',
				'registro_medico',
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
				'filial',
				'grupos_estudio',
				'recertificado',
				'fecha_inicio_recertificacion',
				'fecha_fin_recertificacion',
				'mostrar_perfil_publico',
				'correo_publico',
				'sitio_web',
				'horario_atencion',
				'telefono_publico_1',
				'telefono_publico_2',
				'perfil_profesional',
				'direccion_consultorio',
				'imagenes_consultorio',
				'palabras_clave',
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
				'valor_cuota',
				'fecha_ultimo_pago',
				'comprobante_pago',
				'carta_presentacion_1',
				'carta_presentacion_2',
				'copia_rut',
				'copia_cedula',
				'carta_solicitud_ingreso',
				'hoja_vida',
				'copia_diploma_medico',
				'copia_diploma_dermatologo',
				'registro_rethus',
				'carta_autorizacion_verificacion',
				'certificacion_presentacion',
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
				'registro_medico',
				'pais',
				'ciudad',
				'departamento',
				'direccion_principal',
				'correo_principal',
				'telefono_celular',
				'universidad_residencia',
				'programa_especialidad',
				'anio_residencia',
				'fecha_finalizacion',
				'copia_cedula',
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
		} catch (\Throwable $e) {
			$this->messenger()->addError($this->t('Error procesando el archivo: @message', [
				'@message' => $e->getMessage(),
			]));
		}
	}
}
