(function ($) {
	'use strict';

	function postAjax(action, extra) {
		return $.post(aibaAdmin.ajaxUrl, $.extend({ action: action, nonce: aibaAdmin.nonce }, extra || {}));
	}

	$(function () {
		// Settings tabs
		$('.aiba-tabs .nav-tab').on('click', function (e) {
			e.preventDefault();
			var target = $(this).attr('href');
			$('.aiba-tabs .nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			$('.aiba-tab-panel').hide();
			$(target).show();
		});

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

		$('#aiba-dash-generate').on('click', function () {
			window.location.href = aibaAdmin.adminBase + '?page=aiba-generate';
		});

		$('#aiba-dash-trends').on('click', function () {
			var $msg = $('#aiba-dash-msg');
			$msg.text('Fetching trends…');
			postAjax('aiba_fetch_trends_now')
				.done(function (res) {
					$msg.text(res && res.success ? res.data.message : 'Done');
				})
				.fail(function () {
					$msg.text('Request failed');
				});
		});

		$('#aiba-dash-queue').on('click', function () {
			var $msg = $('#aiba-dash-msg');
			$msg.text('Processing queue…');
			postAjax('aiba_process_queue_now')
				.done(function (res) {
					$msg.text(res && res.success ? res.data.message : 'Done');
				})
				.fail(function () {
					$msg.text('Request failed');
				});
		});

		$('#aiba-clear-logs').on('click', function () {
			if (!window.confirm('Clear all log entries?')) {
				return;
			}
			postAjax('aiba_clear_logs').always(function () {
				window.location.reload();
			});
		});

		$('#aiba-bulk-apply').on('click', function () {
			var act = $('#aiba-bulk-action').val();
			if (!act) {
				return;
			}
			var ids = $('.aiba-row-cb:checked').map(function () {
				return $(this).val();
			}).get();
			if (!ids.length) {
				return;
			}
			postAjax('aiba_queue_bulk', { bulk_action: act, ids: ids }).always(function () {
				window.location.reload();
			});
		});

		$('#aiba-cb-all').on('change', function () {
			$('.aiba-row-cb').prop('checked', $(this).prop('checked'));
		});

		$('#aiba_trend_pick').on('change', function () {
			var $o = $(this).find('option:selected');
			var t = $o.val();
			var k = $o.data('kw');
			var s = $o.data('sec');
			if (t) {
				$('#aiba_gen_topic').val(t);
			}
			if (k) {
				$('#aiba_gen_primary').val(k);
			}
			if (s) {
				$('#aiba_gen_secondary').val(s);
			}
		});

		$('#aiba-generate-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $prog = $('#aiba-gen-progress');
			var $res = $('#aiba-gen-result');
			$prog.prop('hidden', false);
			$res.empty();
			$prog.find('.aiba-step').removeClass('aiba-step-done');
			$prog.find('.aiba-step').first().addClass('aiba-step-done');

			$.post(aibaAdmin.ajaxUrl, {
				action: 'aiba_generate_post',
				nonce: aibaAdmin.genNonce,
				topic: $('#aiba_gen_topic').val(),
				primary_keyword: $('#aiba_gen_primary').val(),
				secondary_keywords: $('#aiba_gen_secondary').val(),
				word_count: $('#aiba_gen_wc').val(),
				tone: $('#aiba_gen_tone').val(),
				article_template: $('#aiba_gen_format').val(),
				publish_now: $('#aiba_gen_publish').is(':checked') ? 1 : 0,
				category_ids: $('#aiba_gen_cats').val() || [],
			})
				.done(function (res) {
					$prog.find('.aiba-step').addClass('aiba-step-done');
					if (res && res.success) {
						var d = res.data;
						$res.html(
							'<strong>Done.</strong> SEO score (est.): ' +
								d.seo_score +
								' · <a href="' +
								d.post_url +
								'">Edit post</a>'
						);
					} else {
						$res.text((res && res.data && res.data.message) || 'Generation failed');
					}
				})
				.fail(function () {
					$res.text('Request failed');
				});
		});
	});
})(jQuery);
