<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for downloading Data Core import templates.
 */
class ImportTemplateController extends ControllerBase
{

	/**
	 * Downloads an Excel template for the selected import table.
	 */
	public function download(string $table): StreamedResponse
	{
		$templates = $this->getTemplates();

		if (!isset($templates[$table])) {
			throw $this->createNotFoundException();
		}

		$label = $templates[$table]['label'];
		$columns = $templates[$table]['columns'];

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle(substr($label, 0, 31));

		foreach ($columns as $index => $column_name) {
			$column_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
			$sheet->setCellValue($column_letter . '1', $column_name);
			$sheet->getColumnDimension($column_letter)->setAutoSize(TRUE);
		}

		$sheet->freezePane('A2');

		$filename = 'plantilla_' . $table . '.xlsx';

		$response = new StreamedResponse(function () use ($spreadsheet): void {
			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
		});

		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Cache-Control', 'max-age=0');

		return $response;
	}

	/**
	 * Returns available import templates.
	 */
	protected function getTemplates(): array
	{
		return [
			'patrocinadores' => [
				'label' => 'Patrocinadores',
				'columns' => [
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
			],
			'proveedores' => [
				'label' => 'Proveedores',
				'columns' => [
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
			],
			'asociados' => [
				'label' => 'Asociados',
				'columns' => [
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
			],
			'residentes' => [
				'label' => 'Residentes',
				'columns' => [
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
			],
			'empleados' => [
				'label' => 'Empleados',
				'columns' => [
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
			],
		];
	}
}
