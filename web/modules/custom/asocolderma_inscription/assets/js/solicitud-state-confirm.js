(function ($, Drupal, once) {
  'use strict';

  function centerDialog($content) {
    if (!$content || !$content.length) {
      return;
    }

    if (!$content.hasClass('ui-dialog-content')) {
      $content = $content.closest('.ui-dialog-content');
    }

    if (!$content.length || !$content.hasClass('ui-dialog-content')) {
      return;
    }

    if (!$content.hasClass('ui-dialog-content')) {
      return;
    }

    try {
      $content.dialog('option', 'position', {
        my: 'center',
        at: 'center',
        of: window,
        collision: 'fit'
      });

      $content.dialog('option', 'position', $content.dialog('option', 'position'));
    }
    catch (e) {
      // El diálogo puede no estar completamente inicializado todavía.
    }
  }

  function centerOpenConfirmationDialogs() {
    $('.asocolderma-confirm-dialog').each(function () {
      const $content = $(this).closest('.ui-dialog-content');
      centerDialog($content);
    });
  }

  Drupal.behaviors.asocoldermaSolicitudStateConfirm = {
    attach(context) {

      once(
        'asocolderma-center-confirm-dialog',
        '.asocolderma-confirm-dialog',
        context
      ).forEach(function (el) {
        const $content = $(el).closest('.ui-dialog-content');

        // Esperamos a que OpenModalDialogCommand termine de construir
        // y posicionar completamente el jQuery UI dialog.
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            centerDialog($content);
          });
        });

        window.setTimeout(function () {
          centerDialog($content);
        }, 50);
      });

      once(
        'asocolderma-confirm-yes',
        '#asocolderma-confirm-yes',
        context
      ).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();
          $('#asocolderma-solicitud-hidden-submit').trigger('click');
        });
      });

      once(
        'asocolderma-confirm-no',
        '#asocolderma-confirm-no',
        context
      ).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();

          const destination = $(el).data('destination') || '/';
          const $dialog = $(el).closest('.ui-dialog-content');

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
      ).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();

          const destination = $(el).data('destination') || '/';
          window.location.href = destination;
        });
      });

      once(
        'asocolderma-confirm-resize',
        'html',
        context
      ).forEach(function () {
        $(window).on('resize.asocoldermaSolicitudStateConfirm', function () {
          centerOpenConfirmationDialogs();
        });
      });
    }
  };
})(jQuery, Drupal, once);