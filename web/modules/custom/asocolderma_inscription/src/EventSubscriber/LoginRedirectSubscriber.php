<?php

namespace Drupal\asocolderma_inscription\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\CurrentRouteMatch;

class LoginRedirectSubscriber implements EventSubscriberInterface
{

	protected $currentUser;
	protected $currentRouteMatch;

	public function __construct(AccountProxyInterface $current_user, CurrentRouteMatch $current_route_match)
	{
		$this->currentUser = $current_user;
		$this->currentRouteMatch = $current_route_match;
	}

	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::RESPONSE => ['onResponse'],
		];
	}

	public function onResponse(ResponseEvent $event)
	{
		if ($this->currentRouteMatch->getRouteName() === 'user.login') {

			if ($this->currentUser->hasRole('coordinacion_administrativa')) {
				$event->setResponse(new RedirectResponse('/coord-admin/enviar-documentos'));
				return;
			}

			if ($this->currentUser->hasRole('secretaria_general')) {
				$event->setResponse(new RedirectResponse('/solicitudes-aspirantes'));
				return;
			}

			if ($this->currentUser->hasRole('aspirante')) {
				$event->setResponse(new RedirectResponse('/zona/solicitudes'));
				return;
			}
		}
	}
}
