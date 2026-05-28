(function (Drupal, once, drupalSettings) {
	Drupal.behaviors.asocoldermaRecordContextMenu = {
		attach(context) {
			const settings = drupalSettings.asocoldermaDataCore || {};
			const permissions = settings.permissions || {};
			const routes = settings.routes || {};

			if (permissions.canAdmin !== true) {
				return;
			}

			const pageConfig = resolvePageConfig(window.location.pathname, routes);

			if (!pageConfig) {
				return;
			}

			const tables = once(
				'asocolderma-record-context-menu-' + pageConfig.tableKey,
				'table',
				context
			);

			tables.forEach(function (table) {
				bindTableRows(table, pageConfig);
			});

			bindGlobalClose();
		}
	};

	function resolvePageConfig(pathname, routes) {
		const editRoutes = routes.edit || {};

		if (pathname.includes('/gestion-data/proveedores')) {
			return {
				tableKey: 'proveedores',
				label: 'proveedor',
				editBaseUrl: editRoutes.proveedores || '/gestion-data/proveedores'
			};
		}

		return null;
	}

	function bindTableRows(table, pageConfig) {
		table.querySelectorAll('tbody tr').forEach(function (row) {
			if (row.dataset.asocoldermaContextMenuBound === '1') {
				return;
			}

			row.dataset.asocoldermaContextMenuBound = '1';
			row.classList.add('asocolderma-context-row');

			row.addEventListener('contextmenu', function (event) {
				const recordId = getRecordIdFromRow(row);

				if (!recordId) {
					return;
				}

				event.preventDefault();

				showContextMenu(event.pageX, event.pageY, {
					recordId: recordId,
					editUrl: pageConfig.editBaseUrl + '/' + recordId + '/editar',
					label: pageConfig.label
				});
			});
		});
	}

	function getRecordIdFromRow(row) {
		const checkbox = row.querySelector('.asocolderma-row-select');

		if (checkbox && checkbox.value) {
			return checkbox.value.trim();
		}

		const idCell = row.querySelector('td.views-field-id');

		if (idCell && idCell.textContent.trim()) {
			return idCell.textContent.trim();
		}

		const firstCell = row.querySelector('td');

		if (firstCell && firstCell.textContent.trim().match(/^\d+$/)) {
			return firstCell.textContent.trim();
		}

		return '';
	}

	function showContextMenu(x, y, data) {
		removeContextMenu();

		const menu = document.createElement('div');
		menu.className = 'asocolderma-record-context-menu';
		menu.innerHTML = `
			<button type="button" class="asocolderma-record-context-menu__item">
				Editar ${escapeHtml(data.label)}
			</button>
		`;

		document.body.appendChild(menu);

		menu.style.left = x + 'px';
		menu.style.top = y + 'px';

		const editButton = menu.querySelector('.asocolderma-record-context-menu__item');

		editButton.addEventListener('click', function () {
			window.location.href = data.editUrl;
		});
	}

	function bindGlobalClose() {
		if (document.body.dataset.asocoldermaContextMenuGlobalBound === '1') {
			return;
		}

		document.body.dataset.asocoldermaContextMenuGlobalBound = '1';

		document.addEventListener('click', removeContextMenu);
		document.addEventListener('scroll', removeContextMenu, true);
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				removeContextMenu();
			}
		});
		window.addEventListener('resize', removeContextMenu);
	}

	function removeContextMenu() {
		document.querySelectorAll('.asocolderma-record-context-menu').forEach(function (menu) {
			menu.remove();
		});
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

})(Drupal, once, drupalSettings);