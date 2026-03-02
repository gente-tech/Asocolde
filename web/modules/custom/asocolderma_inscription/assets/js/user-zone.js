(function (Drupal, once) {
  Drupal.behaviors.userZoneMenu = {
    attach(context) {
      once('userZoneMenu', '.user-zone-menu', context).forEach((menu) => {
        const links = menu.querySelectorAll('a.user-zone-menu__link');
        const path = window.location.pathname.replace(/\/$/, '');
        links.forEach((a) => {
          const href = a.getAttribute('href').replace(/\/$/, '');
          if (href === path) a.classList.add('is-active');
        });
      });
    }
  };
})(Drupal, once);
