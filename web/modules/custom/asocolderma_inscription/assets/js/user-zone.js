(function (Drupal, once) {
  Drupal.behaviors.asocoldermaUserZoneMenu = {
    attach(context) {
      once('asocolderma-user-zone-menu', '[data-user-zone-menu-toggle]', context).forEach((button) => {
        const targetId = button.getAttribute('aria-controls');
        const menu = document.getElementById(targetId);

        if (!menu) {
          return;
        }

        button.addEventListener('click', () => {
          const expanded = button.getAttribute('aria-expanded') === 'true';

          button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
          menu.classList.toggle('is-open', !expanded);
        });
      });
    },
  };
})(Drupal, once);