(function (Drupal, once) {
	'use strict';

	Drupal.behaviors.asocoldermaIntlPhoneField = {
		attach: function (context) {
			once('asocolderma-intl-phone-field', '.asocolderma-intl-phone', context).forEach(function (input) {
				if (typeof window.intlTelInput !== 'function') {
					return;
				}

				const indicativoSelector = input.getAttribute('data-indicativo-target');
				const fullPhoneSelector = input.getAttribute('data-full-phone-target');

				const indicativoInput = indicativoSelector ? document.querySelector(indicativoSelector) : null;
				const fullPhoneInput = fullPhoneSelector ? document.querySelector(fullPhoneSelector) : null;

				const iti = window.intlTelInput(input, {
					initialCountry: 'co',
					preferredCountries: ['co', 'us', 'mx', 'pe', 'ec', 'pa'],
					separateDialCode: true,
					nationalMode: true,
					autoPlaceholder: 'aggressive',
					utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js'
				});

				const syncPhoneData = function () {
					const countryData = iti.getSelectedCountryData();
					const dialCode = countryData && countryData.dialCode ? '+' + countryData.dialCode : '';
					const nationalNumber = input.value ? input.value.replace(/[^\d]/g, '') : '';

					if (indicativoInput) {
						indicativoInput.value = dialCode;
					}

					if (fullPhoneInput) {
						fullPhoneInput.value = dialCode && nationalNumber ? dialCode + nationalNumber : nationalNumber;
					}
				};

				input.addEventListener('countrychange', syncPhoneData);
				input.addEventListener('keyup', syncPhoneData);
				input.addEventListener('change', syncPhoneData);
				input.addEventListener('blur', syncPhoneData);

				syncPhoneData();
			});
		}
	};

})(Drupal, once);