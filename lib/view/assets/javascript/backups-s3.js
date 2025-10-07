(function ($) {
  'use strict';

  var DEFAULT_STRINGS = {
    view_log: "View log",
    col_backup: "Backup",
    col_destination: "Destination",
    col_status: "Status",
    col_updated: "Updated",
    col_logs: "Logs",
    destination: "Destination: %s",
    updated: "Updated %s ago",
    no_log: "No log message available yet.",
    modal_title: "Remote storage log",
    modal_title_with_name: "Remote storage log: %s",
    time_second: "a second",
    time_seconds: "%s seconds",
    time_minute: "a minute",
    time_minutes: "%s minutes",
    time_hour: "an hour",
    time_hours: "%s hours",
    time_day: "a day",
    time_days: "%s days",
    failed_prefix: "Upload failed:",
    preparing: "Scheduling upload...",
    saving_required:
      "Save your remote storage settings before copying a backup.",
    generic_error: "Unexpected error while uploading to remote storage.",
    not_configured: "Configure remote storage to enable uploads.",
    copy_blocked_active:
      "An upload is already %s for this backup. Please wait for it to finish before copying again.",
    confirm_replace:
      "A remote copy already exists (%s). Replace it? This will delete the existing remote backup before uploading again.",
    replace_cancelled: "Upload cancelled.",
    replacing: "Replacing remote backup...",
    replace_message:
      "The existing remote backup (%s) will be deleted before uploading a fresh copy.",
    show_secret: "Show secret access key",
    hide_secret: "Hide secret access key",
    remote_url_text: "Open remote backup (%s)",
    config_export_success: "Configuration copied to clipboard.",
    config_export_error: "Unable to copy configuration. Please copy manually.",
    config_import_success: "Configuration applied. Review and save to persist.",
    config_import_error: "Paste configuration JSON before applying.",
    config_import_invalid: "Configuration format is invalid. Please check the JSON.",
    state_labels: {
      queued: "Queued",
      in_progress: "In progress",
      success: "Completed",
      failed: "Failed",
      pending: "Pending",
    },
  };

  var s3Data = window.ai1wm_s3 || {};
  var strings = $.extend(true, {}, DEFAULT_STRINGS, s3Data.strings || {});
  var activity =
    s3Data.statuses && typeof s3Data.statuses === "object"
      ? s3Data.statuses
      : {};
  if (!s3Data.statuses || typeof s3Data.statuses !== "object") {
    s3Data.statuses = activity;
  }
  var stateLabels = strings.state_labels || {};
  var ajaxUrl = s3Data.ajax && s3Data.ajax.url ? s3Data.ajax.url : "";
  var secretKey = s3Data.secret_key || "";
  var isConfigured =
    typeof s3Data.configured === "undefined" ? false : !!s3Data.configured;
  // Browse config
  var browseCfg = s3Data.browse || {};
  var listUrl = browseCfg.list_url || "";
  var downloadUrl = browseCfg.download_url || "";
  var statusUrl = browseCfg.status_url || "";
  var cancelUrl = browseCfg.cancel_url || "";
  var basePrefix = (browseCfg.prefix || "").replace(/^\/+|\/+$|/g, "");
  var backupsListUrl = browseCfg.backups_list_url || "";
  var bucketName = browseCfg.bucket || "";
  var currentPath = ""; // relative to configured prefix
  var nextToken = "";
  var isLoading = false;
  var activeDownloads = {}; // key -> intervalId

  function joinPath(a, b) {
    a = (a || "").replace(/^\/+|\/+$/g, "");
    b = (b || "").replace(/^\/+|\/+$/g, "");
    if (!a) return b;
    if (!b) return a;
    return a + "/" + b;
  }

  function parentPath(p) {
    p = (p || "").replace(/^\/+|\/+$/g, "");
    if (!p) return "";
    var parts = p.split("/");
    parts.pop();
    return parts.join("/");
  }

  function fmtSize(bytes) {
    bytes = Number(bytes || 0);
    if (bytes <= 0) return "—";
    var thresh = 1024;
    var units = ["B", "KB", "MB", "GB", "TB"]; var u = 0;
    while (bytes >= thresh && u < units.length - 1) { bytes /= thresh; u++; }
    return bytes.toFixed(u ? 1 : 0) + " " + units[u];
  }

  function fmtDate(iso) {
    if (!iso) return "—";
    var d = new Date(iso);
    if (isNaN(d.getTime())) return "—";
    return d.toLocaleString();
  }

  function updatePathLabel() {
    var label = "/" + (basePrefix ? basePrefix + "/" : "") + (currentPath || "");
    $("#ai1wm-s3-current-path").text(label.replace(/\/+$/, "/"));
  }

  function clearList() {
    var tbody = $("#ai1wm-s3-list tbody");
    tbody.empty();
  }

  function appendEmptyRow() {
    var tbody = $("#ai1wm-s3-list tbody");
    tbody.append(
      '<tr class="ai1wm-s3-empty"><td colspan="4">' +
        (strings.empty_folder || "Empty") +
      "</td></tr>"
    );
  }

  function renderItems(result, append) {
    var tbody = $("#ai1wm-s3-list tbody");
    if (!append) {
      tbody.empty();
    }

    var hadRows = false;
    // Folders first
    if (result && result.prefixes && result.prefixes.length) {
      result.prefixes.forEach(function (p) {
        var name = p.name || p.prefix || "";
        var full = p.prefix || name;
        var row = $("<tr></tr>")
          .attr("data-type", "prefix")
          .attr("data-prefix", full)
          .append('<td><i class="ai1wm-icon-folder"></i> ' + name + "</td>")
          .append('<td>—</td>')
          .append('<td>—</td>')
          .append('<td><button type="button" class="ai1wm-button-gray ai1wm-s3-open">Open</button></td>');
        tbody.append(row);
        hadRows = true;
      });
    }

    // Files
    if (result && result.objects && result.objects.length) {
      result.objects.forEach(function (o) {
        var key = o.key || "";
        var name = o.name || key;
        var size = fmtSize(o.size);
        var mod = fmtDate(o.last_modified);
        var row = $("<tr></tr>")
          .attr("data-type", "object")
          .attr("data-key", key)
          .append('<td><i class="ai1wm-icon-file-zip"></i> ' + name + "</td>")
          .append('<td>' + size + '</td>')
          .append('<td>' + mod + '</td>');

        var actionCell = $("<td></td>");
        if (o.is_backup) {
          var btn = $("<button></button>")
            .addClass("ai1wm-button-green ai1wm-s3-copy")
            .text(strings.copy_here || "Copy to Backups")
            .attr("type", "button")
            .attr("data-key", key);
          actionCell.append(btn);
        } else {
          actionCell.append('<span class="ai1wm-muted">' + (strings.col_action || "Action") + '</span>');
        }

        row.append(actionCell);
        tbody.append(row);
        hadRows = true;
      });
    }

    if (!hadRows) {
      appendEmptyRow();
    }

    // Pagination
    nextToken = (result && result.next_token) || "";
    if (result && result.is_truncated && nextToken) {
      $("#ai1wm-s3-load-more-wrap").show();
    } else {
      $("#ai1wm-s3-load-more-wrap").hide();
    }
  }

  function showBrowseFeedback(msg, isError) {
    // Keep for minimal inline updates (e.g., progress), but prefer toast for user notifications
    var el = $("#ai1wm-s3-feedback");
    if (!msg) { el.text("").removeClass("ai1wm-error ai1wm-success"); return; }
    el.text(msg).removeClass("ai1wm-error ai1wm-success").addClass(isError ? "ai1wm-error" : "ai1wm-success");
  }

  function showToast(message, type) {
    var stack = $("#ai1wm-toasts");
    if (!stack.length || !message) return;
    var item = $('<div class="ai1wm-toast-item"></div>')
      .addClass(type === 'error' ? 'ai1wm-toast--error' : (type === 'success' ? 'ai1wm-toast--success' : 'ai1wm-toast--info'))
      .text(message);
    stack.append(item);
    setTimeout(function(){ item.fadeOut(200, function(){ item.remove(); }); }, 3200);
  }

  function formatProgress(done, total) {
    var d = fmtSize(done);
    var t = total > 0 ? fmtSize(total) : "?";
    var tmpl = strings.progress || "Downloading: %1$s of %2$s";
    return tmpl.replace("%1$s", d).replace("%2$s", t);
  }

  function loadList(path, append) {
    if (!isConfigured || !listUrl) {
      return;
    }
    if (isLoading) return;
    isLoading = true;
    var params = { path: path || currentPath };
    if (append && nextToken) params.token = nextToken;
    $.ajax({ url: listUrl, type: "GET", dataType: "json", data: params })
      .done(function (res) {
        if (res && res.success && res.data && res.data.result) {
          updatePathLabel();
          renderItems(res.data.result, !!append);
          showBrowseFeedback("", false);
        } else if (res && res.data && res.data.errors) {
          showToast(strings.listing_error || "Listing failed.", 'error');
        } else {
          showToast(strings.listing_error || "Listing failed.", 'error');
        }
      })
      .fail(function () {
        showToast(strings.listing_error || "Listing failed.", 'error');
      })
      .always(function () {
        isLoading = false;
      });
  }

  // Handlers for S3 browser
  $(document).on("click", "#ai1wm-s3-refresh", function () {
    nextToken = "";
    loadList(currentPath, false);
  });

  $(document).on("click", "#ai1wm-s3-up", function () {
    currentPath = parentPath(currentPath);
    nextToken = "";
    loadList(currentPath, false);
  });

  $(document).on("click", "#ai1wm-s3-load-more", function () {
    if (!nextToken) return;
    loadList(currentPath, true);
  });

  $(document).on("click", "#ai1wm-s3-list tbody tr[data-type=prefix] .ai1wm-s3-open", function () {
    var row = $(this).closest("tr");
    var full = row.data("prefix") || "";
    currentPath = full;
    nextToken = "";
    loadList(currentPath, false);
  });

  $(document).on("click", "#ai1wm-s3-list tbody tr[data-type=object] .ai1wm-s3-copy", function () {
    if (!downloadUrl) return;
    var btn = $(this);
    var row = btn.closest("tr");
    var key = row.data("key") || btn.data("key") || "";
    if (!key) return;
    setButtonBusy(btn, true);
    showToast(strings.copy_started || "Download started...", 'info');

    $.ajax({
      url: downloadUrl,
      type: "POST",
      dataType: "json",
      data: { secret_key: secretKey, key: key },
    })
      .done(function (res) {
        if (res && res.success && res.data && res.data.job && res.data.job.state) {
          // Mark as active and start polling
          btn.text(strings.cancel || "Cancel").addClass("ai1wm-s3-cancel").removeClass("ai1wm-s3-copy");
          setButtonBusy(btn, false);
          // Add to Active Downloads immediately
          ensureDownloadRow(key, (res.data.job && res.data.job.filename) || key);
          startPollingDownload(key, btn);
        } else if (res && res.data && res.data.errors) {
          var msg2 = (strings.copy_failed || "Download failed: %s").replace("%s", res.data.errors.join("\n"));
          showToast(msg2, 'error');
          setButtonBusy(btn, false);
        } else {
          showToast(strings.copy_failed || "Download failed.", 'error');
          setButtonBusy(btn, false);
        }
      })
      .fail(function (jqXHR) {
        var msg = strings.copy_failed || "Download failed.";
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.errors) {
          msg = (strings.copy_failed || "Download failed: %s").replace("%s", jqXHR.responseJSON.data.errors.join("\n"));
        }
        showToast(msg, 'error');
        setButtonBusy(btn, false);
      });
  });

  $(document).on("click", "#ai1wm-s3-list tbody tr[data-type=object] .ai1wm-s3-cancel", function () {
    if (!cancelUrl) return;
    var btn = $(this);
    var row = btn.closest("tr");
    var key = row.data("key") || btn.data("key") || "";
    if (!key) return;
    setButtonBusy(btn, true);
    $.ajax({
      url: cancelUrl,
      type: "POST",
      dataType: "json",
      data: { secret_key: secretKey, key: key },
    })
      .done(function (res) {
        if (res && res.success) {
          showToast(strings.cancelled || "Cancelled.", 'info');
        } else if (res && res.data && res.data.errors) {
          var msg = res.data.errors.join("\n");
          showToast(msg, 'error');
        } else {
          showToast(strings.copy_failed || "Operation failed.", 'error');
        }
      })
      .always(function () {
        setButtonBusy(btn, false);
        stopPollingDownload(key);
        btn.text(strings.copy_here || "Copy to Backups").removeClass("ai1wm-s3-cancel").addClass("ai1wm-s3-copy");
      });
  });

  function startPollingDownload(key, btn) {
    if (!statusUrl) return;
    stopPollingDownload(key);
    var id = setInterval(function () {
      $.ajax({ url: statusUrl, type: "GET", dataType: "json", data: { key: key } })
        .done(function (res) {
          if (res && res.success && res.data && res.data.job) {
            var job = res.data.job;
            var row = ensureDownloadRow(key, job.filename || key);
            if (job.state === 'in_progress') {
              row.find('.ai1wm-s3-prog-text').text(formatProgress(job.bytes_done || 0, job.bytes_total || 0));
              row.find('.ai1wm-s3-state').text(formatState(job.state) || job.state);
              var bar = row.find('.ai1wm-progress');
              var barInner = row.find('.ai1wm-progress__bar');
              if (job.bytes_total && job.bytes_total > 0) {
                var pct = Math.max(0, Math.min(100, Math.round((job.bytes_done || 0) * 100 / job.bytes_total)));
                bar.removeClass('ai1wm-progress--indeterminate');
                barInner.css('width', pct + '%');
              } else {
                bar.addClass('ai1wm-progress--indeterminate');
              }
            } else if (job.state === 'success') {
              var name = job.filename || key;
              showToast((strings.copy_success || "Downloaded: %s").replace("%s", name), 'success');
              stopPollingDownload(key);
              if (strings.refreshing) { showToast(strings.refreshing, 'info'); }
              refreshBackupsTablePartial();
              row.remove();
            } else if (job.state === 'failed') {
              showToast((strings.copy_failed || "Download failed: %s").replace("%s", job.message || ""), 'error');
              stopPollingDownload(key);
              if (btn && btn.length) {
                btn.text(strings.copy_here || "Copy to Backups").removeClass("ai1wm-s3-cancel").addClass("ai1wm-s3-copy");
              }
              row.find('.ai1wm-s3-state').text(formatState(job.state) || job.state);
              row.find('.ai1wm-s3-prog-text').text('—');
            } else if (job.state === 'cancelled') {
              showToast(strings.cancelled || "Cancelled.", 'info');
              stopPollingDownload(key);
              if (btn && btn.length) {
                btn.text(strings.copy_here || "Copy to Backups").removeClass("ai1wm-s3-cancel").addClass("ai1wm-s3-copy");
              }
              row.remove();
            }
          }
        });
    }, 1500);
    activeDownloads[key] = id;
  }

  // Upload progress bar (indeterminate) for Copy to S3
  function ensureUploadProgress(statusElement) {
    if (!statusElement || !statusElement.length) return;
    var container = statusElement.closest('.ai1wm-backup-actions');
    if (!container.length) return;
    var prog = container.find('.ai1wm-upload-progress');
    if (!prog.length) {
      prog = $('<div class="ai1wm-upload-progress"></div>');
      var bar = $('<div class="ai1wm-progress ai1wm-progress--indeterminate"><div class="ai1wm-progress__bar"></div></div>');
      var label = $('<div class="ai1wm-upload-label"></div>').text(strings.uploading_label || 'Uploading…');
      prog.append(bar).append(label);
      container.append(prog);
    }
    prog.show();
  }

  function hideUploadProgress(statusElement) {
    if (!statusElement || !statusElement.length) return;
    var container = statusElement.closest('.ai1wm-backup-actions');
    container.find('.ai1wm-upload-progress').remove();
  }

  function stopPollingDownload(key) {
    if (activeDownloads[key]) {
      clearInterval(activeDownloads[key]);
      delete activeDownloads[key];
    }
  }

  function refreshBackupsTablePartial() {
    if (!backupsListUrl) { window.location.reload(); return; }
    $.ajax({ url: backupsListUrl, type: "GET", dataType: "json" })
      .done(function(res){
        if (res && res.success && res.data && typeof res.data.html === 'string') {
          var $table = $("table.ai1wm-backups:not(.ai1wm-backups-logs)");
          if (!$table.length) {
            // Build skeleton table
            var skeleton = ''+
              '<table class="ai1wm-backups">'+
              '<thead><tr>'+
              '<th class="ai1wm-column-name">Name</th>'+
              '<th class="ai1wm-column-date">Date</th>'+
              '<th class="ai1wm-column-size">Size</th>'+
              '<th class="ai1wm-column-actions"></th>'+
              '</tr></thead><tbody></tbody></table>';
            var header = $("#ai1wm-backups-form h3");
            if (!header.length) {
              $("#ai1wm-backups-form").prepend('<h3>Backups</h3>');
            }
            $("#ai1wm-backups-form").find('.ai1wm-backups-empty').remove();
            $("#ai1wm-backups-form").append(skeleton);
            $table = $("table.ai1wm-backups:not(.ai1wm-backups-logs)");
          }
          var $tbody = $table.find('tbody');
          $tbody.html(res.data.html);
          // Mark dynamic links for delegated handlers
          $tbody.find('.ai1wm-backup-actions a').attr('data-dyn', '1');
        } else {
          window.location.reload();
        }
      })
      .fail(function(){ window.location.reload(); });
  }

  // Active downloads rendering
  function renderActiveDownloads(initial) {
    var list = (s3Data.downloads && typeof s3Data.downloads === 'object') ? s3Data.downloads : {};
    var keys = Object.keys(list || {});
    var tbody = $("#ai1wm-s3-downloads tbody");
    tbody.empty();
    var has = false;
    keys.forEach(function(k){
      var job = list[k] || {};
      if (!job || !job.state || (job.state !== 'queued' && job.state !== 'in_progress')) {
        return;
      }
      has = true;
      var name = job.filename || k.split('/').pop();
      var prog = formatProgress(job.bytes_done || 0, job.bytes_total || 0);
      var state = formatState(job.state) || job.state;
      var row = $('<tr></tr>').attr('data-key', k);
      row.append('<td>'+ name +'</td>');
      var progCell = $('<td class="ai1wm-s3-prog"></td>');
      progCell.append('<div class="ai1wm-progress"><div class="ai1wm-progress__bar" style="width:0%"></div></div>');
      progCell.append('<div class="ai1wm-s3-prog-text">'+ prog +'</div>');
      row.append(progCell);
      row.append('<td class="ai1wm-s3-state">'+ state +'</td>');
      var btn = $('<button type="button" class="ai1wm-button-gray ai1wm-s3-cancel-job"></button>').text(strings.cancel || 'Cancel').attr('data-key', k);
      row.append($('<td></td>').append(btn));
      tbody.append(row);
      // Start polling for each initial job
      if (initial) {
        startPollingDownload(k, null);
      }
      // Set initial progress visuals
      var bar = row.find('.ai1wm-progress');
      var barInner = row.find('.ai1wm-progress__bar');
      if (job.bytes_total && job.bytes_total > 0) {
        var pct = Math.max(0, Math.min(100, Math.round((job.bytes_done || 0) * 100 / job.bytes_total)));
        barInner.css('width', pct + '%');
      } else {
        bar.addClass('ai1wm-progress--indeterminate');
      }
    });
    if (!has) {
      tbody.append('<tr class="ai1wm-s3-downloads-empty"><td colspan="4">'+ (strings.active_downloads_empty || 'No active downloads.') +'</td></tr>');
    }
  }

  $(document).on('click', '.ai1wm-s3-cancel-job', function(){
    var key = $(this).data('key');
    if (!key || !cancelUrl) return;
    $.ajax({ url: cancelUrl, type: 'POST', dataType: 'json', data: { secret_key: secretKey, key: key } })
      .done(function(){ showBrowseFeedback(strings.cancelled || 'Cancelled.', false); })
      .always(function(){ stopPollingDownload(key); renderActiveDownloads(false); });
  });

  function ensureDownloadRow(key, filename) {
    var tbody = $("#ai1wm-s3-downloads tbody");
    var row = tbody.find('tr[data-key="'+ cssEscape(key) +'"]');
    if (!row.length) {
      tbody.find('.ai1wm-s3-downloads-empty').remove();
      row = $('<tr></tr>').attr('data-key', key);
      row.append('<td>'+ (filename || key.split('/').pop()) +'</td>');
      var progCell = $('<td class="ai1wm-s3-prog"></td>');
      progCell.append('<div class="ai1wm-progress"><div class="ai1wm-progress__bar" style="width:0%"></div></div>');
      progCell.append('<div class="ai1wm-s3-prog-text">'+ formatProgress(0,0) +'</div>');
      row.append(progCell);
      row.append('<td class="ai1wm-s3-state">'+ (stateLabels.queued || 'Queued') +'</td>');
      var btn = $('<button type="button" class="ai1wm-button-gray ai1wm-s3-cancel-job"></button>').text(strings.cancel || 'Cancel').attr('data-key', key);
      row.append($('<td></td>').append(btn));
      tbody.append(row);
    }
    return row;
  }
  var MODAL_SELECTOR = "#ai1wmS3LogModal";
  var MODAL_TRIGGER_SELECTOR = '[data-target="#ai1wmS3LogModal"]';
  var lastModalTrigger = null;
  var CONFIG_EXPORT_BUTTON = "#ai1wm-s3-config-export";
  var CONFIG_IMPORT_BUTTON = "#ai1wm-s3-config-import-btn";
  var CONFIG_IMPORT_INPUT = "#ai1wm-s3-config-import-input";
  var CONFIG_FEEDBACK = ".ai1wm-s3-config-feedback";

  $(document).on("click", MODAL_TRIGGER_SELECTOR, function () {
    lastModalTrigger = $(this);
  });

  function cssEscape(value) {
    if (window.CSS && window.CSS.escape) {
      return window.CSS.escape(value);
    }
    return value.replace(/([ #;?%&,.+*~':"!^$\[\]()=>|@\/])/g, "\\$1");
  }

  function getStatusElement(button) {
    return button.closest(".ai1wm-backup-actions").find(".ai1wm-backup-status");
  }

  function capitalize(value) {
    if (!value) {
      return "";
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function formatState(state) {
    if (!state) {
      return "";
    }

    return stateLabels[state] || capitalize(state);
  }

  function formatTimeAgo(timestamp) {
    if (!timestamp) {
      return "";
    }

    var now = Math.floor(Date.now() / 1000);
    var diff = Math.max(0, now - timestamp);

    if (diff < 60) {
      var seconds = Math.max(1, diff);
      if (seconds === 1) {
        return strings.updated.replace("%s", strings.time_second);
      }

      return strings.updated.replace(
        "%s",
        strings.time_seconds.replace("%s", seconds)
      );
    }

    if (diff < 3600) {
      var minutes = Math.max(1, Math.round(diff / 60));
      if (minutes === 1) {
        return strings.updated.replace("%s", strings.time_minute);
      }

      return strings.updated.replace(
        "%s",
        strings.time_minutes.replace("%s", minutes)
      );
    }

    if (diff < 86400) {
      var hours = Math.max(1, Math.round(diff / 3600));
      if (hours === 1) {
        return strings.updated.replace("%s", strings.time_hour);
      }

      return strings.updated.replace(
        "%s",
        strings.time_hours.replace("%s", hours)
      );
    }

    var days = Math.max(1, Math.round(diff / 86400));
    if (days === 1) {
      return strings.updated.replace("%s", strings.time_day);
    }

    return strings.updated.replace("%s", strings.time_days.replace("%s", days));
  }

  function setButtonBusy(button, busy) {
    if (busy) {
      button.attr("disabled", "disabled");
      button.attr("aria-disabled", "true");
    } else {
      button.removeAttr("disabled");
      button.removeAttr("aria-disabled");
    }
  }

  function showStatus(statusElement, message, state) {
    if (!statusElement || !statusElement.length) {
      return;
    }

    statusElement.attr("data-state", state || "");

    if (message) {
      statusElement.attr("data-message", message);
    } else {
      statusElement.removeAttr("data-message");
    }

    statusElement.text("");
    statusElement.addClass("ai1wm-hide").hide();
  }

  function showError(statusElement, message) {
    showStatus(statusElement, strings.failed_prefix + " " + message, "failed");
  }

  function collectMissingFields() {
    var form = $(".ai1wm-s3-form");
    var missing = [];

    form.find("[data-s3-required]").each(function () {
      var field = $(this);
      if (!$.trim(field.val())) {
        missing.push({
          label: field.data("label") || field.attr("name"),
          element: field,
        });
      }
    });

    return missing;
  }

  function updateStatusAttributes(target, status) {
    if (!target || !target.length || !status) {
      return;
    }

    target.attr("data-state", status.state || "");
    target.attr("data-remote", status.remote_key || "");
    target.attr("data-updated", status.updated_at || "");
    target.attr("data-filename", status.filename || status.archive || "");
    target.attr("data-log", JSON.stringify(status));
    if (status.remote_url) {
      target.attr("data-remote-url", status.remote_url);
    } else {
      target.removeAttr("data-remote-url");
    }
    if (status.message) {
      target.attr("data-message", status.message);
    } else {
      target.removeAttr("data-message");
    }
  }

  function updateLogButton(button, status) {
    if (!button || !button.length || !status) {
      return;
    }

    button.attr("data-log", JSON.stringify(status));
    button.attr("data-filename", status.filename || status.archive || "");
    button.attr("data-archive", status.archive || button.data("archive") || "");
    button.attr("data-state", status.state || "");
    if (status.remote_url) {
      button.attr("data-remote-url", status.remote_url);
    } else {
      button.removeAttr("data-remote-url");
    }
    button.attr("data-type", "log");
    button.attr("data-toggle", "modal");
    button.attr("data-target", MODAL_SELECTOR);
    button.attr("title", strings.view_log);
  }

  function ensureActivityTable() {
    var container = $("#ai1wm-backups-s3-activity");
    var table = container.find(".ai1wm-backups-logs");

    if (!table.length) {
      var head =
        "<thead><tr>" +
        "<th>" +
        strings.col_backup +
        "</th>" +
        "<th>" +
        strings.col_destination +
        "</th>" +
        "<th>" +
        strings.col_status +
        "</th>" +
        "<th>" +
        strings.col_updated +
        "</th>" +
        "<th>" +
        strings.col_logs +
        "</th>" +
        "</tr></thead>";

      container.find(".ai1wm-backups-logs-empty").remove();
      container.append(
        '<table class="ai1wm-backups ai1wm-backups-logs">' +
          head +
          "<tbody></tbody></table>"
      );
      table = container.find(".ai1wm-backups-logs");
    }

    return table.find("tbody");
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
    var updatedText = status.updated_at ? formatTimeAgo(status.updated_at) : "";
    var destination = status.remote_key || "";
    var stateText = formatState(status.state);
    var payload = JSON.stringify(status);

    if (!row.length) {
      row = $("<tr></tr>").attr("data-archive", archive);
      row.append('<td class="ai1wm-log-name"></td>');
      row.append('<td class="ai1wm-log-destination"></td>');
      row.append('<td class="ai1wm-log-state"></td>');
      row.append('<td class="ai1wm-log-updated"></td>');
      row.append(
        '<td class="ai1wm-log-actions"><a href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="log" title="' +
          strings.view_log +
          '"><i class="ai1wm-icon-notification"></i><span>' +
          strings.view_log +
          "</span></a></td>"
      );
      tableBody.prepend(row);
    }

    row.attr("data-log", payload);
    if (status.remote_url) {
      row.attr("data-remote-url", status.remote_url);
    } else {
      row.removeAttr("data-remote-url");
    }
    row
      .find(".ai1wm-log-name")
      .text(status.filename || status.archive || archive);
    row.find(".ai1wm-log-destination").text(destination);
    row.find(".ai1wm-log-state").text(stateText);
    row.find(".ai1wm-log-updated").text(updatedText || "—");
    var logButton = row.find(".ai1wm-backup-log-button");
    logButton
      .attr("data-archive", archive)
      .attr("data-filename", status.filename || status.archive || archive)
      .attr("data-log", payload)
      .attr("title", strings.view_log)
      .attr("data-state", status.state || "")
      .attr("data-type", "log")
      .attr("data-toggle", "modal")
      .attr("data-target", MODAL_SELECTOR);
    if (status.remote_url) {
      logButton.attr("data-remote-url", status.remote_url);
    } else {
      logButton.removeAttr("data-remote-url");
    }
  }

  function populateInitialActivity() {
    Object.keys(activity).forEach(function (archive) {
      updateActivityRow(archive, activity[archive]);
    });
  }

  function parseLogData(element) {
    // Try to read pre-baked JSON first (may be HTML-escaped by server).
    var raw = element.attr("data-log");
    function decodeHtmlEntities(str) {
      if (!str) return str;
      try {
        return $("<textarea/>").html(str).text();
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
    var archive = element.data("archive");
    if (!archive) {
      var row = element.closest("[data-archive]");
      archive = row.length ? row.data("archive") : null;
    }

    // Try nearest status element for context
    var statusEl = element.closest("td, tr").find(".ai1wm-backup-status");
    var inlineRemoteUrl = element.data("remoteUrl");
    var status = null;

    if (archive && typeof activity === "object" && activity[archive]) {
      status = $.extend({}, activity[archive]);
    }

    if (!status && statusEl.length) {
      status = {
        archive: archive || statusEl.data("archive") || "",
        filename: statusEl.data("filename") || element.data("filename") || "",
        remote_key: statusEl.attr("data-remote") || "",
        state: statusEl.attr("data-state") || "",
        updated_at: parseInt(statusEl.attr("data-updated") || "0", 10) || 0,
        message: statusEl.attr("data-message") || "",
        remote_url:
          statusEl.attr("data-remote-url") || inlineRemoteUrl || "",
      };
    }

    if (!status && archive) {
      // Compose minimal payload
      status = {
        archive: archive,
        filename: element.data("filename") || archive,
        remote_key: "",
        state: "",
        updated_at: 0,
        message: "",
        remote_url: inlineRemoteUrl || "",
      };
    }

    if (status && !status.remote_url && inlineRemoteUrl) {
      status.remote_url = inlineRemoteUrl;
    }

    return status;
  }

  function initSecretToggle() {
    var wrappers = $(".ai1wm-secret-input");
    if (!wrappers.length) {
      return;
    }

    wrappers.each(function () {
      var wrapper = $(this);
      var input = wrapper.find('input[type="password"], input[type="text"]');
      var toggle = wrapper.find(".ai1wm-secret-toggle");

      if (!input.length || !toggle.length) {
        return;
      }

      var icon = toggle.find(".dashicons");

      function setVisibility(isVisible) {
        if (isVisible) {
          input.attr("type", "text");
          wrapper.attr("data-visible", "true");
          toggle.attr(
            "aria-label",
            strings.hide_secret || "Hide secret access key"
          );
          icon.removeClass("dashicons-visibility").addClass("dashicons-hidden");
          toggle.attr("data-visible", "true");
        } else {
          input.attr("type", "password");
          wrapper.attr("data-visible", "false");
          toggle.attr(
            "aria-label",
            strings.show_secret || "Show secret access key"
          );
          icon.removeClass("dashicons-hidden").addClass("dashicons-visibility");
          toggle.removeAttr("data-visible");
        }
      }

      var initialState = wrapper.attr("data-visible") === "true";
      setVisibility(initialState);

      if (!toggle.data("ai1wmSecretBound")) {
        toggle.data("ai1wmSecretBound", true);
        toggle.on("click", function () {
          var isVisible = wrapper.attr("data-visible") === "true";
          setVisibility(!isVisible);
          input.trigger("focus");
        });
      }
    });
  }

  function gatherConfigFromForm() {
    var form = $(".ai1wm-s3-form");
    return {
      endpoint: $.trim(form.find("#ai1wm-s3-endpoint").val() || ""),
      region: $.trim(form.find("#ai1wm-s3-region").val() || ""),
      bucket: $.trim(form.find("#ai1wm-s3-bucket").val() || ""),
      prefix: $.trim(form.find("#ai1wm-s3-prefix").val() || ""),
      access_key: $.trim(form.find("#ai1wm-s3-access-key").val() || ""),
      secret_key: form.find("#ai1wm-s3-secret-key").val() || "",
      use_path_style: form.find("#ai1wm-s3-use-path-style").is(":checked"),
    };
  }

  function applyConfigToForm(config) {
    if (!config || typeof config !== "object") {
      return false;
    }

    var form = $(".ai1wm-s3-form");
    form.find("#ai1wm-s3-endpoint").val(config.endpoint || "");
    form.find("#ai1wm-s3-region").val(config.region || "");
    form.find("#ai1wm-s3-bucket").val(config.bucket || "");
    form.find("#ai1wm-s3-prefix").val(config.prefix || "");
    form.find("#ai1wm-s3-access-key").val(config.access_key || "");
    form.find("#ai1wm-s3-secret-key").val(config.secret_key || "");
    form
      .find("#ai1wm-s3-use-path-style")
      .prop("checked", !!config.use_path_style);

    initSecretToggle();
    return true;
  }

  function showConfigFeedback(message, isSuccess) {
    var feedback = $(CONFIG_FEEDBACK);
    if (!feedback.length) {
      return;
    }

    feedback.removeClass("ai1wm-error ai1wm-success");
    feedback.text(message || "");

    if (message) {
      feedback.addClass(isSuccess ? "ai1wm-success" : "ai1wm-error");
    }
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
      var temp = $("<textarea>")
        .css({ position: "absolute", left: "-9999px", top: "-9999px" })
        .appendTo("body")
        .val(text)
        .select();

      try {
        var successful = document.execCommand("copy");
        temp.remove();
        if (successful) {
          resolve();
        } else {
          reject();
        }
      } catch (err) {
        temp.remove();
        reject(err);
      }
    });
  }

  function parseConfigInput(raw) {
    if (!raw || !raw.trim()) {
      throw new Error("empty");
    }

    var data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      throw new Error("invalid");
    }

    if (!data || typeof data !== "object") {
      throw new Error("invalid");
    }

    var required = [
      "endpoint",
      "region",
      "bucket",
      "access_key",
      "secret_key",
    ];

    var missing = required.filter(function (field) {
      return typeof data[field] === "undefined" || data[field] === null;
    });

    if (missing.length) {
      throw new Error("invalid");
    }

    data.prefix = typeof data.prefix === "undefined" ? "" : data.prefix;

    if (typeof data.use_path_style === "string") {
      data.use_path_style =
        data.use_path_style === "true" || data.use_path_style === "1";
    } else {
      data.use_path_style = !!data.use_path_style;
    }

    return data;
  }

  function startUploadStatusPolling(archive, trigger, statusElement) {
    var url = (s3Data.ajax && s3Data.ajax.upload_status_url) || '';
    if (!url || !archive) return;
    var attempts = 0;
    var maxAttempts = 300; // ~10 minutes if 2s interval
    var iv = setInterval(function(){
      $.ajax({ url: url, type: 'GET', dataType: 'json', data: { archive: archive } })
        .done(function(res){
          if (!(res && res.success && res.data && res.data.status)) return;
          var st = res.data.status || {};
          updateStatusAttributes(statusElement, st);
          updateActivityRow(archive, st);
          if (trigger) updateLogButton(trigger, st);
          var state = (st.state || '').toLowerCase();
          if (state === 'success') {
            hideUploadProgress(statusElement);
            showToast(strings.remote_url_text ? (strings.remote_url_text.replace('%s', st.remote_url || '')) : (strings.updated || 'Completed'), 'success');
            clearInterval(iv);
          } else if (state === 'failed') {
            hideUploadProgress(statusElement);
            showToast((strings.failed_prefix || 'Upload failed:') + ' ' + (st.message || ''), 'error');
            clearInterval(iv);
          }
        })
        .always(function(){
          attempts++;
          if (attempts >= maxAttempts) {
            clearInterval(iv);
          }
        });
    }, 2000);
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
      lines.push(strings.destination.replace("%s", data.remote_key));
    }

    if (data.updated_at) {
      lines.push(formatTimeAgo(data.updated_at));
    }

    if (!lines.length) {
      lines.push(strings.no_log);
    }

    return lines.join("\n");
  }

  function extractRemoteUrl(data) {
    if (!data) {
      return "";
    }

    if (data.remote_url) {
      return data.remote_url;
    }

    if (data.remoteUrl) {
      return data.remoteUrl;
    }

    return "";
  }

  function appendRemoteLink(body, data) {
    body.removeAttr("data-remote-url");
    var url = extractRemoteUrl(data);
    if (!url) {
      return;
    }

    body.attr("data-remote-url", url);
    body.append(document.createTextNode("\n"));

    var label = strings.remote_url_text
      ? strings.remote_url_text.replace("%s", url)
      : url;

    var anchor = $("<a></a>")
      .attr({
        href: url,
        target: "_blank",
        rel: "noopener noreferrer",
      })
      .text(label);

    body.append(anchor);
  }

  // Populate Bootstrap modal when opened via data-toggle
  $(document).on("show.bs.modal", MODAL_SELECTOR, function (e) {
  var container = $(this);
  var body = container.find(".ai1wm-backup-log-content");
  var titleEl = container.find(".modal-title");
  var trigger = $(e.relatedTarget || []);

  body.removeAttr("data-remote-url");

    if (
      (!trigger.length || !trigger.is(MODAL_TRIGGER_SELECTOR)) &&
      lastModalTrigger &&
      lastModalTrigger.length
    ) {
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

    var type = (trigger && trigger.data("type")) || "log";

    if (!trigger.length) {
      body.text(strings.no_log);
      titleEl.text(strings.modal_title);
      return;
    }

    if (type === "log") {
      var data = parseLogData(trigger);
      var titleSuffix =
        (data && (data.filename || data.archive)) ||
        trigger.data("filename") ||
        trigger.data("archive") ||
        "";
      var modalTitle = titleSuffix
        ? strings.modal_title_with_name.replace("%s", titleSuffix)
        : strings.modal_title;
      titleEl.text(modalTitle);
      var logMessage = buildLogMessage(data || {});
      body.text(logMessage);
      appendRemoteLink(body, data);
      return;
    }

    if (type === "upload") {
      var archive = trigger.data("archive");
      var filename = trigger.data("filename") || archive;
      var modalTitleUpload = filename
        ? strings.modal_title_with_name.replace("%s", filename)
        : strings.modal_title;
      titleEl.text(modalTitleUpload);

      if (!archive) {
        body.text(strings.generic_error);
        return;
      }

      var statusElement = getStatusElement(trigger);
      var existingStatus = parseLogData(trigger) || {};
      var currentState = (existingStatus.state || trigger.data("state") || "")
        .toString()
        .toLowerCase();
      var busyStates = { queued: true, in_progress: true, pending: true };
      if (busyStates[currentState]) {
        var stateLabel =
          formatState(currentState) ||
          currentState ||
          strings.state_labels.pending ||
          "";
        body.text(strings.copy_blocked_active.replace("%s", stateLabel));
        appendRemoteLink(body, existingStatus);
        return;
      }

      if (!isConfigured) {
        body.text(strings.saving_required);
        return;
      }

      if (!ajaxUrl) {
        var ajaxMissing = strings.generic_error;
        showError(statusElement, ajaxMissing);
        body.text(ajaxMissing);
        appendRemoteLink(body, existingStatus);
        return;
      }

      var forceReplace = false;
      if (currentState === "success") {
        var remoteName = existingStatus.remote_key || filename || archive;
        var replaceInfo = strings.replace_message.replace("%s", remoteName);
        body.text(replaceInfo);
        appendRemoteLink(body, existingStatus);
        if (
          !window.confirm(strings.confirm_replace.replace("%s", remoteName))
        ) {
          body.text(strings.replace_cancelled);
          appendRemoteLink(body, existingStatus);
          return;
        }
        forceReplace = true;
        body.text(strings.replacing + "\n\n" + replaceInfo);
        appendRemoteLink(body, existingStatus);
      } else {
        body.text(strings.preparing);
      }

      var pendingStatus = $.extend({}, existingStatus, {
        archive: archive,
        filename: filename,
        state: "pending",
      });

      updateLogButton(trigger, pendingStatus);
      updateStatusAttributes(statusElement, pendingStatus);
      updateActivityRow(archive, pendingStatus);
      showStatus(statusElement, "", "pending");
      ensureUploadProgress(statusElement);
      setButtonBusy(trigger, true);

      var requestData = {
        secret_key: secretKey,
        archive: archive,
      };

      if (forceReplace) {
        requestData.force_replace = 1;
      }

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: requestData,
      })
        .done(function (response) {
          if (
            response &&
            response.success &&
            response.data &&
            response.data.status
          ) {
            var status = response.data.status;
            status.archive = archive;
            status.filename = status.filename || filename;
            showStatus(
              statusElement,
              status.message || status.state || "",
              status.state || ""
            );
            updateStatusAttributes(statusElement, status);
            updateLogButton(trigger, status);
            updateActivityRow(archive, status);
            body.text(buildLogMessage(status));
            appendRemoteLink(body, status);
            existingStatus = status;
            // Start upload status polling to hide progress when done
            if (s3Data.ajax && s3Data.ajax.upload_status_url) {
              startUploadStatusPolling(archive, trigger, statusElement);
            }
          } else if (response && response.data && response.data.errors) {
            var msg = response.data.errors.join("\n");
            showError(statusElement, msg);
            body.text(strings.failed_prefix + " " + msg);
            appendRemoteLink(body, existingStatus);
            hideUploadProgress(statusElement);
          } else {
            var generic = strings.generic_error;
            showError(statusElement, generic);
            body.text(generic);
            appendRemoteLink(body, existingStatus);
            hideUploadProgress(statusElement);
          }
        })
        .fail(function (jqXHR) {
          var message = strings.generic_error;
          if (
            jqXHR &&
            jqXHR.responseJSON &&
            jqXHR.responseJSON.data &&
            jqXHR.responseJSON.data.errors
          ) {
            message = jqXHR.responseJSON.data.errors.join("\n");
          }
          showError(statusElement, message);
          body.text(strings.failed_prefix + " " + message);
          appendRemoteLink(body, existingStatus);
          hideUploadProgress(statusElement);
        })
        .always(function () {
          setButtonBusy(trigger, false);
        });
    }
  });

  $(function () {
    $(".ai1wm-backup-status").each(function () {
      $(this).text("").addClass("ai1wm-hide").hide();
    });
    initSecretToggle();
    // Initial S3 list
    if (isConfigured && listUrl) {
      updatePathLabel();
      loadList(currentPath, false);
    }
    renderActiveDownloads(true);
  });

  // Delegated handlers for dynamically added backup rows (avoid double firing by using data-dyn)
  $(document).on('click', 'a[data-dyn=1].ai1wm-backup-delete', function(e){
    e.preventDefault();
    var self = $(this);
    if (!window.ai1wm_backups || !ai1wm_backups.ajax || !ai1wm_backups.ajax.url) return;
    if (!window.confirm((window.ai1wm_locale && ai1wm_locale.want_to_delete_this_file) || 'Are you sure you want to delete this file?')) return;
    $.ajax({
      url: ai1wm_backups.ajax.url,
      type: 'POST',
      dataType: 'json',
      data: { 'secret_key': ai1wm_backups.secret_key, 'archive': self.data('archive') },
      dataFilter: function(data){ return (window.Ai1wm && Ai1wm.Util && Ai1wm.Util.json) ? Ai1wm.Util.json(data) : data; }
    }).done(function(resp){
      if (resp && resp.errors && resp.errors.length === 0) {
        var tr = self.closest('tr');
        tr.remove();
        var rows = $('table.ai1wm-backups:not(.ai1wm-backups-logs) tbody tr');
        if (!rows.length) {
          $('table.ai1wm-backups:not(.ai1wm-backups-logs)').addClass('ai1wm-hide');
          $('.ai1wm-backups-empty').removeClass('ai1wm-hide');
        }
      }
    });
  });

  $(document).on('click', 'a[data-dyn=1].ai1wm-backup-restore', function(e){
    e.preventDefault();
    if (!window.Ai1wm || !Ai1wm.Import || !Ai1wm.Util) return;
    var storage = Ai1wm.Util.random ? Ai1wm.Util.random(12) : Math.random().toString(36).slice(2,14);
    var options = (Ai1wm.Util.form ? Ai1wm.Util.form('#ai1wm-backups-form') : []).concat({ name: 'storage', value: storage }).concat({ name: 'archive', value: $(this).data('archive') });
    try {
      var model = new Ai1wm.Import();
      if (model && model.setParams && model.start) {
        model.setParams(options);
        model.start();
      }
    } catch (err) {
      // ignore
    }
  });

  $(document).on('click', '#ai1wm-s3-go', function(){
    var val = $('#ai1wm-s3-prefix-input').val() || '';
    val = val.replace(/^\/+|\/+$/g, '');
    currentPath = val;
    nextToken = '';
    loadList(currentPath, false);
  });

  $(document).on("click", CONFIG_EXPORT_BUTTON, function (event) {
    event.preventDefault();
    var config = gatherConfigFromForm();
    var payload = JSON.stringify(config, null, 2);

    copyToClipboard(payload)
      .then(function () {
        showConfigFeedback(strings.config_export_success, true);
      })
      .catch(function () {
        showConfigFeedback(strings.config_export_error, false);
      });
  });

  $(document).on("click", CONFIG_IMPORT_BUTTON, function (event) {
    event.preventDefault();
    var raw = $(CONFIG_IMPORT_INPUT).val();

    try {
      var config = parseConfigInput(raw);
      applyConfigToForm(config);
      showConfigFeedback(strings.config_import_success, true);
    } catch (err) {
      if (err && err.message === "empty") {
        showConfigFeedback(strings.config_import_error, false);
      } else {
        showConfigFeedback(strings.config_import_invalid, false);
      }
    }
  });

  populateInitialActivity();
})(jQuery);
