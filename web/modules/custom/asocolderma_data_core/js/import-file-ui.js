(function (Drupal, once) {
	Drupal.behaviors.asocoldermaImportFileUi = {
		attach(context) {
			once('asocolderma-import-file-ui', '.data-core-file-input', context).forEach(function (input) {
				const wrapper = document.createElement('div');
				wrapper.className = 'data-core-file-upload';

				const button = document.createElement('span');
				button.className = 'data-core-file-upload__button';
				button.textContent = 'Seleccionar archivo';

				const text = document.createElement('span');
				text.className = 'data-core-file-upload__text';
				text.textContent = 'Ningún archivo seleccionado';

				input.parentNode.insertBefore(wrapper, input);
				wrapper.appendChild(input);
				wrapper.appendChild(button);
				wrapper.appendChild(text);

				input.addEventListener('change', function () {
					text.textContent = input.files && input.files.length
						? input.files[0].name
						: 'Ningún archivo seleccionado';
				});

				button.addEventListener('click', function () {
					input.click();
				});

				text.addEventListener('click', function () {
					input.click();
				});
			});
		}
	};
})(Drupal, once);