<?php

declare(strict_types=1);

namespace Drupal\asocolderma_inscription\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/** Permite llegar al menú de configuración sin abrir sus demás opciones. */
final class SolicitudAspirantesReportAccess
{

	public function access(AccountInterface $account): AccessResult
	{
		return AccessResult::allowedIfHasPermissions(
			$account,
			['administer site configuration', 'export asocolderma aspirantes report'],
			'OR',
		)->cachePerPermissions();
	}
}
