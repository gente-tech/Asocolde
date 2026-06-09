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
          button.classList.toggle('is-open', !expanded);
          menu.classList.toggle('is-open', !expanded);
          document.body.classList.toggle('user-zone-menu-open', !expanded);
        });
      });

      once('asocolderma-user-zone-dropdown', '[data-user-zone-dropdown]', context).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();

          const group = trigger.closest('.user-zone-app-menu__group');

          if (!group) {
            return;
          }

          const isOpen = group.classList.contains('is-open');

          document.querySelectorAll('.user-zone-app-menu__group.is-open').forEach((openGroup) => {
            if (openGroup !== group) {
              openGroup.classList.remove('is-open');
            }
          });

          group.classList.toggle('is-open', !isOpen);
        });
      });

      once('asocolderma-user-zone-outside-click', 'body', context).forEach((body) => {
        body.addEventListener('click', (event) => {
          if (event.target.closest('.user-zone-app-menu__group')) {
            return;
          }

          document.querySelectorAll('.user-zone-app-menu__group.is-open').forEach((group) => {
            group.classList.remove('is-open');
          });
        });
      });
      once('asocolderma-user-zone-logout-modal', '[data-user-zone-logout-trigger]', context).forEach((trigger) => {
        const modal = document.querySelector('[data-user-zone-logout-modal]');
        const confirm = modal ? modal.querySelector('[data-user-zone-logout-confirm]') : null;
        const cancelButtons = modal ? modal.querySelectorAll('[data-user-zone-logout-cancel]') : [];

        if (!modal || !confirm) {
          return;
        }

        const openModal = (event) => {
          event.preventDefault();

          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          document.body.classList.add('user-zone-logout-modal-open');

          confirm.focus();
        };

        const closeModal = () => {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('user-zone-logout-modal-open');

          trigger.focus();
        };

        trigger.addEventListener('click', openModal);

        cancelButtons.forEach((button) => {
          button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
          }
        });
      });
    },
  };
})(Drupal, once);