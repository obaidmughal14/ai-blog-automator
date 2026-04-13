/**
 * AI Blog Automator — admin extras (jQuery): sliders and API test.
 * Tabs, generate, queue, and dashboard actions live in admin-boot.js (no jQuery).
 */
(function () {
	'use strict';

	if (typeof window.jQuery === 'undefined') {
		return;
	}

	window.jQuery(function ($) {
		var admin =
			typeof window.aibaAdmin === 'object' && window.aibaAdmin !== null ? window.aibaAdmin : {};

		function ajaxUrl() {
			if (admin.ajaxUrl) {
				return admin.ajaxUrl;
			}
			if (typeof window.ajaxurl === 'string' && window.ajaxurl) {
				return window.ajaxurl;
			}
			return '/wp-admin/admin-ajax.php';
		}

		function adminNonce() {
			return admin.nonce || '';
		}

		function postAjax(action, extra) {
			return $.post(ajaxUrl(), $.extend({ action: action, nonce: adminNonce() }, extra || {}));
		}

		$('#aiba_word_count_slider').on('input', function () {
			$('#aiba_word_count').val($(this).val());
		});
		$('#aiba_word_count').on('change input', function () {
			var v = Math.max(300, Math.min(5000, parseInt($(this).val(), 10) || 300));
			$(this).val(v);
			$('#aiba_word_count_slider').val(v);
		});

		$('#aiba_gen_wc_slider').on('input', function () {
			$('#aiba_gen_wc').val($(this).val());
		});
		$('#aiba_gen_wc').on('change input', function () {
			var v = Math.max(300, Math.min(5000, parseInt($(this).val(), 10) || 300));
			$(this).val(v);
			$('#aiba_gen_wc_slider').val(v);
		});

		$('#aiba-test-apis').on('click', function () {
			var $btn = $(this);
			$('#aiba-test-result').text('…');
			$btn.prop('disabled', true);
			postAjax('aiba_test_apis')
				.done(function (res) {
					if (!res || !res.success) {
						$('#aiba-test-result').text('Error');
						return;
					}
					var d = res.data;
					var parts = [
						'Gemini: ' + (d.gemini ? 'OK' : 'Fail'),
						'OpenAI: ' + (d.openai_skipped ? 'skip' : d.openai ? 'OK' : 'Fail'),
						'Claude: ' + (d.claude_skipped ? 'skip' : d.claude ? 'OK' : 'Fail'),
						'Custom: ' + (d.custom_skipped ? 'skip' : d.custom ? 'OK' : 'Fail'),
						'Pexels: ' + (d.pexels_skipped ? 'skip' : d.pexels ? 'OK' : 'Fail'),
						'Google: ' + (d.google_skipped ? 'skip' : d.google ? 'OK' : 'Fail'),
					];
					$('#aiba-test-result').text(parts.join(' · '));
				})
				.fail(function () {
					$('#aiba-test-result').text('Request failed');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	});
})();
