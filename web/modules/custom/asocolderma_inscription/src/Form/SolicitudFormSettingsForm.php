<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for solicitud ingreso creation form texts.
 */
final class SolicitudFormSettingsForm extends ConfigFormBase
{

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames(): array
	{
		return [
			'asocolderma_inscription.solicitud_form_settings',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_inscription_solicitud_form_settings_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		$config = $this->config('asocolderma_inscription.solicitud_form_settings');

		$form['intro'] = [
			'#type' => 'item',
			'#markup' => '<p>Desde este formulario puede administrar los textos visibles del formulario de creación de solicitud de ingreso: título del campo, placeholder y texto de ayuda.</p>',
		];

		$form['fields'] = [
			'#type' => 'vertical_tabs',
			'#title' => $this->t('Campos del formulario'),
		];

		foreach ($this->getFieldDefinitions() as $group_key => $group) {
			$form[$group_key] = [
				'#type' => 'details',
				'#title' => $group['label'],
				'#group' => 'fields',
				'#tree' => TRUE,
			];

			foreach ($group['fields'] as $field_key => $field) {
				$form[$group_key][$field_key] = [
					'#type' => 'details',
					'#title' => $field['label'],
					'#open' => FALSE,
				];

				$form[$group_key][$field_key]['label'] = [
					'#type' => 'textfield',
					'#title' => $this->t('Título / label del campo'),
					'#default_value' => $config->get("fields.$group_key.$field_key.label") ?? $field['label'],
					'#maxlength' => 255,
				];

				$form[$group_key][$field_key]['placeholder'] = [
					'#type' => 'textfield',
					'#title' => $this->t('Placeholder'),
					'#default_value' => $config->get("fields.$group_key.$field_key.placeholder") ?? ($field['placeholder'] ?? ''),
					'#maxlength' => 255,
				];

				$form[$group_key][$field_key]['description'] = [
					'#type' => 'textarea',
					'#title' => $this->t('Descripción / helper'),
					'#default_value' => $config->get("fields.$group_key.$field_key.description") ?? ($field['description'] ?? ''),
					'#rows' => 3,
				];
			}
		}

		$form['submit_confirmation'] = [
			'#type' => 'details',
			'#title' => $this->t('Modal de confirmación de envío'),
			'#group' => 'fields',
			'#tree' => TRUE,
		];

		$form['submit_confirmation']['title'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Título del modal'),
			'#default_value' => $config->get('submit_confirmation.title') ?? 'Confirmar envío de solicitud',
			'#required' => TRUE,
			'#maxlength' => 255,
		];

		$form['submit_confirmation']['message'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Mensaje de confirmación'),
			'#default_value' => $config->get('submit_confirmation.message') ?? 'Antes de enviar la solicitud, verifique que toda la información suministrada sea correcta y que los soportes correspondan a lo requerido y en sus versiones actualizadas. Una vez enviados no podrán ser modificados',
			'#required' => TRUE,
			'#rows' => 5,
		];

		$form['submit_confirmation']['cancel_button'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Texto del botón cancelar'),
			'#default_value' => $config->get('submit_confirmation.cancel_button') ?? 'Cancelar',
			'#required' => TRUE,
			'#maxlength' => 100,
		];

		$form['submit_confirmation']['confirm_button'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Texto del botón confirmar'),
			'#default_value' => $config->get('submit_confirmation.confirm_button') ?? 'Confirmar envío',
			'#required' => TRUE,
			'#maxlength' => 100,
		];

		$form['confirm']['terms']['url'] = [
			'#type' => 'url',
			'#title' => $this->t('Enlace de términos y condiciones'),
			'#default_value' => $config->get('terms_resource.url') ?? '',
			'#description' => $this->t('Si se configura un enlace, tendrá prioridad sobre el documento PDF adjunto.'),
			'#required' => FALSE,
		];

		$form['confirm']['terms']['document'] = [
			'#type' => 'managed_file',
			'#title' => $this->t('Documento de términos y condiciones'),
			'#default_value' => $config->get('terms_resource.document') ?? [],
			'#upload_location' => 'public://solicitud_ingreso/terminos_condiciones/',
			'#upload_validators' => [
				'file_validate_extensions' => ['pdf'],
				'file_validate_size' => [10 * 1024 * 1024],
			],
			'#description' => $this->t('Adjunte un documento en formato PDF. Tamaño máximo: 10 MB. Se utilizará únicamente cuando no exista un enlace configurado.'),
			'#required' => FALSE,
		];

		$form['confirm']['terms']['button_text'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Texto del botón Ver'),
			'#default_value' => $config->get('terms_resource.button_text') ?? 'Ver',
			'#maxlength' => 100,
			'#required' => TRUE,
		];

		// El resumen no requiere placeholder.
		unset($form['confirm']['preview']['placeholder']);

		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		parent::validateForm($form, $form_state);

		$url = trim((string) $form_state->getValue([
			'confirm',
			'terms',
			'url',
		]));

		if ($url === '') {
			return;
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

		if (
			!in_array($scheme, ['http', 'https'], TRUE)
			|| !filter_var($url, FILTER_VALIDATE_URL)
		) {
			$form_state->setError(
				$form['confirm']['terms']['url'],
				$this->t('Ingrese un enlace válido que comience con http:// o https://.')
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$config = $this->configFactory->getEditable('asocolderma_inscription.solicitud_form_settings');

		$fields = [];

		foreach ($this->getFieldDefinitions() as $group_key => $group) {
			$group_values = $form_state->getValue($group_key) ?? [];

			foreach ($group['fields'] as $field_key => $field) {
				$field_values = $group_values[$field_key] ?? [];

				$fields[$group_key][$field_key] = [
					'label' => trim((string) ($field_values['label'] ?? '')),
					'placeholder' => trim((string) ($field_values['placeholder'] ?? '')),
					'description' => trim((string) ($field_values['description'] ?? '')),
				];
			}
		}

		$submit_confirmation = $form_state->getValue('submit_confirmation');
		$terms = $form_state->getValue(['confirm', 'terms']) ?? [];

		$old_document = (array) ($config->get('terms_resource.document') ?? []);
		$new_document = array_values(array_filter(
			array_map('intval', (array) ($terms['document'] ?? []))
		));

		$file_storage = \Drupal::entityTypeManager()->getStorage('file');
		$file_usage = \Drupal::service('file.usage');

		// Registrar los nuevos documentos.
		foreach (array_diff($new_document, $old_document) as $fid) {
			$file = $file_storage->load($fid);

			if ($file) {
				$file->setPermanent();
				$file->save();

				$file_usage->add(
					$file,
					'asocolderma_inscription',
					'terms_resource',
					1
				);
			}
		}

		$config
			->set('fields', $fields)
			->set('submit_confirmation', [
				'title' => trim((string) $submit_confirmation['title']),
				'message' => trim((string) $submit_confirmation['message']),
				'cancel_button' => trim((string) $submit_confirmation['cancel_button']),
				'confirm_button' => trim((string) $submit_confirmation['confirm_button']),
			])
			->set('terms_resource', [
				'url' => trim((string) ($terms['url'] ?? '')),
				'document' => $new_document,
				'button_text' => trim((string) ($terms['button_text'] ?? 'Ver')),
			])
			->save();

		// Retirar la referencia de los documentos reemplazados o eliminados.
		foreach (array_diff($old_document, $new_document) as $fid) {
			$file = $file_storage->load($fid);

			if ($file) {
				$file_usage->delete(
					$file,
					'asocolderma_inscription',
					'terms_resource',
					1
				);
			}
		}

		parent::submitForm($form, $form_state);
	}

	/**
	 * Returns editable fields from SolicitudIngresoWizardForm.
	 */
	private function getFieldDefinitions(): array
	{
		return [
			'general' => [
				'label' => $this->t('Paso 1: Información general'),
				'fields' => [
					'tipo_asociado' => [
						'label' => 'Tipo de asociado al que aspira',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione la categoría de miembro a la cual desea postularse, de acuerdo con los criterios establecidos por la Asociación.',
					],
					'nombre1' => [
						'label' => 'Primer nombre',
						'placeholder' => 'Ejemplo: Carlos',
						'description' => 'Digite su primer nombre exactamente como figura en su documento de identidad. Ejemplo: Carlos.',
					],
					'nombre2' => [
						'label' => 'Segundo nombre',
						'placeholder' => 'Ejemplo: Andrés',
						'description' => 'Digite su segundo nombre, si aplica, tal como aparece en su documento de identidad. Ejemplo: Andrés.',
					],
					'apellido1' => [
						'label' => 'Primer apellido',
						'placeholder' => 'Ejemplo: Gómez',
						'description' => 'Digite su primer apellido exactamente como figura en su documento de identidad. Ejemplo: Gómez.',
					],
					'apellido2' => [
						'label' => 'Segundo apellido',
						'placeholder' => 'Ejemplo: Rodríguez',
						'description' => 'Digite su segundo apellido, si aplica, tal como aparece en su documento de identidad. Ejemplo: Rodríguez.',
					],
					'fecha_nacimiento' => [
						'label' => 'Fecha de nacimiento',
						'placeholder' => '',
						'description' => 'Seleccione su fecha de nacimiento conforme a su documento oficial.',
					],
					'estado_civil' => [
						'label' => 'Estado civil',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione su estado civil actual. Esta información será utilizada únicamente para fines administrativos del proceso.',
					],
					'sexo' => [
						'label' => 'Sexo',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione la opción correspondiente según su información personal.',
					],
					'tipo_documento' => [
						'label' => 'Tipo de documento',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione el tipo de documento con el cual se identifica formalmente.',
					],
					'numero_documento' => [
						'label' => 'Número de documento',
						'placeholder' => 'Ejemplo: 1234567890',
						'description' => 'Ingrese el número del documento sin puntos, comas ni espacios. Ejemplo: 1234567890.',
					],
					'registro_medico' => [
						'label' => 'Registro médico',
						'placeholder' => 'Ejemplo: RM-12345',
						'description' => 'Ingrese el número de su registro médico profesional vigente. Ejemplo: RM-12345 o el consecutivo oficial que corresponda.',
					],
					'pais' => [
						'label' => 'País',
						'placeholder' => '- Seleccione -',
						'description' => 'Indique el país de residencia actual. Ejemplo: Colombia.',
					],
					'departamento' => [
						'label' => 'Departamento',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione el departamento de residencia actual.',
					],
					'ciudad_ejercicio' => [
						'label' => 'Ciudad de ejercicio',
						'placeholder' => '- Seleccione -',
						'description' => 'Digite la ciudad donde desarrolla actualmente su ejercicio profesional. Ejemplo: Bogotá.',
					],
				],
			],

			'contacto' => [
				'label' => $this->t('Paso 2: Información uso institucional'),
				'fields' => [
					'direccion' => [
						'label' => 'Dirección física principal',
						'placeholder' => 'Ejemplo: Calle 123 # 45-67, Apartamento 201',
						'description' => 'Ingrese su dirección física principal. Ejemplo: Calle 123 # 45-67, Apartamento 201.',
					],
					'correspondencia_fisica' => [
						'label' => 'Dirección institucional',
						'placeholder' => 'Ejemplo: Clínica Dermatológica Central, Consultorio 302',
						'description' => 'Ingrese la dirección institucional o profesional asociada a su ejercicio médico.',
					],
					'email' => [
						'label' => 'Correo electrónico principal',
						'placeholder' => 'Ejemplo: nombre@dominio.com',
						'description' => 'Ingrese el correo electrónico principal donde recibirá comunicaciones oficiales del proceso. Ejemplo: nombre@dominio.com.',
					],
					'celular' => [
						'label' => 'Teléfono celular de contacto',
						'placeholder' => 'Ejemplo: 3001234567',
						'description' => 'Seleccione el indicativo del país e ingrese únicamente el número celular nacional. Ejemplo para Colombia: 3001234567.',
					],
					'lugar_correspondencia' => [
						'label' => 'En caso de ser ratificado ¿Dónde desea recibir la correspondencia física?',
						'placeholder' => '- Seleccione -',
						'description' => '',
					],
				],
			],

			'profesional' => [
				'label' => $this->t('Paso 3: Información académica'),
				'fields' => [
					'facultad_pregrado' => [
						'label' => 'Facultad de medicina – Pregrado',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione la institución donde obtuvo su título de médico.',
					],
					'pais_pregrado' => [
						'label' => 'País donde realizó el pregrado en medicina',
						'placeholder' => '- Seleccione -',
						'description' => '',
					],
					'titulo_universitario' => [
						'label' => 'Título universitario',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione el título profesional obtenido.',
					],
					'universidad_residencia' => [
						'label' => 'Universidad de residencia',
						'placeholder' => '- Seleccione -',
						'description' => '',
					],
					'pais_residencia' => [
						'label' => 'País donde realizó la residencia',
						'placeholder' => '- Seleccione -',
						'description' => '',
					],
					'tiene_subespecialidad' => [
						'label' => 'Tiene una Subespecialidad?',
						'placeholder' => '',
						'description' => '',
					],
					'subespecialidad_cual' => [
						'label' => 'Subespecialidad',
						'placeholder' => '- Seleccione -',
						'description' => 'Seleccione su subespecialidad, en caso de contar con ella.',
					],
				],
			],

			'adjuntos' => [
				'label' => $this->t('Paso 4: Información societaria / adjuntos'),
				'fields' => [
					'adj_carta_1' => [
						'label' => 'Carta 1',
						'placeholder' => '',
						'description' => 'Adjunte la primera carta de presentación o recomendación en formato PDF, conforme a los requisitos del proceso de ingreso. Tamaño máximo: 10 MB.',
					],
					'adj_carta_2' => [
						'label' => 'Carta 2',
						'placeholder' => '',
						'description' => 'Adjunte la segunda carta de presentación o recomendación en formato PDF, conforme a los requisitos del proceso de ingreso. Tamaño máximo: 10 MB.',
					],
					'adj_rut' => [
						'label' => 'RUT',
						'placeholder' => '',
						'description' => 'Adjunte una copia actualizada del Registro Único Tributario (RUT) en formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_id' => [
						'label' => 'Documento de identidad',
						'placeholder' => '',
						'description' => 'Adjunte una copia legible de su documento de identidad vigente. Formatos permitidos: PDF, JPG, JPEG o PNG. Tamaño máximo: 10 MB.',
					],
					'adj_carta_ingreso' => [
						'label' => 'Carta de solicitud de ingreso',
						'placeholder' => '',
						'description' => 'Adjunte la carta formal de solicitud de ingreso a la Asociación en formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_hv' => [
						'label' => 'Hoja de vida',
						'placeholder' => '',
						'description' => 'Adjunte su hoja de vida actualizada en formato PDF. Se recomienda que el documento incluya formación académica, experiencia profesional y datos de contacto. Tamaño máximo: 10 MB.',
					],
					'adj_diploma_medico' => [
						'label' => 'Diploma médico',
						'placeholder' => '',
						'description' => 'Adjunte el diploma que acredita su título profesional como médico en formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_diploma_dermatologo' => [
						'label' => 'Diploma dermatólogo',
						'placeholder' => '',
						'description' => 'Adjunte el diploma o soporte académico que acredita su especialidad en dermatología en formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_rethus' => [
						'label' => 'RETHUS',
						'placeholder' => '',
						'description' => 'Adjunte el certificado o soporte de inscripción en RETHUS en formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_aut_verificacion' => [
						'label' => 'Autorización de verificación',
						'placeholder' => '',
						'description' => 'Adjunte el documento firmado mediante el cual autoriza la validación de la información suministrada. Formato PDF. Tamaño máximo: 10 MB.',
					],
					'adj_cert_publicacion' => [
						'label' => 'Certificación de publicaciones (si aplica)',
						'placeholder' => '',
						'description' => 'Si cuenta con publicaciones, adjunte los certificados o soportes correspondientes en formato PDF. Este campo es opcional. Tamaño máximo: 10 MB.',
					],
					'adj_articulo_cientifico' => [
						'label' => 'Artículo científico o soporte de trabajo académico',
						'placeholder' => '',
						'description' => 'Adjunte el artículo científico o soporte de trabajo académico en formato PDF. Tamaño máximo: 10 MB.',
					],
				],
			],

			'confirm' => [
				'label' => $this->t('Paso 5: Confirmación'),
				'fields' => [
					'terms' => [
						'label' => 'Acepto los términos y condiciones',
						'placeholder' => '',
						'description' => 'Declaro que la información suministrada es veraz y autorizo su validación dentro del proceso institucional de evaluación y admisión.',
					],
					'preview' => [
						'label' => 'Resumen',
						'placeholder' => '',
						'description' => 'Verifique cuidadosamente la información registrada antes de enviar su solicitud. Una vez remitida, será gestionada conforme al flujo institucional definido por la Asociación.',
					],
				],
			],
		];
	}
}
