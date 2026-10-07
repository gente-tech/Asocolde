<?php

namespace Drupal\asocolderma_data_core\Controller;

/**
 * Exports empleados data and stores export logs.
 */
class EmpleadosExportController extends DataExportBaseController
{

	/**
	 * {@inheritdoc}
	 */
	protected function getExportConfig(): array
	{
		return [
			'module_key' => 'empleados',
			'module_label' => 'empleados',
			'table_name' => 'asocolderma_import_empleados',
			'alias' => 'e',
			'filename_prefix' => 'empleados_export',
			'status_field' => 'estado_empleado',
			'numeric_fields' => [
				'salario_mensual',
			],
			'combine_fields' => [
				'id_asocolderma',
				'numero_documento',
				'primer_nombre',
				'segundo_nombre',
				'primer_apellido',
				'segundo_apellido',
				'correo_personal',
				'correo_corporativo',
				'telefono_celular_personal',
				'cargo',
				'area_dependencia',
				'tipo_contrato',
				'pais',
				'ciudad_residencia',
				'departamento',
			],
			'columns' => [
				'id_asocolderma' => 'ID AsoColDerma',
				'estado_empleado' => 'Estado empleado',
				'cargo' => 'Cargo',
				'area_dependencia' => 'Área dependencia',
				'tipo_contrato' => 'Tipo contrato',
				'fecha_ingreso' => 'Fecha ingreso',
				'fecha_retiro' => 'Fecha retiro',
				'primer_nombre' => 'Primer nombre',
				'segundo_nombre' => 'Segundo nombre',
				'primer_apellido' => 'Primer apellido',
				'segundo_apellido' => 'Segundo apellido',
				'fecha_nacimiento' => 'Fecha nacimiento',
				'estado_civil' => 'Estado civil',
				'sexo' => 'Sexo',
				'tipo_documento' => 'Tipo documento',
				'numero_documento' => 'Número documento',
				'pais' => 'País',
				'ciudad_residencia' => 'Ciudad residencia',
				'departamento' => 'Departamento',
				'direccion_fisica_personal' => 'Dirección física personal',
				'correo_personal' => 'Correo personal',
				'telefono_celular_personal' => 'Teléfono celular personal',
				'correo_corporativo' => 'Correo corporativo',
				'observaciones_administrativas' => 'Observaciones administrativas',
				'contacto_emergencia_nombre' => 'Contacto emergencia nombre',
				'contacto_emergencia_telefono' => 'Contacto emergencia teléfono',
				'contacto_emergencia_relacion' => 'Contacto emergencia relación',
				'salario_mensual' => 'Salario mensual',
				'tipo_pago' => 'Tipo pago',
				'eps' => 'EPS',
				'fondo_pensiones' => 'Fondo pensiones',
				'arl' => 'ARL',
				'caja_compensacion' => 'Caja compensación',
				'datos_bancarios_banco' => 'Banco',
				'datos_bancarios_tipo_cuenta' => 'Tipo cuenta',
				'datos_bancarios_numero_cuenta' => 'Número cuenta',
				'copia_contrato_vigente' => 'Copia contrato vigente',
				'copia_rut' => 'Copia RUT',
				'copia_cedula' => 'Copia cédula',
				'copia_certificacion_bancaria' => 'Copia certificación bancaria',
				'copia_hoja_vida' => 'Copia hoja de vida',
				'otros_documentos_adjuntos' => 'Otros documentos adjuntos',
				'validation_status' => 'Estado validación',
				'created' => 'Fecha creación',
			],
			'labels' => [
				'combine' => 'Búsqueda general',
				'is_active' => 'Estado',
				'estado_empleado' => 'Estado',
				'validation_status' => 'Estado validación',
				'id_asocolderma' => 'ID AsoColDerma',
				'numero_documento' => 'Número documento',
				'primer_nombre' => 'Primer nombre',
				'segundo_nombre' => 'Segundo nombre',
				'primer_apellido' => 'Primer apellido',
				'segundo_apellido' => 'Segundo apellido',
				'correo_personal' => 'Correo personal',
				'correo_corporativo' => 'Correo corporativo',
				'telefono_celular_personal' => 'Teléfono celular personal',
				'cargo' => 'Cargo',
				'area_dependencia' => 'Área dependencia',
				'tipo_contrato' => 'Tipo contrato',
				'pais' => 'País',
				'ciudad_residencia' => 'Ciudad residencia',
				'departamento' => 'Departamento',
			],
		];
	}
}
