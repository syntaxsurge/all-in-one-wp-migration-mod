(function ($) {
	'use strict';

	var activity = ai1wm_s3.statuses || {};
	var stateLabels = ai1wm_s3.strings.state_labels || {};
	var overlay = $('#ai1wm-backup-log-overlay');
	var overlayContent = overlay.find('.ai1wm-backup-log-content');
	var overlayTitle = overlay.find('#ai1wm-backup-log-title');

	function cssEscape(value) {
		if (window.CSS && window.CSS.escape) {
			return window.CSS.escape(value);
		}
		return value.replace(/([ #;?%&,.+*~':"!^$\[\]()=>|@\/])/g, '\\$1');
	}

	function getStatusElement(button) {
		return button.closest('.ai1wm-backup-actions').find('.ai1wm-backup-status');
	}

	function capitalize(value) {
		if (!value) {
			return '';
		}

		return value.charAt(0).toUpperCase() + value.slice(1);
	}

	function formatState(state) {
		if (!state) {
			return '';
		}

		return stateLabels[state] || capitalize(state);
	}

	function formatTimeAgo(timestamp) {
		if (!timestamp) {
			return '';
		}

		var now = Math.floor(Date.now() / 1000);
		var diff = Math.max(0, now - timestamp);

		if (diff < 60) {
			var seconds = Math.max(1, diff);
			if (seconds === 1) {
				return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_second);
			}

			return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_seconds.replace('%s', seconds));
		}

		if (diff < 3600) {
			var minutes = Math.max(1, Math.round(diff / 60));
			if (minutes === 1) {
				return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_minute);
			}

			return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_minutes.replace('%s', minutes));
		}

		if (diff < 86400) {
			var hours = Math.max(1, Math.round(diff / 3600));
			if (hours === 1) {
				return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_hour);
			}

			return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_hours.replace('%s', hours));
		}

		var days = Math.max(1, Math.round(diff / 86400));
		if (days === 1) {
			return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_day);
		}

		return ai1wm_s3.strings.updated.replace('%s', ai1wm_s3.strings.time_days.replace('%s', days));
	}

	function setButtonBusy(button, busy) {
		if (busy) {
			button.attr('disabled', 'disabled');
		} else {
			button.removeAttr('disabled');
		}
	}

	function showStatus(statusElement, message, state) {
		statusElement.text(message || '');
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

	function updateStatusAttributes(target, status) {
		if (!target || !target.length || !status) {
			return;
		}

		target.attr('data-state', status.state || '');
		target.attr('data-remote', status.remote_key || '');
		target.attr('data-updated', status.updated_at || '');
		target.attr('data-filename', status.filename || status.archive || '');
		target.attr('data-log', JSON.stringify(status));
	}

	function updateLogButton(button, status) {
		if (!button || !button.length || !status) {
			return;
		}

		button.attr('data-log', JSON.stringify(status));
		button.attr('data-filename', status.filename || status.archive || '');
	}

	function ensureActivityTable() {
		var container = $('#ai1wm-backups-s3-activity');
		var table = container.find('.ai1wm-backups-logs');

		if (!table.length) {
			var head = '<thead><tr>' +
				'<th>' + ai1wm_s3.strings.col_backup + '</th>' +
				'<th>' + ai1wm_s3.strings.col_destination + '</th>' +
				'<th>' + ai1wm_s3.strings.col_status + '</th>' +
				'<th>' + ai1wm_s3.strings.col_updated + '</th>' +
				'<th>' + ai1wm_s3.strings.col_logs + '</th>' +
				'</tr></thead>';

			container.find('.ai1wm-backups-logs-empty').remove();
			container.append('<table class="ai1wm-backups ai1wm-backups-logs">' + head + '<tbody></tbody></table>');
			table = container.find('.ai1wm-backups-logs');
		}

		return table.find('tbody');
	}

	function updateActivityRow(archive, status) {
		if (!archive || !status) {
			return;
		}

		status.archive = archive;
		status.filename = status.filename || status.archive || archive;
		activity[archive] = status;

		var tableBody = ensureActivityTable();
		var selector = 'tr[data-archive="' + cssEscape(archive) + '"]';
		var row = tableBody.find(selector);
		var updatedText = status.updated_at ? formatTimeAgo(status.updated_at) : '';
		var destination = status.remote_key || '';
		var stateText = formatState(status.state);
		var payload = JSON.stringify(status);

		if (!row.length) {
			row = $('<tr></tr>').attr('data-archive', archive);
			row.append('<td class="ai1wm-log-name"></td>');
			row.append('<td class="ai1wm-log-destination"></td>');
			row.append('<td class="ai1wm-log-state"></td>');
			row.append('<td class="ai1wm-log-updated"></td>');
			row.append('<td class="ai1wm-log-actions"><a href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button"><i class="ai1wm-icon-notification"></i><span>' + ai1wm_s3.strings.view_log + '</span></a></td>');
			tableBody.prepend(row);
		}

		row.attr('data-log', payload);
		row.find('.ai1wm-log-name').text(status.filename || status.archive || archive);
		row.find('.ai1wm-log-destination').text(destination);
		row.find('.ai1wm-log-state').text(stateText);
		row.find('.ai1wm-log-updated').text(updatedText || '—');
		row.find('.ai1wm-backup-log-button').attr('data-archive', archive).attr('data-filename', status.filename || status.archive || archive).attr('data-log', payload);
	}

	function populateInitialActivity() {
		Object.keys(activity).forEach(function (archive) {
			updateActivityRow(archive, activity[archive]);
		});
	}

	function parseLogData(element) {
		var raw = element.attr('data-log');
		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		} catch (error) {
			return null;
		}
	}

	function buildLogMessage(data) {
		var lines = [];

		if (data.message) {
			lines.push(data.message);
		}

		if (!data.message && data.state) {
			lines.push(formatState(data.state));
		}

		if (data.remote_key) {
			lines.push(ai1wm_s3.strings.destination.replace('%s', data.remote_key));
		}

		if (data.updated_at) {
			lines.push(formatTimeAgo(data.updated_at));
		}

		if (!lines.length) {
			lines.push(ai1wm_s3.strings.no_log);
		}

		return lines.join('\n');
	}

	function openLogModal(data) {
		if (!overlay.length || !data) {
			return;
		}

		var titleSuffix = data.filename || data.archive || '';
		var modalTitle = titleSuffix ? ai1wm_s3.strings.modal_title_with_name.replace('%s', titleSuffix) : ai1wm_s3.strings.modal_title;

		overlayTitle.text(modalTitle);
		overlayContent.text(buildLogMessage(data));
		overlay.addClass('ai1wm-show').attr('aria-hidden', 'false');
		overlay.find('.ai1wm-backup-log-close').trigger('focus');
	}

	function closeLogModal() {
		if (!overlay.length) {
			return;
		}

		overlay.removeClass('ai1wm-show').attr('aria-hidden', 'true');
	}

	function handleLogButtonClick(event) {
		event.preventDefault();

		var trigger = $(this);
		var data = parseLogData(trigger);

		if (!data) {
			return;
		}

		openLogModal(data);
	}

	function handleOverlayClick(event) {
		if ($(event.target).is('#ai1wm-backup-log-overlay')) {
			closeLogModal();
		}
	}

	function handleKeyUp(event) {
		if (event.key === 'Escape') {
			closeLogModal();
		}
	}

	$(document).on('click', '.ai1wm-backup-log-button', handleLogButtonClick);
	$(document).on('click', '.ai1wm-backup-log-close', function (event) {
		event.preventDefault();
		closeLogModal();
	});
	$(document).on('click', '#ai1wm-backup-log-overlay', handleOverlayClick);
	$(document).on('keyup', handleKeyUp);

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
					status.archive = archive;
					status.filename = status.filename || button.data('filename') || button.closest('tr').find('.ai1wm-column-name').text().trim() || archive;

				showStatus(statusElement, status.message || formatState(status.state), status.state || '');
				updateStatusAttributes(statusElement, status);
				updateLogButton(button, status);
				updateActivityRow(archive, status);
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

	populateInitialActivity();
})(jQuery);
