(function ($, Drupal, once) {
  'use strict';

  /**
   * Lleva la página al inicio y centra el modal una vez Drupal
   * ha terminado de crear el jQuery UI Dialog.
   */
  function positionConfirmationDialog($dialog) {
    if (!$dialog || !$dialog.length) {
      return;
    }

    window.scrollTo(0, 0);

    window.requestAnimationFrame(function () {
      $dialog.dialog('option', 'position', {
        my: 'center',
        at: 'center',
        of: window,
        collision: 'fit'
      });
    });
  }

  Drupal.behaviors.asocoldermaSolicitudStateConfirm = {
    attach(context) {

      once(
        'asocolderma-dialog-aftercreate',
        'html',
        context
      ).forEach(function () {

        $(window).on(
          'dialog:aftercreate.asocoldermaSolicitudStateConfirm',
          function (event, dialog, $element) {

            if (
              !$element ||
              !$element.length ||
              !$element.find('.asocolderma-confirm-dialog').length
            ) {
              return;
            }

            positionConfirmationDialog($element);
          }
        );
      });

      once(
        'asocolderma-confirm-yes',
        '#asocolderma-confirm-yes',
        context
      ).forEach(function (element) {
        $(element).on('click', function (event) {
          event.preventDefault();

          $('#asocolderma-solicitud-hidden-submit').trigger('click');
        });
      });

      once(
        'asocolderma-confirm-no',
        '#asocolderma-confirm-no',
        context
      ).forEach(function (element) {
        $(element).on('click', function (event) {
          event.preventDefault();

          const destination = $(element).data('destination') || '/';
          const $dialog = $(element).closest('.ui-dialog-content');

          if ($dialog.length) {
            $dialog.dialog('close');
          }

          window.location.href = destination;
        });
      });

      once(
        'asocolderma-cancel-button',
        '#asocolderma-cancel-button',
        context
      ).forEach(function (element) {
        $(element).on('click', function (event) {
          event.preventDefault();

          const destination = $(element).data('destination') || '/';
          window.location.href = destination;
        });
      });
    }
  };
})(jQuery, Drupal, once);