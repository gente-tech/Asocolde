<?php

namespace Drupal\asocolderma_data_core\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de creación y edición de asociados.
 */
class AsociadoRecordForm extends FormBase
{

	protected const TABLE_NAME = 'asocolderma_import_asociados';

	protected Connection $database;

	protected AccountProxyInterface $currentUser;

	protected RouteMatchInterface $currentRouteMatch;

	public function __construct(
		Connection $database,
		AccountProxyInterface $current_user,
		RouteMatchInterface $route_match,
	) {
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->currentRouteMatch = $route_match;
	}

	public static function create(ContainerInterface $container): self
	{
		return new static(
			$container->get('database'),
			$container->get('current_user'),
			$container->get('current_route_match'),
		);
	}

	public function getFormId(): string
	{
		return 'asocolderma_data_core_asociado_record_form';
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
			->select(static::TABLE_NAME, 'a')
			->fields('a')
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
				'#markup' => '<div class="messages messages--error">El asociado solicitado no existe.</div>',
			];

			$form['actions'] = [
				'#type' => 'actions',
			];

			$form['actions']['back'] = [
				'#type' => 'link',
				'#title' => $this->t('Volver al listado'),
				'#url' => Url::fromUserInput('/gestion-data/asociados'),
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
				'class' => ['data-core-record-form', 'data-core-record-form--asociados'],
			],
		];

		$this->buildSection($form, $record, 'identificacion', 'Identificación y afiliación', TRUE, [
			'id_asocolderma' => ['ID en AsoColDerma', 'textfield', 50],
			'estado_afiliacion' => ['Estado de afiliación', 'textfield', 100],
			'tipo_asociado' => ['Tipo de asociado', 'textfield', 100],
			'fecha_ingreso_asocolderma' => ['Fecha de ingreso a AsoColDerma', 'textfield', 20],
			'edad' => ['Edad', 'textfield', 20],
			'miembro_numero_1' => ['Miembro número 1', 'textfield', 150],
			'miembro_numero_2' => ['Miembro número 2', 'textfield', 150],
			'capitulo_regional' => ['Capítulo / Regional', 'textfield', 150],
		]);

		$this->buildSection($form, $record, 'datos_personales', 'Datos personales', TRUE, [
			'primer_nombre' => ['Primer nombre', 'textfield', 100, TRUE],
			'segundo_nombre' => ['Segundo nombre', 'textfield', 100],
			'primer_apellido' => ['Primer apellido', 'textfield', 100, TRUE],
			'segundo_apellido' => ['Segundo apellido', 'textfield', 100],
			'fecha_nacimiento' => ['Fecha de nacimiento', 'textfield', 20],
			'estado_civil' => ['Estado civil', 'textfield', 50],
			'sexo' => ['Sexo', 'textfield', 20],
			'tipo_documento' => ['Tipo de documento', 'textfield', 50, TRUE],
		]);

		$this->buildReferenceField($form, $record);

		$this->buildSection($form, $record, 'contacto', 'Contacto institucional', TRUE, [
			'pais_nacimiento' => ['País de nacimiento', 'textfield', 100],
			'direccion_principal' => ['Dirección principal', 'textfield', 255],
			'correspondencia_en' => ['Correspondencia en', 'textfield', 50],
			'correo_principal' => ['Correo principal', 'email', 150],
			'telefono_celular' => ['Teléfono celular', 'textfield', 50],
		]);

		$this->buildSection($form, $record, 'ejercicio', 'Ejercicio profesional', TRUE, [
			'registro_medico_tarjeta' => ['Registro médico / tarjeta profesional', 'textfield', 100],
			'pais_ejercicio' => ['País de ejercicio', 'textfield', 100],
			'ciudad_ejercicio' => ['Ciudad de ejercicio', 'textfield', 150],
			'departamento' => ['Departamento', 'textfield', 150],
		]);

		$this->buildSection($form, $record, 'academica', 'Información académica', FALSE, [
			'facultad_pregrado' => ['Facultad de pregrado', 'textfield', 255],
			'pais_pregrado' => ['País de pregrado', 'textfield', 100],
			'titulo_universitario' => ['Título universitario', 'textfield', 255],
			'universidad_residencia' => ['Universidad de residencia', 'textfield', 255],
			'pais_residencia' => ['País de residencia', 'textfield', 100],
			'especialidad' => ['Especialidad', 'textfield', 150],
			'subespecialidad' => ['Subespecialidad', 'textfield', 255],
			'pertenece_filial' => ['Pertenece a filial', 'textfield', 255],
			'grupos_estudio' => ['Grupos de estudio', 'textarea'],
			'recertificado_camec' => ['Recertificado CAMEC', 'textfield', 20],
			'fecha_inicio_recertificacion' => ['Fecha inicio recertificación', 'textfield', 20],
			'fecha_fin_recertificacion' => ['Fecha fin recertificación', 'textfield', 20],
		]);

		$this->buildSection($form, $record, 'publica', 'Información pública', FALSE, [
			'mostrar_perfil_publico' => ['Mostrar perfil público', 'textfield', 10],
			'correo_publico' => ['Correo público', 'email', 150],
			'sitio_web_publico' => ['Sitio web público', 'textfield', 255],
			'horario_atencion' => ['Horario de atención', 'textarea'],
			'telefono_publico_1' => ['Teléfono público 1', 'textfield', 50],
			'telefono_publico_2' => ['Teléfono público 2', 'textfield', 50],
			'perfil_profesional' => ['Perfil profesional', 'textarea'],
			'direccion_consultorio' => ['Dirección consultorio', 'textarea'],
			'imagenes_consultorio' => ['Imágenes consultorio', 'textarea'],
			'palabras_clave_tags' => ['Palabras clave / tags', 'textarea'],
		]);

		$this->buildSection($form, $record, 'servicios', 'Servicios que presta', FALSE, [
			'capilaroscopia' => ['Capilaroscopia', 'textfield', 10],
			'cicatrices_acne' => ['Cicatrices por acné', 'textfield', 10],
			'dermatologia_general' => ['Dermatología general', 'textfield', 10],
			'dermatologia_oncologica' => ['Dermatología oncológica', 'textfield', 10],
			'dermatologia_pediatrica' => ['Dermatología pediátrica', 'textfield', 10],
			'dermatopatologia' => ['Dermatopatología', 'textfield', 10],
			'fototerapia' => ['Fototerapia', 'textfield', 10],
			'laser' => ['Láser', 'textfield', 255],
			'microdermoabrasion' => ['Microdermoabrasión', 'textfield', 10],
			'peeling' => ['Peeling', 'textfield', 10],
			'inyectables' => ['Inyectables', 'textfield', 255],
			'trasplante_pelo' => ['Trasplante de pelo', 'textfield', 10],
			'otros_servicios' => ['Otros servicios', 'textarea'],
		]);

		$this->buildSection($form, $record, 'financiera', 'Información financiera', FALSE, [
			'estado_cuota' => ['Estado cuota', 'textfield', 100],
			'valor_cuota_vigente' => ['Valor cuota vigente', 'number'],
			'fecha_ultimo_pago' => ['Fecha último pago', 'textfield', 20],
			'comprobante_pago' => ['Comprobante de pago', 'textfield', 255],
		]);

		$this->buildSection($form, $record, 'documentos', 'Documentos / adjuntos', FALSE, [
			'carta_presentacion_1' => ['Carta presentación 1', 'textfield', 255],
			'carta_presentacion_2' => ['Carta presentación 2', 'textfield', 255],
			'copia_rut' => ['Copia RUT', 'textfield', 255],
			'copia_cedula_id' => ['Copia cédula o ID', 'textfield', 255],
			'carta_solicitud_ingreso' => ['Carta solicitud de ingreso', 'textfield', 255],
			'hoja_vida' => ['Hoja de vida', 'textfield', 255],
			'copia_diploma_medico' => ['Copia diploma médico', 'textfield', 255],
			'copia_diploma_dermatologo' => ['Copia diploma dermatólogo', 'textfield', 255],
			'registro_rethus' => ['Registro RETHUS', 'textfield', 255],
			'carta_autorizacion_verificacion' => ['Carta autorización verificación', 'textfield', 255],
			'certificacion_congreso_publicacion' => ['Certificación congreso / publicación', 'textfield', 255],
			'otros_documentos_adjuntos' => ['Otros documentos adjuntos', 'textarea'],
		]);

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->isEditMode() ? $this->t('Guardar cambios') : $this->t('Crear asociado'),
			'#button_type' => 'primary',
		];

		$form['actions']['cancel'] = [
			'#type' => 'link',
			'#title' => $this->t('Cancelar'),
			'#url' => Url::fromUserInput('/gestion-data/asociados'),
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
				'#description' => $this->t('Este campo es la referencia única del asociado y no puede modificarse.'),
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
				->select(static::TABLE_NAME, 'a')
				->fields('a', ['id'])
				->condition('numero_documento', $values['numero_documento'])
				->execute()
				->fetchField();

			if ($existing_id) {
				$form_state->setErrorByName('main][datos_personales][numero_documento', $this->t('Ya existe un asociado registrado con este número de documento.'));
			}
		}
	}

	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$record_id = $this->getRecordId();
		$values = $this->extractRecordValues($form_state);
		$now = time();

		if ($record_id) {
			$this->database
				->update(static::TABLE_NAME)
				->fields($values)
				->condition('id', $record_id)
				->execute();

			$this->messenger()->addStatus($this->t('El asociado fue actualizado correctamente.'));
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

			$this->messenger()->addStatus($this->t('El asociado fue creado correctamente.'));
		}

		$form_state->setRedirectUrl(Url::fromUserInput('/gestion-data/asociados'));
	}

	protected function extractRecordValues(FormStateInterface $form_state): array
	{
		$main = $form_state->getValue('main') ?: [];

		$sections = [
			'identificacion',
			'datos_personales',
			'contacto',
			'ejercicio',
			'academica',
			'publica',
			'servicios',
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
