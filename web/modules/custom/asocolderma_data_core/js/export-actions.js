(function (Drupal, once) {
	Drupal.behaviors.asocoldermaExportActions = {
		attach(context) {
			once('asocolderma-export-actions', '.view-patrocinadores', context).forEach(function (view) {
				const table = view.querySelector('table');
				if (table) {
					const headerRow = table.querySelector('thead tr');
					if (headerRow && !headerRow.querySelector('.asocolderma-select-column')) {
						const th = document.createElement('th');
						th.className = 'asocolderma-select-column';
						th.innerHTML = '<input type="checkbox" id="asocolderma-select-all">';
						headerRow.insertBefore(th, headerRow.firstElementChild);
					}

					table.querySelectorAll('tbody tr').forEach(function (row) {
						if (row.querySelector('.asocolderma-row-select')) {
							return;
						}

						const firstCell = row.querySelector('td');
						const rowId = firstCell ? firstCell.textContent.trim() : '';

						const td = document.createElement('td');
						td.className = 'asocolderma-select-column';
						td.innerHTML = '<input type="checkbox" class="asocolderma-row-select" value="' + rowId + '">';

						row.insertBefore(td, row.firstElementChild);
					});
				}

				const selectAll = table.querySelector('#asocolderma-select-all');
				const rowCheckboxes = table.querySelectorAll('.asocolderma-row-select');

				if (selectAll) {
					selectAll.addEventListener('change', function () {
						rowCheckboxes.forEach(function (checkbox) {
							checkbox.checked = selectAll.checked;
						});
					});
				}

				function updateSelectedLabel() {
					const selectedLabel = view.querySelector('.asocolderma-export-actions__label');

					if (!selectedLabel || !table) {
						return;
					}

					const selectedCount = table.querySelectorAll('.asocolderma-row-select:checked').length;

					selectedLabel.textContent = selectedCount === 1
						? '1 item selected'
						: selectedCount + ' items selected';
				}

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

				const exportButton = view.querySelector('#asocolderma-export-submit');
				const exportSelect = view.querySelector('#asocolderma-export-action');

				if (exportButton && exportSelect) {
					exportButton.addEventListener('click', function () {
						const action = exportSelect.value;

						if (!action) {
							return;
						}

						if (action === 'pdf') {
							window.open('/admin/asocolderma/patrocinadores/export/pdf', '_blank');
						}
					});
				}

				view.appendChild(wrapper);

				rowCheckboxes.forEach(function (checkbox) {
					checkbox.addEventListener('change', updateSelectedLabel);
				});

				if (selectAll) {
					selectAll.addEventListener('change', updateSelectedLabel);
				}

				updateSelectedLabel();
			});
		}
	};
})(Drupal, once);