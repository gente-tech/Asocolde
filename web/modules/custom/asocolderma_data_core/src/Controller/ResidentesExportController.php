<?php

namespace Drupal\asocolderma_data_core\Controller;

/**
 * Exports residentes data and stores export logs.
 */
class ResidentesExportController extends DataExportBaseController
{

	/**
	 * {@inheritdoc}
	 */
	protected function getExportConfig(): array
	{
		return [
			'module_key' => 'residentes',
			'module_label' => 'residentes',
			'table_name' => 'asocolderma_import_residentes',
			'alias' => 'r',
			'filename_prefix' => 'residentes_export',
			'status_field' => 'estado_residente',
			'numeric_fields' => [],
			'combine_fields' => [
				'id_asocolderma',
				'numero_documento',
				'primer_nombre',
				'segundo_nombre',
				'primer_apellido',
				'segundo_apellido',
				'correo_principal',
				'telefono_celular',
				'registro_medico_tarjeta',
				'pais',
				'ciudad',
				'departamento',
				'universidad_residencia',
				'programa_especialidad',
				'anio_residencia',
			],
			'columns' => [
				'id_asocolderma' => 'ID AsoColDerma',
				'estado_residente' => 'Estado residente',
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
				'ciudad' => 'Ciudad',
				'departamento' => 'Departamento',
				'direccion_fisica_institucional' => 'Dirección física institucional',
				'correo_principal' => 'Correo principal',
				'telefono_celular' => 'Teléfono celular',
				'registro_medico_tarjeta' => 'Registro médico / tarjeta',
				'universidad_residencia' => 'Universidad residencia',
				'programa_especialidad' => 'Programa / especialidad',
				'anio_residencia' => 'Año residencia',
				'fecha_estimada_finalizacion' => 'Fecha estimada finalización',
				'aspira_asociarse' => 'Aspira asociarse',
				'eventos_participados' => 'Eventos participados',
				'ponencia_presentacion' => 'Ponencia / presentación',
				'observaciones' => 'Observaciones',
				'copia_cedula_id' => 'Copia cédula ID',
				'carta_aval_universidad' => 'Carta aval universidad',
				'otros_documentos_adjuntos' => 'Otros documentos adjuntos',
				'validation_status' => 'Estado validación',
				'created' => 'Fecha creación',
			],
			'labels' => [
				'combine' => 'Búsqueda general',
				'is_active' => 'Estado',
				'estado_residente' => 'Estado',
				'validation_status' => 'Estado validación',
				'id_asocolderma' => 'ID AsoColDerma',
				'numero_documento' => 'Número documento',
				'primer_nombre' => 'Primer nombre',
				'segundo_nombre' => 'Segundo nombre',
				'primer_apellido' => 'Primer apellido',
				'segundo_apellido' => 'Segundo apellido',
				'correo_principal' => 'Correo principal',
				'telefono_celular' => 'Teléfono celular',
				'pais' => 'País',
				'ciudad' => 'Ciudad',
				'departamento' => 'Departamento',
				'universidad_residencia' => 'Universidad residencia',
				'programa_especialidad' => 'Programa / especialidad',
				'anio_residencia' => 'Año residencia',
			],
		];
	}
}
