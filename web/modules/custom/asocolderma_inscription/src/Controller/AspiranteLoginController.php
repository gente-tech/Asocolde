<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controlador para el login exclusivo de aspirantes.
 */
final class AspiranteLoginController extends ControllerBase
{

	/**
	 * Renderiza el formulario de login de aspirantes.
	 */
	public function login(Request $request): array
	{
		$form = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserLoginForm');
		$form['#attributes']['class'][] = 'user-login-form--aspirante';

		$build = [
			'#theme' => 'asocolderma_inscription_aspirante_modal_login',
			'#form' => $form,
			'#attached' => [
				'library' => [
					'asocolderma_inscription/aspirante_login_modal',
					'core/drupal.dialog.ajax',
				],
			],
		];

		return $build;
	}
}
