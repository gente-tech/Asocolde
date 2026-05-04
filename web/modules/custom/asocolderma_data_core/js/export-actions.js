(function (Drupal, once) {
	Drupal.behaviors.asocoldermaExportActions = {
		attach(context) {
			once('asocolderma-export-actions', '.view-patrocinadores', context).forEach(function (view) {
				const wrapper = document.createElement('div');

				wrapper.className = 'asocolderma-export-actions';
				wrapper.innerHTML = `
					<div class="asocolderma-export-actions__inner">
						<span class="asocolderma-export-actions__label">No items selected</span>

						<label class="asocolderma-export-actions__control">
						<span>Action:</span>
						<select id="asocolderma-export-action">
							<option value="">- Seleccionar -</option>
							<option value="pdf">Exportar PDF</option>
						</select>
						</label>

						<button type="button" class="button button--primary" id="asocolderma-export-submit">
						Aplicar
						</button>
					</div>
				`;

				view.appendChild(wrapper);
			});
		}
	};
})(Drupal, once);