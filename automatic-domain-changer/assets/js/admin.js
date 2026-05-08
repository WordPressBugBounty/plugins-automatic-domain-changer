(function () {
	'use strict';

	function bind() {
		var backupForm = document.getElementById('adc-backup-db');
		if (!backupForm) {
			return;
		}

		var typeInput = backupForm.querySelector('input[name="type"]');
		var buttons = document.querySelectorAll('.adc-backup-button');

		buttons.forEach(function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				if (typeInput) {
					typeInput.value = button.getAttribute('data-type') || 'sql';
				}
				backupForm.submit();
			});
		});
	}

	// Script is enqueued in the footer, so the DOM is usually already ready by the
	// time we run. Fall back to DOMContentLoaded only if we somehow got in early.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
