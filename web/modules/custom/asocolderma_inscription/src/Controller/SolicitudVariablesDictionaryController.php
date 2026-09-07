<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\asocolderma_inscription\Service\SolicitudZohoVariableManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Muestra el diccionario institucional de variables dinámicas.
 */
final class SolicitudVariablesDictionaryController extends ControllerBase
{

	public function __construct(
		private readonly SolicitudZohoVariableManager $zohoVariableManager,
	) {}

	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get(
				'asocolderma_inscription.solicitud_zoho_variable_manager',
			),
		);
	}

	/**
	 * Construye la página del diccionario de variables.
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
				'#markup' => '
          <p>
            Este diccionario muestra las variables dinámicas disponibles
            para correo, WhatsApp y documentos de Zoho Sign.
          </p>
        ',
			],
		];

		$build['notification_help'] = [
			'#type' => 'item',
			'#markup' => '
        <p>
          <strong>Correo y WhatsApp:</strong>
          utilice las claves indicadas en las plantillas de notificación.
        </p>
      ',
		];

		$build['general_data'] = [
			'#type' => 'details',
			'#title' => $this->t('Datos generales del usuario'),
			'#open' => FALSE,
		];

		$build['general_data']['table'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Clave de variable'),
				$this->t('Descripción'),
			],
			'#rows' => $this->buildNotificationRows(
				$this->getGeneralVariables(),
			),
		];

		$build['request_data'] = [
			'#type' => 'details',
			'#title' => $this->t('Datos generales de la solicitud'),
			'#open' => FALSE,
		];

		$build['request_data']['table'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Clave de variable'),
				$this->t('Descripción'),
			],
			'#rows' => $this->buildNotificationRows(
				$this->getRequestVariables(),
			),
		];

		$build['zoho_help'] = [
			'#type' => 'container',
			'#attributes' => [
				'class' => ['messages', 'messages--warning'],
			],
			'content' => [
				'#markup' => '
          <p>
            <strong>Zoho Sign:</strong>
            el nombre del campo de textose en la plantilla de Zoho Sign debe
            coincidir exactamente con la clave indicada en la primera columna.
          </p>
          <p>
            Los campos de archivo envían el nombre original del archivo.
            Las referencias a taxonomía envían la etiqueta visible.
            Los valores booleanos se envían como “Sí” o “No”.
          </p>
          <p>
            La fecha de firma no se incluye en esta lista porque debe ser un
            campo automático de Zoho Sign.
          </p>
        ',
			],
		];

		$build['zoho_variables'] = [
			'#type' => 'details',
			'#title' => $this->t('Variables para documentos de Zoho Sign'),
			'#open' => TRUE,
		];

		$build['zoho_variables']['table'] = [
			'#type' => 'table',
			'#header' => [
				$this->t('Clave para Zoho Sign'),
				$this->t('Descripción'),
				$this->t('Campo de Drupal'),
				$this->t('Tipo'),
			],
			'#rows' => $this->buildZohoRows(
				$this->zohoVariableManager->getDefinitions(),
			),
			'#empty' => $this->t(
				'No hay variables de Zoho Sign disponibles.',
			),
		];

		return $build;
	}

	/**
	 * Construye las filas de variables de notificaciones.
	 */
	private function buildNotificationRows(array $variables): array
	{
		$rows = [];

		foreach ($variables as $key => $label) {
			$rows[] = [
				'key' => [
					'data' => [
						'#type' => 'html_tag',
						'#tag' => 'code',
						'#value' => $key,
					],
				],
				'label' => $label,
			];
		}

		return $rows;
	}

	/**
	 * Construye las filas del diccionario de Zoho Sign.
	 */
	private function buildZohoRows(array $definitions): array
	{
		$rows = [];

		foreach ($definitions as $key => $definition) {
			$rows[] = [
				'key' => [
					'data' => [
						'#type' => 'html_tag',
						'#tag' => 'code',
						'#value' => $key,
					],
				],
				'label' => (string) ($definition['label'] ?? ''),
				'field' => [
					'data' => [
						'#type' => 'html_tag',
						'#tag' => 'code',
						'#value' => (string) ($definition['field'] ?? ''),
					],
				],
				'type' => (string) ($definition['type'] ?? ''),
			];
		}

		return $rows;
	}

	/**
	 * Variables generales disponibles para notificaciones.
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
	 * Variables de solicitud disponibles para notificaciones.
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
			'request_status_change_comment' => 'Observación del cambio de estado',
		];
	}
}
