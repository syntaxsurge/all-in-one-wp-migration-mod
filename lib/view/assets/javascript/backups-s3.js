(function ($) {
	'use strict';

	var DEFAULT_STRINGS = {
		view_log: 'View log',
		col_backup: 'Backup',
		col_destination: 'Destination',
		col_status: 'Status',
		col_updated: 'Updated',
		col_logs: 'Logs',
		destination: 'Destination: %s',
		updated: 'Updated %s ago',
		no_log: 'No log message available yet.',
		modal_title: 'Remote storage log',
		modal_title_with_name: 'Remote storage log: %s',
		time_second: 'a second',
		time_seconds: '%s seconds',
		time_minute: 'a minute',
		time_minutes: '%s minutes',
		time_hour: 'an hour',
		time_hours: '%s hours',
		time_day: 'a day',
		time_days: '%s days',
		failed_prefix: 'Upload failed:',
		preparing: 'Scheduling upload...',
		saving_required: 'Save your remote storage settings before copying a backup.',
		generic_error: 'Unexpected error while uploading to remote storage.',
		not_configured: 'Configure remote storage to enable uploads.',
		state_labels: {
			queued: 'Queued',
			in_progress: 'In progress',
			success: 'Completed',
			failed: 'Failed',
			pending: 'Pending'
		}
	};

	var s3Data = window.ai1wm_s3 || {};
	var strings = $.extend(true, {}, DEFAULT_STRINGS, s3Data.strings || {});
	var activity = (s3Data.statuses && typeof s3Data.statuses === 'object') ? s3Data.statuses : {};
	if (!s3Data.statuses || typeof s3Data.statuses !== 'object') {
		s3Data.statuses = activity;
	}
	var stateLabels = strings.state_labels || {};
	var ajaxUrl = (s3Data.ajax && s3Data.ajax.url) ? s3Data.ajax.url : '';
	var secretKey = s3Data.secret_key || '';
	var isConfigured = typeof s3Data.configured === 'undefined' ? false : !!s3Data.configured;
	var MODAL_SELECTOR = '#ai1wmS3LogModal';
	var MODAL_TRIGGER_SELECTOR = '[data-target="#ai1wmS3LogModal"]';
	var lastModalTrigger = null;

	$(document).on('click', MODAL_TRIGGER_SELECTOR, function () {
		lastModalTrigger = $(this);
	});

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
				return strings.updated.replace('%s', strings.time_second);
			}

			return strings.updated.replace('%s', strings.time_seconds.replace('%s', seconds));
		}

		if (diff < 3600) {
			var minutes = Math.max(1, Math.round(diff / 60));
			if (minutes === 1) {
				return strings.updated.replace('%s', strings.time_minute);
			}

			return strings.updated.replace('%s', strings.time_minutes.replace('%s', minutes));
		}

		if (diff < 86400) {
			var hours = Math.max(1, Math.round(diff / 3600));
			if (hours === 1) {
				return strings.updated.replace('%s', strings.time_hour);
			}

			return strings.updated.replace('%s', strings.time_hours.replace('%s', hours));
		}

		var days = Math.max(1, Math.round(diff / 86400));
		if (days === 1) {
			return strings.updated.replace('%s', strings.time_day);
		}

		return strings.updated.replace('%s', strings.time_days.replace('%s', days));
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
    statusElement.removeClass('ai1wm-hide');
    statusElement.attr('data-state', state || '');
}

	function showError(statusElement, message) {
		showStatus(statusElement, strings.failed_prefix + ' ' + message, 'failed');
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
		button.attr('data-archive', status.archive || button.data('archive') || '');
		button.attr('data-type', 'log');
		button.attr('data-toggle', 'modal');
		button.attr('data-target', MODAL_SELECTOR);
		button.attr('title', strings.view_log);
	}

	function ensureActivityTable() {
		var container = $('#ai1wm-backups-s3-activity');
		var table = container.find('.ai1wm-backups-logs');

		if (!table.length) {
			var head = '<thead><tr>' +
				'<th>' + strings.col_backup + '</th>' +
				'<th>' + strings.col_destination + '</th>' +
				'<th>' + strings.col_status + '</th>' +
				'<th>' + strings.col_updated + '</th>' +
				'<th>' + strings.col_logs + '</th>' +
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
			row.append('<td class="ai1wm-log-actions"><a href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="log" title="' + strings.view_log + '"><i class="ai1wm-icon-notification"></i><span>' + strings.view_log + '</span></a></td>');
			tableBody.prepend(row);
		}

		row.attr('data-log', payload);
		row.find('.ai1wm-log-name').text(status.filename || status.archive || archive);
		row.find('.ai1wm-log-destination').text(destination);
		row.find('.ai1wm-log-state').text(stateText);
		row.find('.ai1wm-log-updated').text(updatedText || '—');
		row.find('.ai1wm-backup-log-button')
			.attr('data-archive', archive)
			.attr('data-filename', status.filename || status.archive || archive)
			.attr('data-log', payload)
			.attr('title', strings.view_log)
			.attr('data-type', 'log')
			.attr('data-toggle', 'modal')
			.attr('data-target', MODAL_SELECTOR);
	}

	function populateInitialActivity() {
		Object.keys(activity).forEach(function (archive) {
			updateActivityRow(archive, activity[archive]);
		});
	}

function parseLogData(element) {
    // Try to read pre-baked JSON first (may be HTML-escaped by server).
    var raw = element.attr('data-log');
    function decodeHtmlEntities(str) {
        if (!str) return str;
        try {
            return $('<textarea/>').html(str).text();
        } catch (e) {
            return str;
        }
    }
    if (raw) {
        try {
            var decoded = decodeHtmlEntities(raw);
            return JSON.parse(decoded);
        } catch (error) {
            // fall through to compose from attributes / registry
        }
    }

    // Fallback: compose from data attributes and registry
    var archive = element.data('archive');
    if (!archive) {
        var row = element.closest('[data-archive]');
        archive = row.length ? row.data('archive') : null;
    }

    // Try nearest status element for context
    var statusEl = element.closest('td, tr').find('.ai1wm-backup-status');
    var status = null;

    if (archive && typeof activity === 'object' && activity[archive]) {
        status = $.extend({}, activity[archive]);
    }

    if (!status && statusEl.length) {
        status = {
            archive: archive || statusEl.data('archive') || '',
            filename: statusEl.data('filename') || element.data('filename') || '',
            remote_key: statusEl.attr('data-remote') || '',
            state: statusEl.attr('data-state') || '',
            updated_at: parseInt(statusEl.attr('data-updated') || '0', 10) || 0,
            message: statusEl.text() || ''
        };
    }

    if (!status && archive) {
        // Compose minimal payload
        status = {
            archive: archive,
            filename: element.data('filename') || archive,
            remote_key: '',
            state: '',
            updated_at: 0,
            message: ''
        };
    }

    return status;
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
			lines.push(strings.destination.replace('%s', data.remote_key));
		}

		if (data.updated_at) {
			lines.push(formatTimeAgo(data.updated_at));
		}

		if (!lines.length) {
			lines.push(strings.no_log);
		}

		return lines.join('\n');
	}

// Populate Bootstrap modal when opened via data-toggle
$(document).on('show.bs.modal', MODAL_SELECTOR, function (e) {
	var container = $(this);
	var body = container.find('.ai1wm-backup-log-content');
	var titleEl = container.find('.modal-title');
	var trigger = $(e.relatedTarget || []);

	if ((!trigger.length || !trigger.is(MODAL_TRIGGER_SELECTOR)) && lastModalTrigger && lastModalTrigger.length) {
		trigger = lastModalTrigger;
	}

	if (!trigger.length || !trigger.is(MODAL_TRIGGER_SELECTOR)) {
		trigger = $(document.activeElement);
	}

	if (!trigger.length || !trigger.is(MODAL_TRIGGER_SELECTOR)) {
		trigger = $();
	} else {
		lastModalTrigger = trigger;
	}

	var type = (trigger && trigger.data('type')) || 'log';

	if (!trigger.length) {
		body.text(strings.no_log);
		titleEl.text(strings.modal_title);
		return;
	}

	if (type === 'log') {
		var data = parseLogData(trigger);
		var titleSuffix = (data && (data.filename || data.archive)) || trigger.data('filename') || trigger.data('archive') || '';
		var modalTitle = titleSuffix ? strings.modal_title_with_name.replace('%s', titleSuffix) : strings.modal_title;
		titleEl.text(modalTitle);
		body.text(buildLogMessage(data || {}));
		return;
	}

	if (type === 'upload') {
		var archive = trigger.data('archive');
		var filename = trigger.data('filename') || archive;
		var modalTitleUpload = filename ? strings.modal_title_with_name.replace('%s', filename) : strings.modal_title;
		titleEl.text(modalTitleUpload);
		body.text(strings.preparing);

		var statusElement = getStatusElement(trigger);
		showStatus(statusElement, strings.preparing, 'pending');

		if (!isConfigured) {
			body.text(strings.saving_required);
			return;
		}

		if (!ajaxUrl || !archive) {
			var missingMessage = strings.generic_error;
			showError(statusElement, missingMessage);
			body.text(missingMessage);
			return;
		}

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				secret_key: secretKey,
				archive: archive
			}
		}).done(function (response) {
			if (response && response.success && response.data && response.data.status) {
				var status = response.data.status;
				status.archive = archive;
				status.filename = status.filename || filename;
				showStatus(statusElement, status.message || (status.state || ''), status.state || '');
				updateStatusAttributes(statusElement, status);
				updateLogButton(trigger, status);
				updateActivityRow(archive, status);
				body.text(buildLogMessage(status));
			} else if (response && response.data && response.data.errors) {
				var msg = response.data.errors.join('\n');
				showError(statusElement, msg);
				body.text(strings.failed_prefix + ' ' + msg);
			} else {
				var generic = strings.generic_error;
				showError(statusElement, generic);
				body.text(generic);
			}
		}).fail(function (jqXHR) {
			var message = strings.generic_error;
			if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.errors) {
				message = jqXHR.responseJSON.data.errors.join('\n');
			}
			showError(statusElement, message);
			body.text(strings.failed_prefix + ' ' + message);
		});
	}
});

// Copy-to-S3 scheduling handled in Bootstrap show.bs.modal above

	populateInitialActivity();
})(jQuery);
