(function ($) {
	'use strict';

	function getStatusElement(button) {
		return button.closest('.ai1wm-backup-actions').find('.ai1wm-backup-status');
	}

	function composeStatusText(status, fallbackRemote, fallbackUpdated) {
		if (!status || !status.state) {
			return '';
		}

		var text = status.message || capitalize(status.state);
		var remote = status.remote_key || fallbackRemote;

		if (remote && text.indexOf(remote) === -1) {
			text += ' • ' + remote;
		}

		return text;
	}

	function capitalize(value) {
		if (!value) {
			return '';
		}

		return value.charAt(0).toUpperCase() + value.slice(1);
	}

	function setButtonBusy(button, busy) {
		if (busy) {
			button.attr('disabled', 'disabled');
		} else {
			button.removeAttr('disabled');
		}
	}

	function showStatus(statusElement, message, state) {
		statusElement.text(message);
		statusElement.attr('data-state', state || '');
	}

	function showError(statusElement, message) {
		showStatus(statusElement, ai1wm_s3.strings.failed_prefix + ' ' + message, 'failed');
	}

	function collectMissingFields() {
		var form = $('.ai1wm-s3-form');
		var missing = [];

		form.find('[data-s3-required]').each(function () {
			var field = $(this);
			if (!$.trim(field.val())) {
				missing.push({
					label: field.data('label') || field.attr('name'),
					element: field
				});
			}
		});

		return missing;
	}

	$(document).on('click', '.ai1wm-backup-s3', function (event) {
		var button = $(this);

		if (button.is('[disabled]')) {
			return;
		}

		var statusElement = getStatusElement(button);

		if (!ai1wm_s3.configured) {
			var missing = collectMissingFields();
			if (missing.length) {
				showStatus(statusElement, ai1wm_s3.strings.missing_fields.replace('%s', missing.map(function (item) {
					return item.label;
				}).join(', ')), 'failed');
				missing[0].element.focus();
			} else {
				showStatus(statusElement, ai1wm_s3.strings.saving_required, 'failed');
			}

			event.preventDefault();
			return;
		}

		event.preventDefault();

		var archive = button.data('archive');
		var existingRemote = statusElement.attr('data-remote');
		var existingUpdated = statusElement.attr('data-updated');

		setButtonBusy(button, true);
		showStatus(statusElement, ai1wm_s3.strings.preparing, 'pending');

		$.ajax({
			url: ai1wm_s3.ajax.url,
			type: 'POST',
			dataType: 'json',
			data: {
				secret_key: ai1wm_s3.secret_key,
				archive: archive
			}
		}).done(function (response) {
			if (response && response.success && response.data && response.data.status) {
				var status = response.data.status;
				showStatus(statusElement, composeStatusText(status, existingRemote, existingUpdated), status.state || '');
				button.data('state', status.state || '');
				if (status.remote_key) {
					statusElement.attr('data-remote', status.remote_key);
				}
				if (status.updated_at) {
					statusElement.attr('data-updated', status.updated_at);
				}
			} else if (response && response.data && response.data.errors) {
				showError(statusElement, response.data.errors.join('\n'));
			} else {
				showError(statusElement, ai1wm_s3.strings.generic_error);
			}
		}).fail(function (jqXHR) {
			var message = ai1wm_s3.strings.generic_error;
			if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.errors) {
				message = jqXHR.responseJSON.data.errors.join('\n');
			}
			showError(statusElement, message);
		}).always(function () {
			setButtonBusy(button, false);
		});
	});
})(jQuery);
