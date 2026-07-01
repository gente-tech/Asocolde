<?php

namespace Drupal\asocolderma_data_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controlador para el login independiente de Base de Datos.
 */
final class DataCoreLoginController extends ControllerBase
{
	/**
	 * Renderiza el login independiente de Base de Datos.
	 */
	public function login()
	{
		if ($this->currentUser()->isAuthenticated()) {
			$account = User::load($this->currentUser()->id());

			if ($account && asocolderma_data_core_user_has_database_role($account)) {
				return new RedirectResponse(Url::fromUri('internal:' . asocolderma_data_core_database_default_path())->toString());
			}

			return new RedirectResponse(Url::fromRoute('<front>')->toString());
		}

		$form = $this->formBuilder()->getForm('Drupal\user\Form\UserLoginForm');
		$form['#attributes']['class'][] = 'user-login-form--data-core';

		$module_path = \Drupal::service('extension.list.module')->getPath('asocolderma_data_core');
		$logo_url = base_path() . $module_path . '/images/logo-horizontal-pequeno.jpg';

		return [
			'#type' => 'container',
			'#attributes' => [
				'class' => [
					'data-core-login-page',
				],
			],
			'#attached' => [
				'library' => [
					'asocolderma_data_core/database_login',
				],
			],
			'top' => [
				'#type' => 'container',
				'#attributes' => [
					'class' => [
						'modal-login-top',
						'modal-login-top--data-core',
					],
				],
				'brand' => [
					'#markup' => '
						<div class="data-core-login-brand">
							<img class="data-core-login-brand__logo" src="' . Html::escape($logo_url) . '" alt="AsoColDerma">
							<div class="data-core-login-brand__text">
								<div class="data-core-login-brand__title">Base de Datos Institucional</div>
								<div class="data-core-login-brand__subtitle">Ingreso autorizado AsoColDerma</div>
							</div>
						</div>
					',
					'#allowed_tags' => [
						'div',
						'img',
					],
				],
				'back' => [
					'#type' => 'link',
					'#title' => $this->t('Volver al inicio'),
					'#url' => Url::fromRoute('<front>'),
					'#attributes' => [
						'class' => [
							'btn',
							'btn-link',
							'icn-back',
						],
					],
				],
			],
			'content' => [
				'#type' => 'container',
				'#attributes' => [
					'class' => [
						'modal-login-content',
						'modal-login-content--data-core',
					],
				],
				'left' => [
					'#type' => 'container',
					'#attributes' => [
						'class' => [
							'modal-login-left',
						],
					],
					'form' => $form,
				],
				'right' => [
					'#type' => 'container',
					'#attributes' => [
						'class' => [
							'modal-login-right',
							'modal-login-right--data-core',
						],
					],
				],
			],
		];
	}
}
