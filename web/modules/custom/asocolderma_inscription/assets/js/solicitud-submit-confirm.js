(function ($, Drupal, once) {
	'use strict';

	Drupal.behaviors.asocoldermaSolicitudSubmitConfirm = {
		attach(context) {
			once(
				'asocolderma-solicitud-submit-confirm',
				'#asocolderma-solicitud-submit',
				context
			).forEach(function (button) {
				const form = button.form;

				if (!form) {
					return;
				}

				let confirmed = false;
				let dialogOpen = false;
				let submitting = false;

				form.addEventListener('submit', function (event) {
					if (event.submitter !== button) {
						return;
					}

					if (submitting) {
						event.preventDefault();
						return;
					}

					if (confirmed) {
						submitting = true;
						return;
					}

					event.preventDefault();

					if (dialogOpen || !form.reportValidity()) {
						return;
					}

					dialogOpen = true;

					const $content = $('<div>', {
						class: 'asocolderma-submit-confirm-dialog'
					}).append(
						$('<p>').text(
							Drupal.t('Antes de enviar la solicitud, verifique que toda la información suministrada sea correcta y que los soportes correspondan a lo requerido y en sus versiones actualizadas. Una vez enviados no podrán ser modificados')
						)
					);

					const dialog = Drupal.dialog($content, {
						dialogClass: 'asocolderma-submit-confirm-modal',
						title: Drupal.t('Confirmar envío de solicitud'),
						modal: true,
						width: 600,
						maxWidth: '95%',
						resizable: false,
						draggable: false,
						closeOnEscape: true,
						buttons: [
							{
								text: Drupal.t('Cancelar'),
								class: 'btn solicitud-confirm-cancel',
								click: function () {
									dialog.close();
								}
							},
							{
								text: Drupal.t('Confirmar envío'),
								class: 'btn solicitud-confirm-submit',
								click: function () {
									confirmed = true;
									dialog.close();

									form.requestSubmit(button);

									if (!submitting) {
										confirmed = false;
									}
								}
							}
						],
						close: function () {
							dialogOpen = false;
							$content.remove();
						}
					});

					dialog.showModal();
				});
			});
		}
	};
})(jQuery, Drupal, once);