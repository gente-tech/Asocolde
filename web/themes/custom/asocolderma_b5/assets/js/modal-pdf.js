(function (Drupal, once) {
  Drupal.behaviors.modalPdfBehavior = {
    attach: function (context, settings) {

      const overlay = document.getElementById('overlayPdf');
      const modal = document.getElementById('modalPdf');
      const pdfFrame = document.getElementById('pdfFrame');
      const closeBtn = document.getElementById('closeBtn');

      if (!overlay || !pdfFrame) {
        return;
      }

      once('pdf-modal-init', context.querySelectorAll('a.btn-pdf')).forEach(link => {

        link.addEventListener('click', function (e) {
          e.preventDefault();
          const href = link.getAttribute('href');
          pdfFrame.src = href;
          overlay.style.display = 'flex';
        });

      });

      function closeModal() {
        overlay.style.display = 'none';
        pdfFrame.src = '';
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
      }

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
          closeModal();
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeModal();
        }
      });

    }
  };
})(Drupal, once);