<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Form;

use Drupal\asocolderma_inscription\Service\AccountActivationNotificationManager;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de registro inicial de aspirantes.
 */
final class AspiranteRegisterForm extends FormBase
{

	public function __construct(
		private readonly AccountActivationNotificationManager $activationNotificationManager,
	) {}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): static
	{
		return new static(
			$container->get(
				'asocolderma_inscription.account_activation_notification_manager'
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'asocolderma_inscription_register_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(
		array $form,
		FormStateInterface $form_state,
	): array {
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

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(
		array &$form,
		FormStateInterface $form_state,
	): void {
		$mail = trim((string) $form_state->getValue('mail'));

		if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			$form_state->setErrorByName(
				'mail',
				$this->t('Correo inválido.'),
			);

			return;
		}

		$users = \Drupal::entityTypeManager()
			->getStorage('user')
			->loadByProperties([
				'mail' => $mail,
			]);

		if (!empty($users)) {
			$form_state->setErrorByName(
				'mail',
				$this->t(
					'Ya existe una cuenta registrada con este correo.'
				),
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(
		array &$form,
		FormStateInterface $form_state,
	): void {
		$mail = trim((string) $form_state->getValue('mail'));

		$user = User::create([
			'name' => $mail,
			'mail' => $mail,
			'status' => 0,
		]);

		$user->setPassword($form_state->getValue('pass'));
		$user->save();

		$token = Crypt::randomBytesBase64(32);

		\Drupal::keyValueExpirable(
			'asocolderma_inscription_activation'
		)->set(
			$token,
			$user->id(),
			86400,
		);

		$activationUrl = Url::fromRoute(
			'asocolderma_inscription.activate',
			[
				'token' => $token,
			],
			[
				'absolute' => TRUE,
			],
		)->toString();

		$notificationResult =
			$this->activationNotificationManager->send(
				$mail,
				$activationUrl,
			);

		if (!empty($notificationResult['sent'])) {
			$this->messenger()->addStatus(
				$this->t(
					'Te enviamos un correo para activar tu cuenta.'
				),
			);
		} elseif (!empty($notificationResult['success'])) {
			/*
       * La cuenta fue creada correctamente, pero el canal fue
       * deshabilitado desde la configuración administrativa.
       */
			$this->messenger()->addWarning(
				$this->t(
					'Tu cuenta fue creada, pero actualmente no hay un correo de activación configurado. Comunícate con la Asociación para continuar.'
				),
			);
		} else {
			$this->messenger()->addError(
				$this->t(
					'Tu cuenta fue creada, pero no fue posible enviar el correo de activación. Comunícate con la Asociación para continuar.'
				),
			);
		}

		$form_state->setRedirect('user.login');
	}
}
