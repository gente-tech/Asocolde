<?php

namespace Drupal\asocolderma_inscription\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Controla el acceso del aspirante a la edición de una solicitud en aclaración.
 */
final class SolicitudEditClarificationAccess
{

	/**
	 * Permite editar únicamente al aspirante dueño de la solicitud
	 * cuando el estado actual es "Pendiente aclaración".
	 */
	public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface
	{
		$result = AccessResult::forbidden()
			->addCacheableDependency($node)
			->cachePerUser();

		if ($node->bundle() !== 'solicitud_ingreso') {
			return $result;
		}

		if (!$account->isAuthenticated()) {
			return $result;
		}

		if (!in_array('aspirante', $account->getRoles(), TRUE)) {
			return $result;
		}

		if ((int) $node->getOwnerId() !== (int) $account->id()) {
			return $result;
		}

		if (!$node->hasField('field_state') || $node->get('field_state')->isEmpty()) {
			return $result;
		}

		$state = $node->get('field_state')->entity;
		$state_label = $state ? trim((string) $state->label()) : '';

		if ($state_label !== 'Pendiente aclaración') {
			return $result;
		}

		return AccessResult::allowed()
			->addCacheableDependency($node)
			->cachePerUser();
	}
}
