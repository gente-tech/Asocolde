<?php

namespace Drupal\asocolderma_inscription\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\asocolderma_inscription\Service\SolicitudManager;

final class SolicitudCreateAccess {

  public function __construct(private readonly SolicitudManager $manager) {}

  public function access(AccountInterface $account): AccessResult {
    // Regla: solo aspirante (si además quieres validar rol).
    if (!in_array('aspirante', $account->getRoles(), TRUE)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }

    $has_active = $this->manager->hasActiveSolicitud((int) $account->id());

    $result = $has_active ? AccessResult::forbidden() : AccessResult::allowed();

    // CRÍTICO para que el menú no se cachee cruzado.
    return $result->addCacheContexts(['user']);
  }
}