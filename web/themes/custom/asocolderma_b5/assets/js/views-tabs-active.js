(function (Drupal) {
  Drupal.behaviors.viewsTabsActive = {
    attach: function (context) {

      const currentPath = window.location.pathname;

      document.querySelectorAll('.acciones a').forEach(function(link) {
        const href = link.getAttribute('href');

        if (href === currentPath) {
          link.classList.add('active');
        }
      });

    }
  };
})(Drupal);