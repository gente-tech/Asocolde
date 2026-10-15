(function ($, Drupal, once) {
	'use strict';

	Drupal.behaviors.asocoldermaActivationLogin = {
		attach(context) {
			once(
				'asocolderma-activation-login',
				'body',
				context
			).forEach(function () {
				const params = new URLSearchParams(window.location.search);

				if (params.get('open_aspirante_login') !== '1') {
					return;
				}

				const $link = $('<a>', {
					href: '/aspirante/login',
					class: 'use-ajax login-popup-form',
					'data-dialog-type': 'modal',
					'data-dialog-options': JSON.stringify({
						classes: {
							'ui-dialog': 'login-dialog aspirante-login-dialog'
						},
						drupalAutoButtons: false
					})
				});

				$('body').append($link);

				Drupal.attachBehaviors($link[0]);

				window.requestAnimationFrame(function () {
					$link.trigger('click');

					const url = new URL(window.location.href);
					url.searchParams.delete('open_aspirante_login');
					window.history.replaceState({}, '', url.pathname + url.search + url.hash);
				});
			});
		}
	};
})(jQuery, Drupal, once);