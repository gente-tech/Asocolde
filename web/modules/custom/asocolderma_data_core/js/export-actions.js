(function (Drupal, once) {
	Drupal.behaviors.asocoldermaExportActions = {
		attach(context) {
			once('asocolderma-export-actions', '.view-patrocinadores', context).forEach(function (view) {
				const wrapper = document.createElement('div');

				wrapper.className = 'asocolderma-export-actions';
				wrapper.innerHTML = `
          <span>No items selected</span>

          <label>
            <strong>Action:</strong>
            <select id="asocolderma-export-action">
              <option value="">- Seleccionar -</option>
              <option value="pdf">Exportar PDF</option>
            </select>
          </label>

          <button type="button" class="button button--primary" id="asocolderma-export-submit">
            Aplicar a los elementos seleccionados
          </button>
        `;

				view.appendChild(wrapper);
			});
		}
	};
})(Drupal, once);