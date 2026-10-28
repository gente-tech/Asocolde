
<?php

namespace Drupal\asocolderma_inscription\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirige los accesos a login/registro cuando ya existe una sesión activa.
 */
final class LoginRedirectSubscriber implements EventSubscriberInterface
{

	/**
	 * Rutas protegidas para cuentas autenticadas.
	 *
	 * Se comprueba el path antes del enrutamiento para evitar
	 * que Drupal envíe user.register a la edición de cuenta.
	 */
	private const AUTH_PATHS = [
		'/user/login' => TRUE,
		'/user/register' => TRUE,
		'/aspirante/login' => TRUE,
		'/registro-aspirante' => TRUE,
		'/gestion-data/login' => TRUE,
	];

	public function __construct(
		private readonly AccountProxyInterface $currentUser,
		private readonly CurrentRouteMatch $currentRouteMatch,
	) {}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			// Después de autenticación y antes del enrutamiento.
			KernelEvents::REQUEST => ['onRequest', 40],
			KernelEvents::RESPONSE => ['onResponse'],
		];
	}

	/**
	 * Impide mostrar login o registro a usuarios autenticados.
	 */
	public function onRequest(RequestEvent $event): void
	{
		if (
			!$event->isMainRequest() ||
			!$this->currentUser->isAuthenticated()
		) {
			return;
		}

		$request = $event->getRequest();
		$path = rtrim($request->getPathInfo(), '/') ?: '/';

		if (!isset(self::AUTH_PATHS[$path])) {
			return;
		}

		// Evita que ?destination reemplace la ruta por rol.
		$request->query->remove('destination');

		$url = Url::fromUserInput($this->getDefaultPath(), [
			'absolute' => TRUE,
		])->toString();

		if (
			$request->isXmlHttpRequest() ||
			in_array((string) $request->query->get('_wrapper_format'), [
				'drupal_modal',
				'drupal_ajax',
			], TRUE)
		) {
			$response = new AjaxResponse();
			$response->addCommand(new RedirectCommand($url));
		} else {
			$response = new RedirectResponse($url, 302);
		}

		$response->headers->set('Cache-Control', 'private, no-store');
		$event->setResponse($response);
	}

	/**
	 * Conserva las redirecciones existentes del login general.
	 */
	public function onResponse(ResponseEvent $event): void
	{
		if (
			!$event->isMainRequest() ||
			!$this->currentUser->isAuthenticated() ||
			$this->currentRouteMatch->getRouteName() !== 'user.login'
		) {
			return;
		}

		if (
			$this->currentUser->hasRole('coordinacion_administrativa') ||
			$this->currentUser->hasRole('secretaria_general') ||
			$this->currentUser->hasRole('aspirante')
		) {
			$event->setResponse(new RedirectResponse(
				Url::fromUserInput($this->getDefaultPath())->toString()
			));
		}
	}

	/**
	 * Obtiene el destino correspondiente según el rol.
	 */
	private function getDefaultPath(): string
	{
		if (function_exists('asocolderma_data_core_database_login_allowed_roles')) {
			foreach (asocolderma_data_core_database_login_allowed_roles() as $role) {
				if ($this->currentUser->hasRole($role)) {
					return asocolderma_data_core_database_default_path();
				}
			}
		}

		if ($this->currentUser->hasRole('coordinacion_administrativa')) {
			return '/coord-admin/enviar-documentos';
		}

		if ($this->currentUser->hasRole('secretaria_general')) {
			return '/solicitudes-aspirantes';
		}

		if ($this->currentUser->hasRole('aspirante')) {
			return '/zona/solicitudes';
		}

		// Login general de asociados.
		return '/dermatologos';
	}
}
