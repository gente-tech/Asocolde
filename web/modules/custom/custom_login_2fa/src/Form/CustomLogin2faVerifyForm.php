<?php

declare(strict_types=1);

namespace Drupal\custom_login_2fa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\custom_login_2fa\Service\CustomLogin2faManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides the 2FA verification form.
 */
final class CustomLogin2faVerifyForm extends FormBase
{

	/**
	 * Constructs a new CustomLogin2faVerifyForm.
	 */
	public function __construct(
		private readonly PrivateTempStoreFactory $tempStoreFactory,
		private readonly CustomLogin2faManager $manager,
		private readonly AccountProxyInterface $currentUser,
	) {}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): self
	{
		return new self(
			$container->get('tempstore.private'),
			$container->get('custom_login_2fa.manager'),
			$container->get('current_user'),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormId(): string
	{
		return 'custom_login_2fa_verify_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array
	{
		if (!$this->currentUser->isAnonymous()) {
			$form['message'] = [
				'#markup' => '<p>Ya tienes una sesión activa.</p>',
			];

			$form['actions']['go_home'] = [
				'#type' => 'link',
				'#title' => $this->t('Ir al inicio'),
				'#url' => Url::fromRoute('<front>'),
				'#attributes' => [
					'class' => ['button', 'button--primary'],
				],
			];

			return $form;
		}

		$tempstore = $this->tempStoreFactory->get('custom_login_2fa');
		$pending_uid = (int) ($tempstore->get('pending_uid') ?: 0);

		if ($pending_uid <= 0) {
			$this->messenger()->addError($this->t('No existe una verificación pendiente. Inicia sesión nuevamente.'));
			$form_state->setRedirect('user.login');
			return $form;
		}

		$account = User::load($pending_uid);
		if (!$account || !$account->isActive()) {
			$tempstore->delete('pending_uid');
			$tempstore->delete('pending_challenge_id');
			$tempstore->delete('pending_created');

			$this->messenger()->addError($this->t('La cuenta no está disponible. Inicia sesión nuevamente.'));
			$form_state->setRedirect('user.login');
			return $form;
		}

		$form['intro'] = [
			'#type' => 'markup',
			'#markup' => '<p>Ingresa el código de verificación enviado a tu correo electrónico para completar el inicio de sesión.</p>',
		];

		$form['code'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Código de verificación'),
			'#required' => TRUE,
			'#maxlength' => 10,
			'#size' => 10,
			'#attributes' => [
				'autocomplete' => 'one-time-code',
				'autocapitalize' => 'characters',
				'spellcheck' => 'false',
				'class' => ['custom-login-2fa-code'],
			],
			'#description' => $this->t('El código vence rápidamente. Escríbelo tal como aparece en el correo.'),
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Verificar e iniciar sesión'),
			'#button_type' => 'primary',
		];

		$form['actions']['resend'] = [
			'#type' => 'submit',
			'#value' => $this->t('Reenviar código'),
			'#submit' => ['::resendCodeSubmit'],
			'#limit_validation_errors' => [],
			'#attributes' => [
				'class' => ['button'],
			],
		];

		$form['actions']['cancel'] = [
			'#type' => 'submit',
			'#value' => $this->t('Cancelar'),
			'#submit' => ['::cancelSubmit'],
			'#limit_validation_errors' => [],
			'#attributes' => [
				'class' => ['button'],
			],
		];

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state): void
	{
		$tempstore = $this->tempStoreFactory->get('custom_login_2fa');
		$pending_uid = (int) ($tempstore->get('pending_uid') ?: 0);

		if ($pending_uid <= 0) {
			$form_state->setErrorByName('code', $this->t('No existe una verificación pendiente. Inicia sesión nuevamente.'));
			return;
		}

		$code = strtoupper(trim((string) $form_state->getValue('code')));

		if ($code === '') {
			$form_state->setErrorByName('code', $this->t('Debes ingresar el código de verificación.'));
			return;
		}

		if (!preg_match('/^[A-Z0-9]{5,10}$/', $code)) {
			$form_state->setErrorByName('code', $this->t('El código solo puede contener letras mayúsculas y números.'));
			return;
		}

		$result = $this->manager->validateCode($pending_uid, $code);

		if (empty($result['valid'])) {
			$form_state->setErrorByName('code', $result['message']);
			return;
		}

		$form_state->set('custom_login_2fa_validated_uid', $pending_uid);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void
	{
		$uid = (int) $form_state->get('custom_login_2fa_validated_uid');

		if ($uid <= 0) {
			$this->messenger()->addError($this->t('No fue posible completar el inicio de sesión.'));
			$form_state->setRedirect('user.login');
			return;
		}

		$account = User::load($uid);

		if (!$account || !$account->isActive()) {
			$this->messenger()->addError($this->t('La cuenta no está disponible.'));
			$form_state->setRedirect('user.login');
			return;
		}

		user_login_finalize($account);

		$tempstore = $this->tempStoreFactory->get('custom_login_2fa');
		$tempstore->delete('pending_uid');
		$tempstore->delete('pending_challenge_id');
		$tempstore->delete('pending_created');

		$redirect_path = $this->manager->getRedirectPathForUser($account);
		$form_state->setRedirectUrl(Url::fromUri('internal:' . $redirect_path));
	}

	/**
	 * Resends the 2FA verification code.
	 */
	public function resendCodeSubmit(array &$form, FormStateInterface $form_state): void
	{
		$tempstore = $this->tempStoreFactory->get('custom_login_2fa');
		$pending_uid = (int) ($tempstore->get('pending_uid') ?: 0);

		if ($pending_uid <= 0) {
			$this->messenger()->addError($this->t('No existe una verificación pendiente. Inicia sesión nuevamente.'));
			$form_state->setRedirect('user.login');
			return;
		}

		$account = User::load($pending_uid);

		if (!$account || !$account->isActive()) {
			$tempstore->delete('pending_uid');
			$tempstore->delete('pending_challenge_id');
			$tempstore->delete('pending_created');
			$tempstore->delete('last_resend_time');
			$tempstore->delete('resend_count');

			$this->messenger()->addError($this->t('La cuenta no está disponible. Inicia sesión nuevamente.'));
			$form_state->setRedirect('user.login');
			return;
		}

		$now = \Drupal::time()->getRequestTime();
		$cooldown = $this->manager->getResendCooldown();
		$max_resends = $this->manager->getMaxResends();

		$last_resend_time = (int) ($tempstore->get('last_resend_time') ?: 0);
		$resend_count = (int) ($tempstore->get('resend_count') ?: 0);

		if ($max_resends === 0) {
			$this->messenger()->addError($this->t('El reenvío de códigos no está habilitado. Inicia sesión nuevamente.'));
			$form_state->setRedirect('custom_login_2fa.verify');
			return;
		}

		if ($resend_count >= $max_resends) {
			$this->manager->invalidatePendingCodes((int) $account->id());

			$tempstore->delete('pending_uid');
			$tempstore->delete('pending_challenge_id');
			$tempstore->delete('pending_created');
			$tempstore->delete('last_resend_time');
			$tempstore->delete('resend_count');

			$this->messenger()->addError($this->t('Superaste el máximo de reenvíos permitidos. Inicia sesión nuevamente.'));
			$form_state->setRedirect('user.login');
			return;
		}

		if ($last_resend_time > 0 && ($now - $last_resend_time) < $cooldown) {
			$remaining = $cooldown - ($now - $last_resend_time);

			$this->messenger()->addWarning($this->t('Debes esperar @seconds segundos antes de reenviar otro código.', [
				'@seconds' => $remaining,
			]));

			$form_state->setRedirect('custom_login_2fa.verify');
			return;
		}

		try {
			$challenge_id = $this->manager->createAndSendChallenge($account);

			$tempstore->set('pending_uid', (int) $account->id());
			$tempstore->set('pending_challenge_id', (int) $challenge_id);
			$tempstore->set('pending_created', $now);
			$tempstore->set('last_resend_time', $now);
			$tempstore->set('resend_count', $resend_count + 1);

			$this->messenger()->addStatus($this->t('Se envió un nuevo código de verificación a tu correo electrónico.'));

			$form_state->setRedirect('custom_login_2fa.verify');
		} catch (\Throwable $exception) {
			\Drupal::logger('custom_login_2fa')->error(
				'Could not resend 2FA challenge for user @uid. Error: @error',
				[
					'@uid' => $account->id(),
					'@error' => $exception->getMessage(),
				]
			);

			$this->messenger()->addError($this->t('No fue posible reenviar el código de verificación. Intenta nuevamente o contacta al administrador.'));
			$form_state->setRedirect('custom_login_2fa.verify');
		}
	}

	/**
	 * Cancels the pending 2FA verification.
	 */
	public function cancelSubmit(array &$form, FormStateInterface $form_state): void
	{
		$tempstore = $this->tempStoreFactory->get('custom_login_2fa');

		$pending_uid = (int) ($tempstore->get('pending_uid') ?: 0);

		if ($pending_uid > 0) {
			$this->manager->invalidatePendingCodes($pending_uid);
		}

		$tempstore->delete('pending_uid');
		$tempstore->delete('pending_challenge_id');
		$tempstore->delete('pending_created');

		$this->messenger()->addStatus($this->t('La verificación fue cancelada. Inicia sesión nuevamente.'));

		$form_state->setRedirect('user.login');
	}
}
