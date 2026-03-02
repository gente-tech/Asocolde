<?php

namespace Drupal\asocolderma_inscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\profile\Entity\Profile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AspiranteActivateController extends ControllerBase
{

	public function activate($token)
	{

		$store = \Drupal::keyValueExpirable('asocolderma_inscription_activation');
		$uid = $store->get($token);

		if (!$uid) {
			throw new AccessDeniedHttpException('Token inválido o expirado.');
		}

		$user = User::load($uid);
		if (!$user || $user->isActive()) {
			throw new AccessDeniedHttpException();
		}

		$user->activate();
		$user->addRole('aspirante');
		$user->save();

		$store->delete($token);


		// Crear perfil aspirante si no existe
		$profiles = \Drupal::entityTypeManager()
			->getStorage('profile')
			->loadByProperties([
				'uid' => $uid,
				'type' => 'aspirante',
			]);

		if (empty($profiles)) {
			$profile = Profile::create([
				'type' => 'aspirante',
				'uid' => $uid,
			]);

			$profile->set('field_email', $user->getEmail());

			$profile->save();
		}

		$this->messenger()->addStatus('Cuenta activada correctamente.');

		return new RedirectResponse('/user/login');
	}
}
