<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Component\Utility\Crypt;

class AspiranteRegisterForm extends FormBase
{

	public function getFormId()
	{
		return 'asocolderma_inscription_register_form';
	}

	public function buildForm(array $form, FormStateInterface $form_state)
	{

		$form['mail'] = [
			'#type' => 'email',
			'#title' => $this->t('Correo electrónico'),
			'#required' => TRUE,
		];

		$form['pass'] = [
			'#type' => 'password_confirm',
			'#title' => $this->t('Contraseña'),
			'#required' => TRUE,
		];

		$form['tyc'] = [
			'#type' => 'checkbox',
			'#title' => $this->t('Acepto los términos y condiciones'),
			'#required' => TRUE,
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Registrarme'),
		];

		return $form;
	}

	public function validateForm(array &$form, FormStateInterface $form_state)
	{
		$mail = $form_state->getValue('mail');

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			$form_state->setErrorByName('mail', $this->t('Correo inválido.'));
			return;
		}

		$users = \Drupal::entityTypeManager()
			->getStorage('user')
			->loadByProperties(['mail' => $mail]);

		if (!empty($users)) {
			$form_state->setErrorByName(
				'mail',
				$this->t('Ya existe una cuenta registrada con este correo.')
			);
		}
	}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{

		$mail = $form_state->getValue('mail');

		$user = User::create([
			'name' => $mail,
			'mail' => $mail,
			'status' => 0,
		]);
		$user->setPassword($form_state->getValue('pass'));
		$user->save();

		$token = Crypt::randomBytesBase64(32);
		\Drupal::keyValueExpirable('asocolderma_inscription_activation')
			->set($token, $user->id(), 86400);

		$activation_url = Url::fromRoute(
			'asocolderma_inscription.activate',
			['token' => $token],
			['absolute' => TRUE]
		)->toString();

		// Enviar correo
		$result = \Drupal::service('plugin.manager.mail')->mail(
			'user',
			'register_pending_approval',
			$mail,
			\Drupal::languageManager()->getDefaultLanguage()->getId(),
			[
				'account' => $user,
				'activation_link' => $activation_url,
			]
		);

		if ($result['result'] === TRUE) {
			\Drupal::logger('asocolderma_inscription')->info(
				'El correo de activación se envió al correo: @mail, con el link: @activation_link',
				[
					'@mail' => $mail,
					'@activation_link' => $activation_url,
				]
			);
		} else {
			\Drupal::logger('asocolderma_inscription')->error(
				'ERROR al intentar enviar el correo al correo: @mail',
				['@mail' => $mail]
			);
		}

		$this->messenger()->addStatus(
			$this->t('Te enviamos un correo para activar tu cuenta.')
		);
		$form_state->setRedirect('user.login');
	}
}
