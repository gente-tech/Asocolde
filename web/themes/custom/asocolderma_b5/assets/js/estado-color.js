(function (Drupal) {

  function normalizeHex(hex) {
    if (!hex) {
      return null;
    }

    hex = hex.trim().replace('#', '');

    if (hex.length === 3) {
      hex = hex.split('').map(function (char) {
        return char + char;
      }).join('');
    }

    if (hex.length !== 6) {
      return null;
    }

    return '#' + hex;
  }

  function getContrastTextColor(backgroundColor) {
    const hex = normalizeHex(backgroundColor);

    if (!hex) {
      return '#000000';
    }

    const r = parseInt(hex.substring(1, 3), 16);
    const g = parseInt(hex.substring(3, 5), 16);
    const b = parseInt(hex.substring(5, 7), 16);

    const luminance = ((r * 299) + (g * 587) + (b * 114)) / 1000;

    return luminance >= 150 ? '#000000' : '#ffffff';
  }

  Drupal.behaviors.estadoSolicitudColor = {
    attach: function (context) {

      document.querySelectorAll('.estado-solicitud-color', context).forEach(function (el) {
        const color = el.getAttribute('data-estado-color');
        const normalizedColor = normalizeHex(color);

        if (!normalizedColor) {
          return;
        }

        el.style.backgroundColor = normalizedColor;
        el.style.color = getContrastTextColor(normalizedColor);
      });

    }
  };

})(Drupal);