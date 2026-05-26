<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Displays the dynamic variables dictionary for solicitud notifications.
 */
final class SolicitudVariablesDictionaryController extends ControllerBase
{

	/**
	 * Builds the variables dictionary page.
	 *
	 * @return array
	 *   Render array.
	 */
	public function build(): array
	{
		$build = [];

		$build['intro'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => ['messages', 'messages--status'],
			],
			'content' => [
				'#markup' => '<p>Este diccionario muestra las variables dinámicas disponibles para las plantillas de correo y WhatsApp del proceso de registro, postulación y activación de nuevos dermatólogos en AsoColDerma.</p>',
			],
		];

		$build['help'] = [
			'#type' => 'item',
			'#markup' => '
        <p><strong>Uso en Mandrill / Mailchimp Transactional:</strong> las variables se usan en mayúsculas con formato <code>*|VARIABLE|*</code>.</p>
        <p><strong>Uso en Twilio WhatsApp:</strong> las variables se asignan por posición a los parámetros de la plantilla, por ejemplo <code>{{1}}</code>, <code>{{2}}</code>, <code>{{3}}</code>.</p>
      ',
		];

		$build['general_data'] = [
			'#type' => 'details',
			'#title' => $this->t('Datos generales del usuario'),
			'#open' => TRUE,
		];

		$build['general_data']['table'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Clave interna'),
				$this->t('Valor'),
				$this->t('Variable Mandrill'),
			],
			'#rows' => $this->buildRows($this->getGeneralVariables()),
			'#empty' => $this->t('No hay variables generales configuradas.'),
		];

		$build['request_data'] = [
			'#type' => 'details',
			'#title' => $this->t('Datos de la solicitud'),
			'#open' => TRUE,
		];

		$build['request_data']['table'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Clave interna'),
				$this->t('Valor'),
				$this->t('Variable Mandrill'),
			],
			'#rows' => $this->buildRows($this->getRequestVariables()),
			'#empty' => $this->t('No hay variables de solicitud configuradas.'),
		];

		$build['twilio_example'] = [
			'#type' => 'details',
			'#title' => $this->t('Ejemplo de mapeo para Twilio WhatsApp'),
			'#open' => FALSE,
		];

		$build['twilio_example']['content'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Parámetro Twilio'),
				$this->t('Variable sugerida'),
				$this->t('Valor'),
			],
			'#rows' => [
				[
					['data' => ['#markup' => '<code>{{1}}</code>']],
					['data' => ['#markup' => '<code>user_full_name</code>']],
					$this->t('Nombre completo del usuario'),
				],
				[
					['data' => ['#markup' => '<code>{{2}}</code>']],
					['data' => ['#markup' => '<code>request_code</code>']],
					$this->t('Código único público de la solicitud'),
				],
				[
					['data' => ['#markup' => '<code>{{3}}</code>']],
					['data' => ['#markup' => '<code>request_current_status</code>']],
					$this->t('Estado actual de la solicitud'),
				],
				[
					['data' => ['#markup' => '<code>{{4}}</code>']],
					['data' => ['#markup' => '<code>request_url</code>']],
					$this->t('URL de la solicitud'),
				],
			],
		];

		return $build;
	}

	/**
	 * Builds table rows from variable definitions.
	 *
	 * @param array $variables
	 *   Variable definitions.
	 *
	 * @return array
	 *   Table rows.
	 */
	private function buildRows(array $variables): array
	{
		$rows = [];

		foreach ($variables as $key => $label) {
			$mandrill_key = strtoupper($key);

			$rows[] = [
				'key' => [
					'data' => [
						'#markup' => '<code>' . $key . '</code>',
					],
				],
				'label' => $label,
				'mandrill' => [
					'data' => [
						'#markup' => '<code>*|' . $mandrill_key . '|*</code>',
					],
				],
			];
		}

		return $rows;
	}

	/**
	 * Returns general user variables.
	 *
	 * @return array
	 *   Variables keyed by internal variable name.
	 */
	private function getGeneralVariables(): array
	{
		return [
			'user_full_name' => 'Nombre completo del usuario',
			'user_first_name' => 'Primer nombre del usuario',
			'user_last_name' => 'Primer apellido del usuario',
			'user_email' => 'Correo electrónico principal del usuario',
			'user_mobile' => 'Celular principal del usuario',
			'user_mobile_country_code' => 'Indicativo telefónico del celular',
			'user_document_number' => 'Número de documento del usuario',
			'user_role' => 'Rol actual del usuario',
			'user_activation_url' => 'URL de activación de cuenta',
			'user_login_url' => 'URL de inicio de sesión',
			'user_dashboard_url' => 'URL del panel del usuario',
		];
	}

	/**
	 * Returns solicitud variables.
	 *
	 * @return array
	 *   Variables keyed by internal variable name.
	 */
	private function getRequestVariables(): array
	{
		return [
			'request_id' => 'ID interno de la solicitud',
			'request_uuid' => 'UUID interno de la solicitud',
			'request_code' => 'Código único público de la solicitud',
			'request_title' => 'Título de la solicitud',
			'request_url' => 'URL de la solicitud',
			'request_admin_url' => 'URL administrativa de la solicitud',
			'request_applicant_url' => 'URL de la solicitud para el aspirante',
			'request_created_date' => 'Fecha de creación de la solicitud',
			'request_updated_date' => 'Fecha de última actualización de la solicitud',
			'request_current_status' => 'Estado actual de la solicitud',
			'request_previous_status' => 'Estado anterior de la solicitud',
			'request_new_status' => 'Nuevo estado de la solicitud',
			'request_status_changed_date' => 'Fecha del cambio de estado',
			'request_status_changed_by' => 'Usuario que realizó el cambio de estado',
			'request_status_change_comment' => 'Observación o comentario del cambio de estado',
			'request_rejection_reason' => 'Motivo de rechazo de la solicitud',
			'request_clarification_reason' => 'Motivo de solicitud de aclaración',
			'request_clarification_comment' => 'Comentario de aclaración solicitado',
			'request_correction_date' => 'Fecha en que el aspirante realizó ajustes',
		];
	}
}
