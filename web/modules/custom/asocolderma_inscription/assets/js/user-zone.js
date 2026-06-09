(function (Drupal, once, drupalSettings) {
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

      once('asocolderma-underage-modal', 'body', context).forEach(() => {
        const settings = drupalSettings.asocoldermaInscription || {};
        const modalSettings = settings.underageModal || {};

        if (!modalSettings.show) {
          return;
        }

        const existingModal = document.querySelector('[data-user-zone-underage-modal]');
        if (existingModal) {
          existingModal.remove();
        }

        const modal = document.createElement('div');
        modal.className = 'user-zone-underage-modal is-open';
        modal.setAttribute('data-user-zone-underage-modal', '1');
        modal.setAttribute('aria-hidden', 'false');

        modal.innerHTML = `
          <div class="user-zone-underage-modal__backdrop"></div>
          <div class="user-zone-underage-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-zone-underage-title">
            <div class="user-zone-underage-modal__icon">!</div>
            <h2 id="user-zone-underage-title" class="user-zone-underage-modal__title">${modalSettings.title || 'No puedes continuar'}</h2>
            <p class="user-zone-underage-modal__text">${modalSettings.message || 'Debes ser mayor de edad para continuar con el proceso.'}</p>
            <button type="button" class="user-zone-underage-modal__button" data-user-zone-underage-close>
              Entendido
            </button>
          </div>
        `;

        document.body.appendChild(modal);
        document.body.classList.add('user-zone-underage-modal-open');

        const closeModal = () => {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('user-zone-underage-modal-open');
          modal.remove();
        };

        modal.querySelector('[data-user-zone-underage-close]').addEventListener('click', closeModal);
        modal.querySelector('.user-zone-underage-modal__backdrop').addEventListener('click', closeModal);

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeModal();
          }
        });
      });
    },
  };
})(Drupal, once, drupalSettings);