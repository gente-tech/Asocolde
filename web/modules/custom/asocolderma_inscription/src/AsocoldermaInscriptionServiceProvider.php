
<?php

namespace Drupal\asocolderma_inscription;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ajusta los servicios existentes sin modificar otros módulos.
 */
final class AsocoldermaInscriptionServiceProvider extends ServiceProviderBase
{

	/**
	 * {@inheritdoc}
	 */
	public function alter(ContainerBuilder $container): void
	{

		// Desactivar exclusivamente la redirección automática
		// del home hacia /dermatologos.
		if ($container->hasDefinition('asocol.redirect_subscriber')) {
			$container
				->getDefinition('asocol.redirect_subscriber')
				->clearTag('event_subscriber');
		}
	}
}
