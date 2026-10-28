
<?php

namespace Drupal\asocolderma_inscription\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protege las rutas privadas de dermatólogos.
 */
final class DermatologistAreaAccessSubscriber implements EventSubscriberInterface
{

	public function __construct(
		private readonly AccountProxyInterface $currentUser,
	) {}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::REQUEST => ['onRequest', 0],
		];
	}

	/**
	 * Redirige aspirantes y usuarios de Gestión Data.
	 */
	public function onRequest(RequestEvent $event): void
	{
		if (
			!$event->isMainRequest() ||
			!$this->currentUser->isAuthenticated()
		) {
			return;
		}

		// Los administradores conservan sus permisos.
		if ($this->currentUser->hasRole('administrator')) {
			return;
		}

		$request = $event->getRequest();
		$path = rtrim($request->getPathInfo(), '/') ?: '/';

		// Solo se restringe la zona privada.
		// El home y el directorio público quedan excluidos.
		if (
			$path !== '/dermatologos' &&
			$path !== '/usuario' &&
			!str_starts_with($path, '/usuario/')
		) {
			return;
		}

		$destination = $this->getRestrictedRoleDestination();

		if ($destination === NULL) {
			return;
		}

		// Evitar destinos externos o redirecciones alternativas.
		$request->query->remove('destination');

		$url = Url::fromUserInput($destination)->toString();

		// Compatibilidad con peticiones AJAX.
		if (
			$request->isXmlHttpRequest() ||
			in_array(
				(string) $request->query->get('_wrapper_format'),
				['drupal_ajax', 'drupal_modal'],
				TRUE
			)
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
	 * Determina el destino según el rol autenticado.
	 */
	private function getRestrictedRoleDestination(): ?string
	{

		// Usuarios de Gestión Data.
		if (function_exists('asocolderma_data_core_database_login_allowed_roles')) {
			foreach (asocolderma_data_core_database_login_allowed_roles() as $role) {
				if ($this->currentUser->hasRole($role)) {
					return asocolderma_data_core_database_default_path();
				}
			}
		}

		// Aspirantes.
		if ($this->currentUser->hasRole('aspirante')) {
			return '/zona/solicitudes';
		}

		return NULL;
	}
}
