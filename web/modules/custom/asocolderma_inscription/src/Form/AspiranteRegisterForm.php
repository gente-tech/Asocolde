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
			$config_email = $this->mandrillService->getMessageGroupByKey('mail_text_1');

			if (!$config_email) {
				throw new \RuntimeException('No existe la configuración de correo mail_text_1.');
			}

			$template_slug = $config_email['mandrill_template_slug'] ?? '';

			if ($template_slug === '') {
				throw new \RuntimeException('La configuración mail_text_1 no tiene slug de plantilla Mandrill.');
			}

			$subject_tokens = [
				'email' => $mail,
				'correo' => $mail,
				'tipo_usuario' => 'aspirante',
			];

			$subject_resolver = \Drupal::service('enterprise_integrations.token_resolver');

			$subject_config = trim((string) ($config_email['subject'] ?? ''));
			if ($subject_config === '') {
				$subject_config = 'Active su cuenta de aspirante Asocolderma';
			}

			$subject = $subject_resolver->replace($subject_config, $subject_tokens);

			$result = $this->mandrillService->sendTemplate(
				$template_slug,
				[
					'subject' => $subject,
					'to_email' => $mail,
					'to_name' => $mail,
				],
				[
					[
						'name' => 'user_email',
						'content' => $mail,
					],
					[
						'name' => 'user_activation_url',
						'content' => $activation_url,
					],
				]
			);

			if (empty($result['success'])) {
				throw new \RuntimeException('Mandrill no confirmó el envío del correo.');
			}

			$response = $result['mandrill_response'] ?? [];

			if (isset($response[0]['status']) && in_array($response[0]['status'], ['rejected', 'invalid'], TRUE)) {
				throw new \RuntimeException('Mandrill rechazó el correo. Estado: ' . $response[0]['status']);
			}

			$this->logger->info(
				'Correo de activación de cuenta enviado a @mail usando plantilla @template.',
				[
					'@mail' => $mail,
					'@template' => $template_slug,
				]
			);

			if (
				!empty($config_email['send_copy']) &&
				!empty($config_email['copy_template_slug']) &&
				!empty($config_email['copy_emails']) &&
				is_array($config_email['copy_emails'])
			) {
				$copy_template_slug = trim((string) $config_email['copy_template_slug']);

				$copy_subject_tokens = [
					'email' => $mail,
				];

				$copy_subject_config = trim((string) ($config_email['copy_subject'] ?? ''));
				if ($copy_subject_config === '') {
					$copy_subject_config = 'Nuevo registro de aspirante Asocolderma - [email]';
				}

				$copy_subject = $subject_resolver->replace($copy_subject_config, $copy_subject_tokens);

				foreach ($config_email['copy_emails'] as $copy_email) {
					$copy_email = trim((string) $copy_email);

					if ($copy_email === '') {
						continue;
					}

					$copy_result = $this->mandrillService->sendTemplate(
						$copy_template_slug,
						[
							'subject' => $copy_subject,
							'to_email' => $copy_email,
						],
						[
							[
								'name' => 'user_email',
								'content' => $mail,
							],
						]
					);

					if (empty($copy_result['success'])) {
						$this->logger->warning(
							'Mandrill no confirmó el envío de copia interna de registro de aspirante a @mail.',
							[
								'@mail' => $copy_email,
							]
						);
					}
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error(
				'Error enviando correo de activación de cuenta a @mail: @error',
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
