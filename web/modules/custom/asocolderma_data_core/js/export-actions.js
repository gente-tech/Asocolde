(function (Drupal, once) {
	Drupal.behaviors.asocoldermaExportActions = {
		attach(context) {
			once('asocolderma-export-actions', '.view-patrocinadores', context).forEach(function (view) {
				const table = view.querySelector('table');

				if (!table) {
					return;
				}

				const tableKey = 'patrocinadores';

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

					const idCell = row.querySelector('td.views-field-id');
					const rowId = idCell ? idCell.textContent.trim() : '';

					const td = document.createElement('td');
					td.className = 'asocolderma-select-column';

					if (rowId) {
						td.innerHTML = '<input type="checkbox" class="asocolderma-row-select" value="' + rowId + '">';
					}
					else {
						td.innerHTML = '';
					}

					row.insertBefore(td, row.firstElementChild);
				});

				function getSelectedIds() {
					return Array.from(
						table.querySelectorAll('.asocolderma-row-select:checked')
					).map(function (checkbox) {
						return checkbox.value;
					}).filter(function (value) {
						return value !== '';
					});
				}

				function updateSelectedLabel() {
					const selectedLabel = view.querySelector('.asocolderma-export-actions__label');

					if (!selectedLabel) {
						return;
					}

					const selectedCount = getSelectedIds().length;

					selectedLabel.textContent = selectedCount === 1
						? '1 item selected'
						: selectedCount + ' items selected';
				}

				const selectAll = table.querySelector('#asocolderma-select-all');

				if (selectAll) {
					selectAll.addEventListener('change', function () {
						table.querySelectorAll('.asocolderma-row-select').forEach(function (checkbox) {
							checkbox.checked = selectAll.checked;
						});

						updateSelectedLabel();
					});
				}

				table.querySelectorAll('.asocolderma-row-select').forEach(function (checkbox) {
					checkbox.addEventListener('change', function () {
						if (selectAll && !checkbox.checked) {
							selectAll.checked = false;
						}

						const allCheckboxes = table.querySelectorAll('.asocolderma-row-select');
						const checkedCheckboxes = table.querySelectorAll('.asocolderma-row-select:checked');

						if (selectAll && allCheckboxes.length && allCheckboxes.length === checkedCheckboxes.length) {
							selectAll.checked = true;
						}

						updateSelectedLabel();
					});
				});

				if (!view.querySelector('.asocolderma-export-actions')) {
					const wrapper = document.createElement('div');

					wrapper.className = 'asocolderma-export-actions';
					wrapper.innerHTML = `
						<div class="asocolderma-export-actions__inner">
							<span class="asocolderma-export-actions__label">0 items selected</span>

							<label class="asocolderma-export-actions__control">
								<span>Action:</span>
								<select id="asocolderma-export-action">
									<option value="">- Seleccionar -</option>
									<option value="excel">Exportar Excel</option>
									<option value="activate">Activar</option>
									<option value="deactivate">Desactivar</option>
									<option value="delete">Eliminar definitivamente</option>
								</select>
							</label>

							<button type="button" class="button button--primary" id="asocolderma-export-submit">
								Aplicar
							</button>
						</div>
					`;

					view.appendChild(wrapper);
				}

				const actionButton = view.querySelector('#asocolderma-export-submit');
				const actionSelect = view.querySelector('#asocolderma-export-action');

				async function postJson(url, data) {
					const response = await fetch(url, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json'
						},
						credentials: 'same-origin',
						body: JSON.stringify(data)
					});

					const result = await response.json();

					if (!response.ok || !result.success) {
						throw new Error(result.message || 'No fue posible ejecutar la acción seleccionada.');
					}

					return result;
				}

				async function applyBulkStatus(operation, selectedIds) {
					const confirmMessage = operation === 'activate'
						? '¿Está seguro que desea activar estos registros?'
						: '¿Está seguro que desea desactivar estos registros?';

					if (!window.confirm(confirmMessage)) {
						return;
					}

					actionButton.disabled = true;
					actionButton.textContent = 'Procesando...';

					try {
						const result = await postJson('/admin/asocolderma/data-core/bulk-status', {
							table: tableKey,
							operation: operation,
							ids: selectedIds
						});

						window.alert(result.message || 'Acción ejecutada correctamente.');
						window.location.reload();
					}
					catch (error) {
						console.error(error);
						window.alert(error.message || 'Ocurrió un error al ejecutar la acción seleccionada.');
					}
					finally {
						actionButton.disabled = false;
						actionButton.textContent = 'Aplicar';
					}
				}

				async function applyBulkDelete(selectedIds) {
					const firstConfirm = window.confirm(
						'¿Está seguro que desea eliminar definitivamente estos registros? Esta acción no es un borrado lógico.'
					);

					if (!firstConfirm) {
						return;
					}

					const secondConfirm = window.confirm(
						'Confirmación final: los registros seleccionados se eliminarán físicamente de la tabla. ¿Desea continuar?'
					);

					if (!secondConfirm) {
						return;
					}

					actionButton.disabled = true;
					actionButton.textContent = 'Eliminando...';

					try {
						const result = await postJson('/admin/asocolderma/data-core/bulk-delete', {
							table: tableKey,
							ids: selectedIds
						});

						window.alert(result.message || 'Registros eliminados correctamente.');
						window.location.reload();
					}
					catch (error) {
						console.error(error);
						window.alert(error.message || 'Ocurrió un error al eliminar los registros seleccionados.');
					}
					finally {
						actionButton.disabled = false;
						actionButton.textContent = 'Aplicar';
					}
				}

				if (actionButton && actionSelect && !actionButton.dataset.asocoldermaBound) {
					actionButton.dataset.asocoldermaBound = '1';

					actionButton.addEventListener('click', function () {
						const action = actionSelect.value;

						if (!action) {
							window.alert('Debe seleccionar una acción.');
							return;
						}

						const selectedIds = getSelectedIds();

						if (action === 'excel') {
							const query = new URLSearchParams(window.location.search);

							if (selectedIds.length) {
								query.set('ids', selectedIds.join(','));
							}

							window.location.href = '/admin/asocolderma/patrocinadores/export/excel?' + query.toString();
							return;
						}

						if (!selectedIds.length) {
							window.alert('Debe seleccionar al menos un registro.');
							return;
						}

						if (action === 'activate' || action === 'deactivate') {
							applyBulkStatus(action, selectedIds);
							return;
						}

						if (action === 'delete') {
							applyBulkDelete(selectedIds);
						}
					});
				}

				updateSelectedLabel();
			});
		}
	};
})(Drupal, once);