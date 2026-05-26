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
        <p><strong>Uso general:</strong> las variables se usan con la misma clave tanto para correo como para WhatsApp.</p>
        <p><strong>Ejemplo:</strong> <code>user_full_name</code>, <code>request_code</code>, <code>request_current_status</code>.</p>
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
				$this->t('Clave de variable'),
				$this->t('Descripción'),
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
				$this->t('Clave de variable'),
				$this->t('Descripción'),
			],
			'#rows' => $this->buildRows($this->getRequestVariables()),
			'#empty' => $this->t('No hay variables de solicitud configuradas.'),
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
			$rows[] = [
				'key' => [
					'data' => [
						'#markup' => '<code>' . $key . '</code>',
					],
				],
				'label' => $label,
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
			'user_document_number' => 'Número de documento del usuario',
			'user_activation_url' => 'URL de activación de cuenta del usuario',
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
			'request_code' => 'Código único público de la solicitud',
			'request_url' => 'URL de la solicitud',
			'request_created_date' => 'Fecha de creación de la solicitud',
			'request_current_status' => 'Estado actual de la solicitud',
			'request_previous_status' => 'Estado anterior de la solicitud',
			'request_new_status' => 'Nuevo estado de la solicitud',
			'request_status_changed_date' => 'Fecha del cambio de estado',
			'request_status_changed_by' => 'Usuario que realizó el cambio de estado',
			'request_status_change_comment' => 'Observación o comentario del cambio de estado',
			'request_rejection_reason' => 'Motivo de rechazo de la solicitud',
			'request_clarification_comment' => 'Comentario de aclaración solicitado',
		];
	}
}
