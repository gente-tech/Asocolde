<?php

namespace Drupal\asocolderma_inscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Component\Utility\Crypt;
use Drupal\enterprise_integrations\Service\MandrillService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Psr\Log\LoggerInterface;

class AspiranteRegisterForm extends FormBase
{
	protected MandrillService $mandrillService;
	protected LanguageManagerInterface $languageManager;
	protected LoggerInterface $logger;

	public function __construct(
		MandrillService $mandrillService,
		LanguageManagerInterface $languageManager,
		LoggerInterface $logger
	) {
		$this->mandrillService = $mandrillService;
		$this->languageManager = $languageManager;
		$this->logger = $logger;
	}

	public static function create(ContainerInterface $container)
	{
		return new static(
			$container->get('enterprise_integrations.mandrill'),
			$container->get('language_manager'),
			$container->get('logger.factory')->get('asocolderma_inscription')
		);
	}

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
		try {
			$this->mandrillService->sendConfiguredTemplateMessage(
				'mail_text_1',
				'base-mail',
				[
					'to_email' => $mail,
					'to_name' => $mail,
					'internal_copy' => FALSE,
					'tags' => ['registro_aspirante', 'activacion_cuenta'],
					'metadata' => [
						'user_id' => (string) $user->id(),
						'mail_type' => 'activation',
					],
				],
				[
					'nombre' => $mail,
					'email_title' => 'Asocolderma',
					'email_description' => 'Asociación Colombiana de Dermatología',
					'action_url' => $activation_url,
					'action_text' => 'Activar cuenta',
				]
			);

			$this->logger->info(
				'Correo de activación enviado a @mail.',
				['@mail' => $mail]
			);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Error enviando correo de activación a @mail: @error',
				[
					'@mail' => $mail,
					'@error' => $e->getMessage(),
				]
			);
		}

		$this->messenger()->addStatus(
			$this->t('Te enviamos un correo para activar tu cuenta.')
		);
		$form_state->setRedirect('user.login');
	}
}
