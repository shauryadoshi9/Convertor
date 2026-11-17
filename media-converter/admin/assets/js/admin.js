/**
 * MediaConverter Pro - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle Job Actions (Delete, Retry, Cancel).
		$('.mc-btn-action').on('click', function(e) {
			e.preventDefault();

			var $btn    = $(this);
			var jobId   = $btn.data('id');
			var action  = $btn.data('action');
			var $row    = $('#mc-job-row-' + jobId);

			if (action === 'delete') {
				if (!confirm('Are you sure you want to delete this conversion job and its physical files?')) {
					return;
				}
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: mediaConverterAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'media_converter_admin_job_action',
					job_id: jobId,
					job_action: action,
					nonce: mediaConverterAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						if (action === 'delete') {
							$row.fadeOut(300, function() { $(this).remove(); });
						} else {
							location.reload();
						}
					} else {
						alert(response.data.message || 'Operation failed.');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					alert('Server network error. Please refresh and try again.');
					$btn.prop('disabled', false);
				}
			});
		});

		// Handle Test Binaries Button.
		$('.mc-test-binaries-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $res = $('#mc-binary-test-result');

			$btn.prop('disabled', true).text('Testing binaries...');
			$res.empty();

			$.ajax({
				url: mediaConverterAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'media_converter_test_binaries',
					nonce: mediaConverterAdmin.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Test Binaries');
					if (response.success) {
						var d = response.data;
						var html = '';

						if (d.ffmpeg.available) {
							html += '<div style="color: #15803d; margin-bottom: 4px;">✔ FFmpeg OK: ' + $('<div>').text(d.ffmpeg.version).html() + '</div>';
						} else {
							html += '<div style="color: #b91c1c; margin-bottom: 4px;">✖ FFmpeg Error: ' + $('<div>').text(d.ffmpeg.error).html() + '</div>';
						}

						if (d.ffprobe.available) {
							html += '<div style="color: #15803d;">✔ FFprobe OK: ' + $('<div>').text(d.ffprobe.version).html() + '</div>';
						} else {
							html += '<div style="color: #b91c1c;">✖ FFprobe Error: ' + $('<div>').text(d.ffprobe.error).html() + '</div>';
						}

						$res.html(html);
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Test Binaries');
					$res.html('<div style="color: #b91c1c;">Network error while testing binaries.</div>');
				}
			});
		});
	});

})(jQuery);
