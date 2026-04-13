/**
 * AI Blog Automator — admin UI (requires jQuery; safe if localization is late).
 */
(function () {
	'use strict';

	if (typeof window.jQuery === 'undefined') {
		if (window.console && console.error) {
			console.error('AI Blog Automator: jQuery is not loaded; admin buttons will not work. Disable “remove jQuery” optimizations or conflicting plugins.');
		}
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

		function genNonce() {
			return admin.genNonce || '';
		}

		function adminBase() {
			return admin.adminBase || window.location.origin + '/wp-admin/admin.php';
		}

		function postAjax(action, extra) {
			return $.post(ajaxUrl(), $.extend({ action: action, nonce: adminNonce() }, extra || {}));
		}

		function initAibaSettingsTabs() {
			var $nav = $('.wrap.aiba-wrap .aiba-settings-nav');
			if (!$nav.length) {
				return;
			}
			var $form = $nav.next('form');
			if (!$form.length) {
				$form = $('.wrap.aiba-wrap form[action*="options.php"]').first();
			}
			if (!$form.length) {
				return;
			}
			var $panels = $form.find('.aiba-tab-panel');

			function switchTab($tab) {
				var href = $tab.attr('href');
				if (!href || href.charAt(0) !== '#') {
					return;
				}
				var $panel = $form.find(href);
				if (!$panel.length) {
					return;
				}
				$nav.find('a.nav-tab').removeClass('nav-tab-active').attr('aria-selected', 'false');
				$tab.addClass('nav-tab-active').attr('aria-selected', 'true');
				$panels.prop('hidden', true).attr('aria-hidden', 'true');
				$panel.prop('hidden', false).attr('aria-hidden', 'false');
			}

			$nav.on('click', 'a.nav-tab', function (e) {
				e.preventDefault();
				var $t = $(this);
				switchTab($t);
				var href = $t.attr('href');
				if (href && window.history && window.history.replaceState && typeof URL === 'function') {
					var u = new URL(window.location.href);
					u.hash = href;
					window.history.replaceState(null, '', u.toString());
				}
			});

			var hash = window.location.hash || '';
			if (hash.indexOf('#aiba-tab-') === 0) {
				var $match = $nav.find('a.nav-tab').filter(function () {
					return $(this).attr('href') === hash;
				}).first();
				if ($match.length) {
					switchTab($match);
				}
			}
		}

		try {
			initAibaSettingsTabs();
		} catch (e) {
			if (window.console && console.error) {
				console.error('AI Blog Automator: settings tabs init failed', e);
			}
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

		$('#aiba-dash-generate').on('click', function () {
			window.location.href = adminBase() + '?page=aiba-generate';
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
			var ids = $('.aiba-row-cb:checked')
				.map(function () {
					return $(this).val();
				})
				.get();
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

		function showAibaGenerateLiveNotice(message, opts) {
			opts = opts || {};
			var isWarning = !!opts.warning;
			var $mount = $('#aiba-generate-alerts-mount');
			if (!$mount.length || message === undefined || message === null || message === '') {
				return;
			}
			var logsUrl = $mount.data('logs-url') || '';
			var $live = $('<div class="notice aiba-generate-alerts aiba-generate-alerts--live"></div>').addClass(
				isWarning ? 'notice-warning' : 'notice-error'
			);
			var $p = $('<p class="aiba-generate-alerts-title"></p>');
			$p.append($('<strong></strong>').text(opts.title || 'Last attempt'));
			$p.append(document.createTextNode(' — '));
			$p.append($('<span class="aiba-gen-alert-msg"></span>').text(String(message)));
			if (logsUrl) {
				$p.append(document.createTextNode(' '));
				$p.append(
					$('<a></a>')
						.attr('href', String(logsUrl))
						.text('View activity logs')
				);
			}
			$live.append($p);
			$mount.find('.aiba-generate-alerts--live').remove();
			$mount.prepend($live);
		}

		function runAibaGenerate() {
			var $prog = $('#aiba-gen-progress');
			var $res = $('#aiba-gen-result');
			if (!$prog.length) {
				return;
			}
			$prog.prop('hidden', false);
			$res.empty();
			$('#aiba-generate-alerts-mount .aiba-generate-alerts--live').remove();
			$prog.find('.aiba-step').removeClass('aiba-step-done');
			$prog.find('.aiba-step').first().addClass('aiba-step-done');

			$.post(ajaxUrl(), {
				action: 'aiba_generate_post',
				nonce: genNonce(),
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
						var msg = (res && res.data && res.data.message) || 'Generation failed';
						var isRl = res && res.data && res.data.code === 'rate_limit';
						$res.text(msg);
						showAibaGenerateLiveNotice(msg, { warning: isRl });
					}
				})
				.fail(function () {
					var msg = 'Request failed (network or server).';
					$res.text(msg);
					showAibaGenerateLiveNotice(msg, { warning: false });
				});
		}

		$('#aiba-generate-form').on('submit', function (e) {
			e.preventDefault();
			runAibaGenerate();
		});

		$('#aiba_gen_submit').on('click', function () {
			runAibaGenerate();
		});
	});
})();
