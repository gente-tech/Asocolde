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
 * Formulario de creación y edición de patrocinadores.
 */
class PatrocinadorRecordForm extends FormBase
{

	/**
	 * Tabla real usada por la vista de patrocinadores.
	 */
	protected const TABLE_NAME = 'asocolderma_import_patrocinadores';

	/**
	 * Conexión a base de datos.
	 */
	protected Connection $database;

	/**
	 * Usuario actual.
	 */
	protected AccountProxyInterface $currentUser;

	/**
	 * Current route match.
	 */
	protected RouteMatchInterface $currentRouteMatch;

	/**
	 * Constructor.
	 */
	public function __construct(
		Connection $database,
		AccountProxyInterface $current_user,
		RouteMatchInterface $route_match,
	) {
		$this->database = $database;
		$this->currentUser = $current_user;
		$this->currentRouteMatch = $route_match;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): self
	{
		return new static(
			$container->get('database'),
			$container->get('current_user'),
			$container->get('current_route_match'),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_data_core_patrocinador_record_form';
	}

	/**
	 * Retorna el ID del registro cuando está en edición.
	 */
	protected function getRecordId(): ?int
	{
		$record_id = $this->currentRouteMatch->getParameter('record_id');

		if ($record_id === NULL || $record_id === '') {
			return NULL;
		}

		return (int) $record_id;
	}

	/**
	 * Indica si el formulario está en modo edición.
	 */
	protected function isEditMode(): bool
	{
		return $this->getRecordId() !== NULL;
	}

	/**
	 * Carga el registro actual en modo edición.
	 */
	protected function loadRecord(?int $record_id): array
	{
		if (!$record_id) {
			return [];
		}

		$record = $this->database
			->select(static::TABLE_NAME, 'p')
			->fields('p')
			->condition('id', $record_id)
			->execute()
			->fetchAssoc();

		return $record ?: [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$record_id = $this->getRecordId();
		$record = $this->loadRecord($record_id);

		if ($this->isEditMode() && empty($record)) {
			$form['not_found'] = [
				'#markup' => '<div class="messages messages--error">El patrocinador solicitado no existe.</div>',
			];

			$form['actions'] = [
				'#type' => 'actions',
			];

			$form['actions']['back'] = [
				'#type' => 'link',
				'#title' => $this->t('Volver al listado'),
				'#url' => Url::fromUserInput('/gestion-data/patrocinadores'),
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
				'class' => ['data-core-record-form', 'data-core-record-form--patrocinadores'],
			],
		];

		$form['main']['identificacion'] = [
			'#type' => 'details',
			'#title' => $this->t('Identificación del patrocinador'),
			'#open' => TRUE,
		];

		$form['main']['identificacion']['id_asocolderma'] = [
			'#type' => 'textfield',
			'#title' => $this->t('ID en AsoColDerma'),
			'#default_value' => $record['id_asocolderma'] ?? '',
			'#maxlength' => 50,
		];

		$form['main']['identificacion']['estado_patrocinador'] = [
			'#type' => 'select',
			'#title' => $this->t('Estado del patrocinador'),
			'#options' => [
				'' => $this->t('- Seleccione -'),
				'activo' => $this->t('Activo'),
				'inactivo' => $this->t('Inactivo'),
				'historico' => $this->t('Histórico'),
			],
			'#default_value' => $record['estado_patrocinador'] ?? 'activo',
		];

		$form['main']['identificacion']['tipo_patrocinador'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Tipo de patrocinador'),
			'#default_value' => $record['tipo_patrocinador'] ?? '',
			'#maxlength' => 100,
		];

		$form['main']['identificacion']['anios_vinculacion'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Año(s) de vinculación'),
			'#default_value' => $record['anios_vinculacion'] ?? '',
			'#maxlength' => 50,
		];

		$form['main']['identificacion']['razon_social'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Razón social'),
			'#default_value' => $record['razon_social'] ?? '',
			'#maxlength' => 255,
			'#required' => TRUE,
		];

		$form['main']['identificacion']['nombre_comercial'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Nombre comercial'),
			'#default_value' => $record['nombre_comercial'] ?? '',
			'#maxlength' => 255,
		];

		if ($this->isEditMode()) {
			$form['main']['identificacion']['nit_display'] = [
				'#type' => 'textfield',
				'#title' => $this->t('NIT'),
				'#default_value' => $record['nit'] ?? '',
				'#disabled' => TRUE,
				'#description' => $this->t('Este campo es la referencia única del patrocinador y no puede modificarse.'),
			];

			$form['main']['identificacion']['nit'] = [
				'#type' => 'hidden',
				'#value' => $record['nit'] ?? '',
			];
		} else {
			$form['main']['identificacion']['nit'] = [
				'#type' => 'textfield',
				'#title' => $this->t('NIT'),
				'#default_value' => $record['nit'] ?? '',
				'#maxlength' => 50,
				'#required' => TRUE,
			];
		}

		$form['main']['ubicacion'] = [
			'#type' => 'details',
			'#title' => $this->t('Ubicación'),
			'#open' => TRUE,
		];

		$form['main']['ubicacion']['pais'] = [
			'#type' => 'textfield',
			'#title' => $this->t('País'),
			'#default_value' => $record['pais'] ?? '',
			'#maxlength' => 100,
		];

		$form['main']['ubicacion']['ciudad_sede_principal'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Ciudad sede principal'),
			'#default_value' => $record['ciudad_sede_principal'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['ubicacion']['direccion_fiscal'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Dirección fiscal'),
			'#default_value' => $record['direccion_fiscal'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['ubicacion']['sitio_web_corporativo'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Sitio web corporativo'),
			'#default_value' => $record['sitio_web_corporativo'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['contacto'] = [
			'#type' => 'details',
			'#title' => $this->t('Contacto institucional'),
			'#open' => TRUE,
		];

		$form['main']['contacto']['nombre_contacto_principal'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Nombre contacto principal'),
			'#default_value' => $record['nombre_contacto_principal'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['contacto']['cargo_contacto'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Cargo del contacto'),
			'#default_value' => $record['cargo_contacto'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['contacto']['correo_corporativo'] = [
			'#type' => 'email',
			'#title' => $this->t('Correo corporativo'),
			'#default_value' => $record['correo_corporativo'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['contacto']['telefono_corporativo'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Teléfono corporativo'),
			'#default_value' => $record['telefono_corporativo'] ?? '',
			'#maxlength' => 50,
		];

		$form['main']['contacto']['celular_contacto'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Celular de contacto'),
			'#default_value' => $record['celular_contacto'] ?? '',
			'#maxlength' => 50,
		];

		$form['main']['contacto']['nombre_contacto_2'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Nombre contacto 2'),
			'#default_value' => $record['nombre_contacto_2'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['contacto']['cargo_contacto_2'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Cargo contacto 2'),
			'#default_value' => $record['cargo_contacto_2'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['contacto']['correo_contacto_2'] = [
			'#type' => 'email',
			'#title' => $this->t('Correo contacto 2'),
			'#default_value' => $record['correo_contacto_2'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['relacion'] = [
			'#type' => 'details',
			'#title' => $this->t('Relación institucional'),
			'#open' => TRUE,
		];

		$form['main']['relacion']['eventos_vinculados'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Eventos vinculados'),
			'#default_value' => $record['eventos_vinculados'] ?? '',
		];

		$form['main']['relacion']['beneficios_pactados'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Beneficios pactados'),
			'#default_value' => $record['beneficios_pactados'] ?? '',
		];

		$form['main']['relacion']['contacto_comercial_asocolderma'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Contacto comercial AsoColDerma'),
			'#default_value' => $record['contacto_comercial_asocolderma'] ?? '',
			'#maxlength' => 150,
		];

		$form['main']['relacion']['observaciones_generales'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Observaciones generales'),
			'#default_value' => $record['observaciones_generales'] ?? '',
		];

		$form['main']['financiera'] = [
			'#type' => 'details',
			'#title' => $this->t('Información financiera'),
			'#open' => FALSE,
		];

		$form['main']['financiera']['estado_pago'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Estado de pago'),
			'#default_value' => $record['estado_pago'] ?? '',
			'#maxlength' => 100,
		];

		$form['main']['financiera']['valor_comprometido_evento_1'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor comprometido evento 1'),
			'#default_value' => $record['valor_comprometido_evento_1'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['valor_pagado_evento_1'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor pagado evento 1'),
			'#default_value' => $record['valor_pagado_evento_1'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['valor_comprometido_evento_2'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor comprometido evento 2'),
			'#default_value' => $record['valor_comprometido_evento_2'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['valor_pagado_evento_2'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor pagado evento 2'),
			'#default_value' => $record['valor_pagado_evento_2'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['valor_comprometido_evento_3'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor comprometido evento 3'),
			'#default_value' => $record['valor_comprometido_evento_3'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['valor_pagado_evento_3'] = [
			'#type' => 'number',
			'#title' => $this->t('Valor pagado evento 3'),
			'#default_value' => $record['valor_pagado_evento_3'] ?? '',
			'#step' => '0.01',
		];

		$form['main']['financiera']['metodo_pago'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Método de pago'),
			'#default_value' => $record['metodo_pago'] ?? '',
			'#maxlength' => 100,
		];

		$form['main']['financiera']['iva_aplicable'] = [
			'#type' => 'textfield',
			'#title' => $this->t('IVA aplicable'),
			'#default_value' => $record['iva_aplicable'] ?? '',
			'#maxlength' => 20,
		];

		$form['main']['financiera']['datos_bancarios'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Datos bancarios'),
			'#default_value' => $record['datos_bancarios'] ?? '',
		];

		$form['main']['documentos'] = [
			'#type' => 'details',
			'#title' => $this->t('Documentos / adjuntos'),
			'#open' => FALSE,
		];

		$form['main']['documentos']['carta_compromiso_evento_1'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Carta compromiso evento 1'),
			'#default_value' => $record['carta_compromiso_evento_1'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['carta_compromiso_evento_2'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Carta compromiso evento 2'),
			'#default_value' => $record['carta_compromiso_evento_2'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['carta_compromiso_evento_3'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Carta compromiso evento 3'),
			'#default_value' => $record['carta_compromiso_evento_3'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['contrato_evento_1'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Contrato evento 1'),
			'#default_value' => $record['contrato_evento_1'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['contrato_evento_2'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Contrato evento 2'),
			'#default_value' => $record['contrato_evento_2'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['contrato_evento_3'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Contrato evento 3'),
			'#default_value' => $record['contrato_evento_3'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['copia_rut'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Copia RUT'),
			'#default_value' => $record['copia_rut'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['copia_camara_comercio'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Copia Cámara de Comercio'),
			'#default_value' => $record['copia_camara_comercio'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['copia_cedula_representante'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Copia cédula representante legal'),
			'#default_value' => $record['copia_cedula_representante'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['copia_certificacion_bancaria'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Copia certificación bancaria'),
			'#default_value' => $record['copia_certificacion_bancaria'] ?? '',
			'#maxlength' => 255,
		];

		$form['main']['documentos']['otros_documentos_adjuntos'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Otros documentos adjuntos'),
			'#default_value' => $record['otros_documentos_adjuntos'] ?? '',
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->isEditMode() ? $this->t('Guardar cambios') : $this->t('Crear patrocinador'),
			'#button_type' => 'primary',
		];

		$form['actions']['cancel'] = [
			'#type' => 'link',
			'#title' => $this->t('Cancelar'),
			'#url' => Url::fromUserInput('/gestion-data/patrocinadores'),
			'#attributes' => [
				'class' => ['button'],
			],
		];

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$values = $this->extractRecordValues($form_state);

		if (trim((string) ($values['razon_social'] ?? '')) === '') {
			$form_state->setErrorByName('main][identificacion][razon_social', $this->t('La razón social es obligatoria.'));
		}

		if (trim((string) ($values['nit'] ?? '')) === '') {
			$form_state->setErrorByName('main][identificacion][nit', $this->t('El NIT es obligatorio.'));
		}

		if (!$this->isEditMode() && !empty($values['nit'])) {
			$existing_id = $this->database
				->select(static::TABLE_NAME, 'p')
				->fields('p', ['id'])
				->condition('nit', $values['nit'])
				->execute()
				->fetchField();

			if ($existing_id) {
				$form_state->setErrorByName('main][identificacion][nit', $this->t('Ya existe un patrocinador registrado con este NIT.'));
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
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

			$this->messenger()->addStatus($this->t('El patrocinador fue actualizado correctamente.'));
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

			$this->messenger()->addStatus($this->t('El patrocinador fue creado correctamente.'));
		}

		$form_state->setRedirectUrl(Url::fromUserInput('/gestion-data/patrocinadores'));
	}

	/**
	 * Extrae y normaliza los valores del formulario.
	 */
	protected function extractRecordValues(FormStateInterface $form_state): array
	{
		$main = $form_state->getValue('main') ?: [];

		$sections = [
			'identificacion',
			'ubicacion',
			'contacto',
			'relacion',
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

	/**
	 * Normaliza valores antes de guardar.
	 */
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
