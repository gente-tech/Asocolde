<?php

namespace Drupal\asocolderma_data_core\Form;

use Drupal\asocolderma_data_core\Service\RecordCrudLogger;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de creación y edición de empleados.
 */
class EmpleadoRecordForm extends FormBase
{

	protected const TABLE_NAME = 'asocolderma_import_empleados';

	protected Connection $database;

	protected AccountProxyInterface $currentUser;

	protected RouteMatchInterface $currentRouteMatch;

	/**
	 * CRUD audit logger.
	 */
	protected RecordCrudLogger $recordCrudLogger;

	public function __construct(
		Connection $database,
		AccountProxyInterface $current_user,
		RouteMatchInterface $route_match,
		RecordCrudLogger $record_crud_logger,
	) {
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->currentRouteMatch = $route_match;
		$this->recordCrudLogger = $record_crud_logger;
	}

	public static function create(ContainerInterface $container): self
	{
		return new static(
			$container->get('database'),
			$container->get('current_user'),
			$container->get('current_route_match'),
			$container->get('asocolderma_data_core.record_crud_logger'),
		);
	}

	public function getFormId(): string
	{
		return 'asocolderma_data_core_empleado_record_form';
	}

	protected function getRecordId(): ?int
	{
		$record_id = $this->currentRouteMatch->getParameter('record_id');

		if ($record_id === NULL || $record_id === '') {
			return NULL;
		}

		return (int) $record_id;
	}

	protected function isEditMode(): bool
	{
		return $this->getRecordId() !== NULL;
	}

	protected function loadRecord(?int $record_id): array
	{
		if (!$record_id) {
			return [];
		}

		$record = $this->database
			->select(static::TABLE_NAME, 'e')
			->fields('e')
			->condition('id', $record_id)
			->execute()
			->fetchAssoc();

		return $record ?: [];
	}

	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$record_id = $this->getRecordId();
		$record = $this->loadRecord($record_id);

		if ($this->isEditMode() && empty($record)) {
			$form['not_found'] = [
				'#markup' => '<div class="messages messages--error">El empleado solicitado no existe.</div>',
			];

			$form['actions'] = [
				'#type' => 'actions',
			];

			$form['actions']['back'] = [
				'#type' => 'link',
				'#title' => $this->t('Volver al listado'),
				'#url' => Url::fromUserInput('/gestion-data/empleados'),
				'#attributes' => [
					'class' => ['button'],
				],
			];

			return $form;
		}

		$form['#tree'] = TRUE;

		$form['record_id'] = [
			'#type' => 'hidden',
			'#value' => $record_id,
		];

		$form['main'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => ['data-core-record-form', 'data-core-record-form--empleados'],
			],
		];

		$this->buildSection($form, $record, 'institucional', 'Información institucional', TRUE, [
			'id_asocolderma' => ['ID en AsoColDerma', 'textfield', 50],
			'estado_empleado' => ['Estado del empleado', 'textfield', 100],
			'cargo' => ['Cargo', 'textfield', 150, TRUE],
			'area_dependencia' => ['Área o dependencia', 'textfield', 150],
			'tipo_contrato' => ['Tipo de contrato', 'textfield', 150],
			'fecha_ingreso' => ['Fecha de ingreso', 'textfield', 50],
			'fecha_retiro' => ['Fecha de retiro', 'textfield', 50],
		]);

		$this->buildSection($form, $record, 'datos_personales', 'Datos personales', TRUE, [
			'primer_nombre' => ['Primer nombre', 'textfield', 100, TRUE],
			'segundo_nombre' => ['Segundo nombre', 'textfield', 100],
			'primer_apellido' => ['Primer apellido', 'textfield', 100, TRUE],
			'segundo_apellido' => ['Segundo apellido', 'textfield', 100],
			'fecha_nacimiento' => ['Fecha de nacimiento', 'textfield', 50],
			'estado_civil' => ['Estado civil', 'textfield', 50],
			'sexo' => ['Sexo', 'textfield', 50],
			'tipo_documento' => ['Tipo de documento', 'textfield', 50, TRUE],
		]);

		$this->buildReferenceField($form, $record);

		$this->buildSection($form, $record, 'ubicacion_contacto', 'Ubicación y contacto', TRUE, [
			'pais' => ['País', 'textfield', 100],
			'ciudad_residencia' => ['Ciudad de residencia', 'textfield', 150],
			'departamento' => ['Departamento', 'textfield', 150],
			'direccion_fisica_personal' => ['Dirección física personal', 'textfield', 255],
			'correo_personal' => ['Correo personal', 'email', 150],
			'telefono_celular_personal' => ['Teléfono celular personal', 'textfield', 50],
			'correo_corporativo' => ['Correo corporativo', 'email', 150],
		]);

		$this->buildSection($form, $record, 'administrativa', 'Información administrativa', FALSE, [
			'observaciones_administrativas' => ['Observaciones administrativas', 'textarea'],
		]);

		$this->buildSection($form, $record, 'emergencia', 'Contacto de emergencia', FALSE, [
			'contacto_emergencia_nombre' => ['Nombre contacto de emergencia', 'textfield', 150],
			'contacto_emergencia_telefono' => ['Teléfono contacto de emergencia', 'textfield', 50],
			'contacto_emergencia_relacion' => ['Relación contacto de emergencia', 'textfield', 100],
		]);

		$this->buildSection($form, $record, 'financiera', 'Información financiera y seguridad social', FALSE, [
			'salario_mensual' => ['Salario mensual', 'number'],
			'tipo_pago' => ['Tipo de pago', 'textfield', 100],
			'eps' => ['EPS', 'textfield', 150],
			'fondo_pensiones' => ['Fondo de pensiones', 'textfield', 150],
			'arl' => ['ARL', 'textfield', 150],
			'caja_compensacion' => ['Caja de compensación', 'textfield', 150],
			'datos_bancarios_banco' => ['Banco', 'textfield', 150],
			'datos_bancarios_tipo_cuenta' => ['Tipo de cuenta', 'textfield', 100],
			'datos_bancarios_numero_cuenta' => ['Número de cuenta', 'textfield', 100],
		]);

		$this->buildSection($form, $record, 'documentos', 'Documentos / adjuntos', FALSE, [
			'copia_contrato_vigente' => ['Copia contrato vigente', 'textfield', 255],
			'copia_rut' => ['Copia RUT', 'textfield', 255],
			'copia_cedula' => ['Copia cédula', 'textfield', 255],
			'copia_certificacion_bancaria' => ['Copia certificación bancaria', 'textfield', 255],
			'copia_hoja_vida' => ['Copia hoja de vida', 'textfield', 255],
			'otros_documentos_adjuntos' => ['Otros documentos adjuntos', 'textarea'],
		]);

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->isEditMode() ? $this->t('Guardar cambios') : $this->t('Crear empleado'),
			'#button_type' => 'primary',
		];

		$form['actions']['cancel'] = [
			'#type' => 'link',
			'#title' => $this->t('Cancelar'),
			'#url' => Url::fromUserInput('/gestion-data/empleados'),
			'#attributes' => [
				'class' => ['button'],
			],
		];

		return $form;
	}

	protected function buildSection(array &$form, array $record, string $section_key, string $title, bool $open, array $fields): void
	{
		$form['main'][$section_key] = [
			'#type' => 'details',
			'#title' => $this->t($title),
			'#open' => $open,
		];

		foreach ($fields as $field_name => $definition) {
			$label = $definition[0];
			$type = $definition[1] ?? 'textfield';
			$maxlength = $definition[2] ?? NULL;
			$required = $definition[3] ?? FALSE;

			$element = [
				'#type' => $type,
				'#title' => $this->t($label),
				'#default_value' => $record[$field_name] ?? '',
				'#required' => $required,
			];

			if ($type === 'textfield' || $type === 'email') {
				$element['#maxlength'] = $maxlength ?: 255;
			}

			if ($type === 'number') {
				$element['#step'] = '0.01';
			}

			$form['main'][$section_key][$field_name] = $element;
		}
	}

	protected function buildReferenceField(array &$form, array $record): void
	{
		if ($this->isEditMode()) {
			$form['main']['datos_personales']['numero_documento_display'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Número de documento'),
				'#default_value' => $record['numero_documento'] ?? '',
				'#disabled' => TRUE,
				'#description' => $this->t('Este campo es la referencia única del empleado y no puede modificarse.'),
			];

			$form['main']['datos_personales']['numero_documento'] = [
				'#type' => 'hidden',
				'#value' => $record['numero_documento'] ?? '',
			];
		} else {
			$form['main']['datos_personales']['numero_documento'] = [
				'#type' => 'textfield',
				'#title' => $this->t('Número de documento'),
				'#default_value' => $record['numero_documento'] ?? '',
				'#maxlength' => 50,
				'#required' => TRUE,
			];
		}
	}

	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$values = $this->extractRecordValues($form_state);

		if (trim((string) ($values['primer_nombre'] ?? '')) === '') {
			$form_state->setErrorByName('main][datos_personales][primer_nombre', $this->t('El primer nombre es obligatorio.'));
		}

		if (trim((string) ($values['primer_apellido'] ?? '')) === '') {
			$form_state->setErrorByName('main][datos_personales][primer_apellido', $this->t('El primer apellido es obligatorio.'));
		}

		if (trim((string) ($values['tipo_documento'] ?? '')) === '') {
			$form_state->setErrorByName('main][datos_personales][tipo_documento', $this->t('El tipo de documento es obligatorio.'));
		}

		if (trim((string) ($values['numero_documento'] ?? '')) === '') {
			$form_state->setErrorByName('main][datos_personales][numero_documento', $this->t('El número de documento es obligatorio.'));
		}

		if (!$this->isEditMode() && !empty($values['numero_documento'])) {
			$existing_id = $this->database
				->select(static::TABLE_NAME, 'e')
				->fields('e', ['id'])
				->condition('numero_documento', $values['numero_documento'])
				->execute()
				->fetchField();

			if ($existing_id) {
				$form_state->setErrorByName('main][datos_personales][numero_documento', $this->t('Ya existe un empleado registrado con este número de documento.'));
			}
		}
	}

	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$record_id = $this->getRecordId();
		$values = $this->extractRecordValues($form_state);
		$now = time();

		if ($record_id) {
			$old_record = $this->loadRecord($record_id);

			$this->database
				->update(static::TABLE_NAME)
				->fields($values)
				->condition('id', $record_id)
				->execute();

			$this->recordCrudLogger->logUpdate(
				static::TABLE_NAME,
				(int) $record_id,
				$old_record,
				$values,
			);

			$this->messenger()->addStatus($this->t('El empleado fue actualizado correctamente.'));
		} else {
			$values['batch_id'] = 0;
			$values['row_number'] = 0;
			$values['is_active'] = 1;
			$values['status_changed'] = NULL;
			$values['status_changed_by'] = NULL;
			$values['status_change_reason'] = NULL;
			$values['raw_payload'] = NULL;
			$values['validation_status'] = 'manual';
			$values['validation_errors'] = NULL;
			$values['created'] = $now;

			$record_id = (int) $this->database
				->insert(static::TABLE_NAME)
				->fields($values)
				->execute();

			$this->recordCrudLogger->logCreate(
				static::TABLE_NAME,
				(int) $record_id,
				$values,
			);

			$this->messenger()->addStatus($this->t('El empleado fue creado correctamente.'));
		}

		$form_state->setRedirectUrl(Url::fromUserInput('/gestion-data/empleados'));
	}

	protected function extractRecordValues(FormStateInterface $form_state): array
	{
		$main = $form_state->getValue('main') ?: [];

		$sections = [
			'institucional',
			'datos_personales',
			'ubicacion_contacto',
			'administrativa',
			'emergencia',
			'financiera',
			'documentos',
		];

		$values = [];

		foreach ($sections as $section) {
			if (!empty($main[$section]) && is_array($main[$section])) {
				foreach ($main[$section] as $field_name => $value) {
					if (str_ends_with($field_name, '_display')) {
						continue;
					}

					$values[$field_name] = $this->normalizeValue($value);
				}
			}
		}

		return $values;
	}

	protected function normalizeValue($value)
	{
		if ($value === NULL) {
			return NULL;
		}

		if (is_array($value)) {
			return NULL;
		}

		$value = trim((string) $value);

		if ($value === '') {
			return NULL;
		}

		return $value;
	}
}
