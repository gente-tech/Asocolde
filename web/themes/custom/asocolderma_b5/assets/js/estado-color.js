(function (Drupal) {

  Drupal.behaviors.estadoSolicitudColor = {
    attach: function (context) {

      document.querySelectorAll('.estado-solicitud-color', context).forEach(function (el) {

        const color = el.getAttribute('data-estado-color');

        if (color) {
          el.style.backgroundColor = color;
        }

      });

    }
  };

})(Drupal);