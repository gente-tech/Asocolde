<?php

namespace Drupal\asocolderma_data_core\Controller;

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
		$form['#action'] = Url::fromRoute('asocolderma_data_core.database_login')->toString();

		return [
			'#theme' => 'asocolderma_data_core_database_modal_login',
			'#form' => $form,
			'#attached' => [
				'library' => [
					'asocolderma_data_core/database_login',
				],
			],
			'#cache' => [
				'max-age' => 0,
			],
		];
	}
}
