<?php

namespace Drupal\asocol\EventSubscriber;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber subscribing to KernelEvents::REQUEST.
 */
class RedirectAuthenticatedSubscriber implements EventSubscriberInterface {

  /**
   * Stores the current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected PathMatcherInterface $pathMatcher;

  /**
   * Constructs a RedirectAuthenticatedSubscriber object.
   */
  public function __construct(AccountProxyInterface $current_user, PathMatcherInterface $pathMatcher) {
    $this->currentUser = $current_user;
    $this->pathMatcher = $pathMatcher;
  }

  public function checkAuthStatus(RequestEvent $event) {
    if ($this->currentUser->isAuthenticated() && $this->pathMatcher->isFrontPage()) {
      $response = new RedirectResponse('/dermatologos', 302);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkAuthStatus'];
    return $events;
  }

}
