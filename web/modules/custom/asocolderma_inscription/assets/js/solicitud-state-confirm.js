(function ($, Drupal, once) {
  Drupal.behaviors.asocoldermaSolicitudStateConfirm = {
    attach(context) {
      once('asocolderma-confirm-yes', '#asocolderma-confirm-yes', context).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();
          $('#asocolderma-solicitud-hidden-submit').trigger('click');
        });
      });

      once('asocolderma-confirm-no', '#asocolderma-confirm-no', context).forEach(function (el) {
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
    }
  };
})(jQuery, Drupal, once);