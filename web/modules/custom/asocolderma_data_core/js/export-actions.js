(function (Drupal, once, drupalSettings) {
	Drupal.behaviors.asocoldermaExportActions = {
		attach(context) {
			const settings = drupalSettings.asocoldermaDataCore || {};
			const permissions = settings.permissions || {};
			const routes = settings.routes || {};

			const canAdmin = permissions.canAdmin === true;
			const canAudit = permissions.canAudit === true;
			const canView = permissions.canView === true;

			/*
			 * Los botones de crear están colocados manualmente en los encabezados
			 * de las Views. No deben mostrarse a usuarios sin permiso administrativo.
			 *
			 * Actualmente las rutas /crear requieren:
			 * administer asocolderma data core
			 */
			if (!canAdmin) {
				removeCreateButtons(context);
			}

			// El operador solo puede ver la tabla. No debe ver checks ni acciones.
			if (!canAdmin && !canAudit) {
				return;
			}

			const currentPath = window.location.pathname;
			const pageConfig = resolvePageConfig(currentPath, routes);

			if (!pageConfig) {
				return;
			}

			const tables = once(
				'asocolderma-export-actions-table-' + pageConfig.tableKey,
				'table',
				context
			);

			tables.forEach(function (table) {
				const view = table.closest('.view') || table.parentElement;

				if (!view) {
					return;
				}

				addSelectionColumn(table);
				addBulkActions(view, table, pageConfig, {
					canAdmin,
					canAudit,
					canView,
				});
				updateSelectedLabel(view, table);
			});
		}
	};

	function removeCreateButtons(context) {
		const selectors = [
			'.data-core-create-button',
			'a[href$="/crear"]',
			'a[href*="/gestion-data/patrocinadores/crear"]',
			'a[href*="/gestion-data/proveedores/crear"]',
			'a[href*="/gestion-data/asociados/crear"]',
			'a[href*="/gestion-data/residentes/crear"]',
			'a[href*="/gestion-data/empleados/crear"]'
		];

		once(
			'asocolderma-remove-create-buttons',
			selectors.join(','),
			context
		).forEach(function (element) {
			element.remove();
		});
	}

	function resolvePageConfig(pathname, routes) {
		const exports = routes.exports || {};

		if (pathname.includes('/patrocinadores')) {
			return {
				tableKey: 'patrocinadores',
				exportUrl: exports.patrocinadores || '/gestion-data/patrocinadores/exportar',
				bulkStatusUrl: routes.bulkStatusUrl || '/gestion-data/registros/estado-masivo',
				bulkDeleteUrl: routes.bulkDeleteUrl || '/gestion-data/registros/eliminar-masivo'
			};
		}

		if (pathname.includes('/proveedores')) {
			return {
				tableKey: 'proveedores',
				exportUrl: exports.proveedores || '/gestion-data/proveedores/exportar',
				bulkStatusUrl: routes.bulkStatusUrl || '/gestion-data/registros/estado-masivo',
				bulkDeleteUrl: routes.bulkDeleteUrl || '/gestion-data/registros/eliminar-masivo'
			};
		}

		if (pathname.includes('/asociados')) {
			return {
				tableKey: 'asociados',
				exportUrl: exports.asociados || '/gestion-data/asociados/exportar',
				bulkStatusUrl: routes.bulkStatusUrl || '/gestion-data/registros/estado-masivo',
				bulkDeleteUrl: routes.bulkDeleteUrl || '/gestion-data/registros/eliminar-masivo'
			};
		}

		if (pathname.includes('/residentes')) {
			return {
				tableKey: 'residentes',
				exportUrl: exports.residentes || '/gestion-data/residentes/exportar',
				bulkStatusUrl: routes.bulkStatusUrl || '/gestion-data/registros/estado-masivo',
				bulkDeleteUrl: routes.bulkDeleteUrl || '/gestion-data/registros/eliminar-masivo'
			};
		}

		if (pathname.includes('/empleados')) {
			return {
				tableKey: 'empleados',
				exportUrl: exports.empleados || '/gestion-data/empleados/exportar',
				bulkStatusUrl: routes.bulkStatusUrl || '/gestion-data/registros/estado-masivo',
				bulkDeleteUrl: routes.bulkDeleteUrl || '/gestion-data/registros/eliminar-masivo'
			};
		}

		return null;
	}

	function addSelectionColumn(table) {
		const headerRow = table.querySelector('thead tr');

		if (headerRow && !headerRow.querySelector('.asocolderma-select-column')) {
			const th = document.createElement('th');
			th.className = 'asocolderma-select-column';
			th.innerHTML = '<input type="checkbox" class="asocolderma-select-all">';
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

		const selectAll = table.querySelector('.asocolderma-select-all');

		if (selectAll && !selectAll.dataset.asocoldermaBound) {
			selectAll.dataset.asocoldermaBound = '1';

			selectAll.addEventListener('change', function () {
				table.querySelectorAll('.asocolderma-row-select').forEach(function (checkbox) {
					checkbox.checked = selectAll.checked;
				});

				const view = table.closest('.view') || table.parentElement;
				updateSelectedLabel(view, table);
			});
		}

		table.querySelectorAll('.asocolderma-row-select').forEach(function (checkbox) {
			if (checkbox.dataset.asocoldermaBound) {
				return;
			}

			checkbox.dataset.asocoldermaBound = '1';

			checkbox.addEventListener('change', function () {
				if (selectAll && !checkbox.checked) {
					selectAll.checked = false;
				}

				const allCheckboxes = table.querySelectorAll('.asocolderma-row-select');
				const checkedCheckboxes = table.querySelectorAll('.asocolderma-row-select:checked');

				if (selectAll && allCheckboxes.length && allCheckboxes.length === checkedCheckboxes.length) {
					selectAll.checked = true;
				}

				const view = table.closest('.view') || table.parentElement;
				updateSelectedLabel(view, table);
			});
		});
	}

	function addBulkActions(view, table, pageConfig, access) {
		if (view.querySelector('.asocolderma-export-actions')) {
			return;
		}

		const options = [];

		if (pageConfig.exportUrl && (access.canAdmin || access.canAudit)) {
			options.push('<option value="excel">Exportar Excel</option>');
		}

		if (access.canAdmin) {
			options.push('<option value="activate">Activar</option>');
			options.push('<option value="deactivate">Desactivar</option>');
			options.push('<option value="delete">Eliminar definitivamente</option>');
		}

		if (!options.length) {
			return;
		}

		const wrapper = document.createElement('div');

		wrapper.className = 'asocolderma-export-actions';
		wrapper.innerHTML = `
			<div class="asocolderma-export-actions__inner">
				<span class="asocolderma-export-actions__label">0 items selected</span>

				<label class="asocolderma-export-actions__control">
					<span>Acción:</span>
					<select class="asocolderma-export-action">
						<option value="">- Seleccionar -</option>
						${options.join('')}
					</select>
				</label>

				<button type="button" class="button button--primary asocolderma-export-submit">
					Aplicar
				</button>
			</div>
		`;

		view.appendChild(wrapper);

		const actionButton = wrapper.querySelector('.asocolderma-export-submit');
		const actionSelect = wrapper.querySelector('.asocolderma-export-action');

		actionButton.addEventListener('click', function () {
			const action = actionSelect.value;

			if (!action) {
				window.alert('Debe seleccionar una acción.');
				return;
			}

			const selectedIds = getSelectedIds(table);

			if (action === 'excel') {
				if (!pageConfig.exportUrl) {
					window.alert('La exportación Excel todavía no está habilitada para esta vista.');
					return;
				}

				const query = new URLSearchParams(window.location.search);

				if (selectedIds.length) {
					query.set('ids', selectedIds.join(','));
				}

				window.location.href = pageConfig.exportUrl + '?' + query.toString();
				return;
			}

			if (!access.canAdmin) {
				window.alert('No tiene permisos para ejecutar esta acción.');
				return;
			}

			if (!selectedIds.length) {
				window.alert('Debe seleccionar al menos un registro.');
				return;
			}

			if (action === 'activate' || action === 'deactivate') {
				applyBulkStatus(action, selectedIds, pageConfig, actionButton);
				return;
			}

			if (action === 'delete') {
				applyBulkDelete(selectedIds, pageConfig, actionButton);
			}
		});
	}

	function getSelectedIds(table) {
		return Array.from(
			table.querySelectorAll('.asocolderma-row-select:checked')
		).map(function (checkbox) {
			return checkbox.value;
		}).filter(function (value) {
			return value !== '';
		});
	}

	function updateSelectedLabel(view, table) {
		if (!view) {
			return;
		}

		const selectedLabel = view.querySelector('.asocolderma-export-actions__label');

		if (!selectedLabel) {
			return;
		}

		const selectedCount = getSelectedIds(table).length;

		selectedLabel.textContent = selectedCount === 1
			? '1 item seleccionado'
			: selectedCount + ' items seleccionados';
	}

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

	async function applyBulkStatus(operation, selectedIds, pageConfig, actionButton) {
		const confirmMessage = operation === 'activate'
			? '¿Está seguro que desea activar estos registros?'
			: '¿Está seguro que desea desactivar estos registros?';

		if (!window.confirm(confirmMessage)) {
			return;
		}

		actionButton.disabled = true;
		actionButton.textContent = 'Procesando...';

		try {
			const result = await postJson(pageConfig.bulkStatusUrl, {
				table: pageConfig.tableKey,
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

	async function applyBulkDelete(selectedIds, pageConfig, actionButton) {
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
			const result = await postJson(pageConfig.bulkDeleteUrl, {
				table: pageConfig.tableKey,
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

})(Drupal, once, drupalSettings);