(function ($, Drupal, once) {
  'use strict';

  /**
   * Lleva la página al inicio y centra el diálogo en el viewport.
   */
  function showConfirmationCentered($content) {
    if (!$content || !$content.length) {
      return;
    }

    // La confirmación siempre debe mostrarse desde el inicio de la página.
    if (document.scrollingElement) {
      document.scrollingElement.scrollTop = 0;
    }

    window.scrollTo({
      top: 0,
      left: 0,
      behavior: 'auto'
    });

    // Esperamos al siguiente ciclo de renderizado para que el navegador
    // aplique primero el cambio de scroll.
    window.requestAnimationFrame(function () {
      try {
        $content.dialog('option', 'position', {
          my: 'center',
          at: 'center',
          of: window,
          collision: 'fit'
        });
      }
      catch (error) {
        // El diálogo debe estar inicializado por Drupal/jQuery UI.
      }
    });
  }

  Drupal.behaviors.asocoldermaSolicitudStateConfirm = {
    attach(context) {

      once(
        'asocolderma-confirm-dialog-position',
        '.asocolderma-confirm-dialog',
        context
      ).forEach(function (element) {
        const $content = $(element).closest('.ui-dialog-content');

        showConfirmationCentered($content);
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