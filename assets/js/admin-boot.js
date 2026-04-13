/**
 * AI Blog Automator — critical admin UI without jQuery (tabs, generate, queue).
 * Loads before admin.js so controls work even if jQuery is late or stripped.
 */
(function () {
	'use strict';

	function cfg() {
		return typeof window.aibaAdmin === 'object' && window.aibaAdmin !== null ? window.aibaAdmin : {};
	}

	function ajaxUrl() {
		var a = cfg();
		if (a.ajaxUrl) {
			return a.ajaxUrl;
		}
		if (typeof window.ajaxurl === 'string' && window.ajaxurl) {
			return window.ajaxurl;
		}
		return '/wp-admin/admin-ajax.php';
	}

	function adminNonce() {
		return cfg().nonce || '';
	}

	function genNonceVal() {
		var a = cfg();
		if (a.genNonce) {
			return a.genNonce;
		}
		var el = document.getElementById('aiba-generate-form');
		return el ? el.getAttribute('data-gen-nonce') || '' : '';
	}

	function adminBase() {
		var a = cfg();
		if (a.adminBase) {
			return a.adminBase;
		}
		return window.location.origin + '/wp-admin/admin.php';
	}

	function buildBody(data) {
		var params = new URLSearchParams();
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (v === undefined || v === null) {
				return;
			}
			if (Array.isArray(v)) {
				v.forEach(function (item) {
					params.append(k + '[]', String(item));
				});
			} else {
				params.append(k, String(v));
			}
		});
		return params.toString();
	}

	/**
	 * Parse admin-ajax body as JSON; on failure return a shaped error (PHP fatals / notices often break JSON).
	 *
	 * @param {Response} r
	 * @returns {Promise<object>}
	 */
	function parseAdminAjaxJson(r) {
		return r.text().then(function (text) {
			var trim = (text || '').trim();
			if (!trim) {
				return {
					success: false,
					data: {
						message:
							'Empty response (HTTP ' +
							r.status +
							'). Often a server timeout while calling the LLM—increase PHP max_execution_time or try a shorter word count.',
					},
				};
			}
			try {
				var data = JSON.parse(trim);
				if (typeof data !== 'object' || data === null) {
					return {
						success: false,
						data: { message: 'Unexpected response: ' + String(trim).slice(0, 160) },
					};
				}
				return data;
			} catch (e) {
				var plain = trim
					.replace(/<script[\s\S]*?<\/script>/gi, ' ')
					.replace(/<[^>]+>/g, ' ')
					.replace(/\s+/g, ' ')
					.trim()
					.slice(0, 400);
				return {
					success: false,
					data: {
						message:
							'Server returned non-JSON (HTTP ' +
							r.status +
							'). Usually a PHP error, security rule, or timeout. Snippet: ' +
							(plain || '(no text)'),
					},
				};
			}
		});
	}

	function postAjax(action, extra) {
		var payload = { action: action, nonce: adminNonce() };
		if (extra && typeof extra === 'object') {
			Object.keys(extra).forEach(function (k) {
				payload[k] = extra[k];
			});
		}
		return fetch(ajaxUrl(), {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: buildBody(payload),
			credentials: 'same-origin',
		}).then(function (r) {
			return r.text().then(function (text) {
				var data;
				try {
					data = JSON.parse(text);
				} catch (e) {
					data = { success: false, data: { message: text ? text.slice(0, 200) : 'Bad response' } };
				}
				if (!r.ok) {
					return Promise.reject(data);
				}
				return data;
			});
		});
	}

	function initSettingsTabs() {
		var nav = document.querySelector('.wrap.aiba-wrap .aiba-settings-nav');
		if (!nav) {
			return;
		}
		var card = nav.closest('.aiba-settings-tabs-card');
		var form = card ? card.querySelector('form[action*="options.php"]') : null;
		if (!form) {
			form = document.querySelector('.wrap.aiba-wrap form[action*="options.php"]');
		}
		if (!form) {
			return;
		}
		var tabs = nav.querySelectorAll('a.nav-tab[href^="#aiba-tab-"]');
		var panels = form.querySelectorAll('.aiba-tab-panel');
		if (!tabs.length || !panels.length) {
			return;
		}

		function setActiveByHref(href) {
			tabs.forEach(function (t) {
				var on = t.getAttribute('href') === href;
				t.classList.toggle('nav-tab-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.setAttribute('tabindex', on ? '0' : '-1');
			});
		}

		function switchTo(href) {
			if (!href || href.charAt(0) !== '#') {
				return;
			}
			var panel = form.querySelector(href);
			if (!panel) {
				return;
			}
			panels.forEach(function (p) {
				p.hidden = true;
				p.setAttribute('aria-hidden', 'true');
			});
			panel.hidden = false;
			panel.setAttribute('aria-hidden', 'false');
			setActiveByHref(href);
		}

		function pushHash(href) {
			if (!href || !window.history || !window.history.replaceState || typeof URL !== 'function') {
				return;
			}
			try {
				var u = new URL(window.location.href);
				u.hash = href;
				window.history.replaceState(null, '', u.toString());
			} catch (e) {
				/* ignore */
			}
		}

		nav.addEventListener('click', function (e) {
			var t = e.target.closest('a.nav-tab');
			if (!t || !nav.contains(t)) {
				return;
			}
			e.preventDefault();
			var href = t.getAttribute('href');
			switchTo(href);
			pushHash(href);
		});

		window.addEventListener('hashchange', function () {
			var h = window.location.hash || '';
			if (h.indexOf('#aiba-tab-') === 0) {
				switchTo(h);
			}
		});

		nav.addEventListener('keydown', function (e) {
			var t = e.target.closest('a.nav-tab');
			if (!t || !nav.contains(t)) {
				return;
			}
			var key = e.key;
			if (['ArrowRight', 'ArrowLeft', 'Home', 'End'].indexOf(key) === -1) {
				return;
			}
			e.preventDefault();
			var list = Array.prototype.slice.call(tabs);
			var idx = list.indexOf(t);
			var n = list.length;
			if (n < 1) {
				return;
			}
			if (key === 'ArrowRight') {
				idx = (idx + 1) % n;
			} else if (key === 'ArrowLeft') {
				idx = (idx - 1 + n) % n;
			} else if (key === 'Home') {
				idx = 0;
			} else {
				idx = n - 1;
			}
			var next = list[idx];
			next.focus();
			var href = next.getAttribute('href');
			switchTo(href);
			pushHash(href);
		});

		var hash = window.location.hash || '';
		if (hash.indexOf('#aiba-tab-') === 0) {
			switchTo(hash);
		}
	}

	function pushLiveNotice(message, isWarning) {
		if (typeof window.aibaGenerateAlertPushLive === 'function') {
			window.aibaGenerateAlertPushLive(String(message), !!isWarning);
		}
	}

	function catIdsFromSelect() {
		var sel = document.getElementById('aiba_gen_cats');
		if (!sel || !sel.options) {
			return [];
		}
		var out = [];
		for (var i = 0; i < sel.options.length; i++) {
			if (sel.options[i].selected) {
				out.push(sel.options[i].value);
			}
		}
		return out;
	}

	function initGenerate() {
		var form = document.getElementById('aiba-generate-form');
		var btn = document.getElementById('aiba_gen_submit');
		if (!form && !btn) {
			return;
		}

		function run() {
			if (form && form.checkValidity && !form.checkValidity()) {
				form.reportValidity();
				return;
			}
			var prog = document.getElementById('aiba-gen-progress');
			var resEl = document.getElementById('aiba-gen-result');
			var spin = document.getElementById('aiba-gen-spinner');
			if (spin) {
				spin.removeAttribute('hidden');
			}
			if (prog) {
				prog.removeAttribute('hidden');
				prog.querySelectorAll('.aiba-step').forEach(function (s) {
					s.classList.remove('aiba-step-done');
				});
				var first = prog.querySelector('.aiba-step');
				if (first) {
					first.classList.add('aiba-step-done');
				}
			}
			if (resEl) {
				resEl.textContent = '';
			}

			var topicEl = document.getElementById('aiba_gen_topic');
			var pkEl = document.getElementById('aiba_gen_primary');
			var skEl = document.getElementById('aiba_gen_secondary');
			var wcEl = document.getElementById('aiba_gen_wc');
			var toneEl = document.getElementById('aiba_gen_tone');
			var fmtEl = document.getElementById('aiba_gen_format');
			var pubEl = document.getElementById('aiba_gen_publish');

			var payload = {
				action: 'aiba_generate_post',
				nonce: genNonceVal(),
				topic: topicEl ? topicEl.value : '',
				primary_keyword: pkEl ? pkEl.value : '',
				secondary_keywords: skEl ? skEl.value : '',
				word_count: wcEl ? wcEl.value : '',
				tone: toneEl ? toneEl.value : '',
				article_template: fmtEl ? fmtEl.value : '',
				publish_now: pubEl && pubEl.checked ? 1 : 0,
				category_ids: catIdsFromSelect(),
			};

			fetch(ajaxUrl(), {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: buildBody(payload),
				credentials: 'same-origin',
			})
				.then(parseAdminAjaxJson)
				.then(function (res) {
					if (spin) {
						spin.setAttribute('hidden', 'hidden');
					}
					if (prog) {
						prog.querySelectorAll('.aiba-step').forEach(function (s) {
							s.classList.add('aiba-step-done');
						});
					}
					if (!res || typeof res !== 'object') {
						var bad = 'Unexpected server response.';
						if (resEl) {
							resEl.textContent = bad;
						}
						pushLiveNotice(bad, false);
						return;
					}
					if (res.success && res.data) {
						var d = res.data;
						var editHref = d.post_url || '#';
						if (resEl) {
							resEl.innerHTML =
								'<strong>Done.</strong> SEO score (est.): ' +
								(d.seo_score != null ? d.seo_score : '—') +
								' · <a href="' +
								editHref +
								'">Edit post</a>';
						}
					} else {
						var msg = (res.data && res.data.message) || 'Generation failed';
						var isRl = res.data && res.data.code === 'rate_limit';
						if (resEl) {
							resEl.textContent = msg;
						}
						pushLiveNotice(msg, isRl);
					}
				})
				.catch(function (err) {
					if (spin) {
						spin.setAttribute('hidden', 'hidden');
					}
					if (prog) {
						prog.querySelectorAll('.aiba-step').forEach(function (s) {
							s.classList.add('aiba-step-done');
						});
					}
					var msg =
						err && err.message
							? 'Network error: ' + err.message
							: 'Request failed (network or connection reset). If generation takes long, your host may be timing out—try a lower word count or raise PHP max_execution_time.';
					if (resEl) {
						resEl.textContent = msg;
					}
					pushLiveNotice(msg, false);
				});
		}

		if (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				run();
			});
		}
		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				run();
			});
		}
	}

	function initQueueBulk() {
		var btn = document.getElementById('aiba-bulk-apply');
		if (!btn) {
			return;
		}
		btn.addEventListener('click', function () {
			var sel = document.getElementById('aiba-bulk-action');
			var act = sel ? sel.value : '';
			if (!act) {
				return;
			}
			var ids = [];
			document.querySelectorAll('.aiba-row-cb:checked').forEach(function (c) {
				ids.push(c.value);
			});
			if (!ids.length) {
				return;
			}
			postAjax('aiba_queue_bulk', { bulk_action: act, ids: ids })
				.then(function () {
					window.location.reload();
				})
				.catch(function () {
					window.alert('Bulk action failed. Check your connection and try again.');
				});
		});

		var all = document.getElementById('aiba-cb-all');
		if (all) {
			all.addEventListener('change', function () {
				document.querySelectorAll('.aiba-row-cb').forEach(function (c) {
					c.checked = all.checked;
				});
			});
		}
	}

	function initDashboard() {
		var g = document.getElementById('aiba-dash-generate');
		if (g) {
			g.addEventListener('click', function () {
				window.location.href = adminBase() + '?page=aiba-generate';
			});
		}
		var tr = document.getElementById('aiba-dash-trends');
		if (tr) {
			tr.addEventListener('click', function () {
				var msg = document.getElementById('aiba-dash-msg');
				if (msg) {
					msg.textContent = 'Fetching trends…';
				}
				postAjax('aiba_fetch_trends_now')
					.then(function (res) {
						if (msg) {
							msg.textContent = res && res.success ? res.data.message : 'Done';
						}
					})
					.catch(function () {
						if (msg) {
							msg.textContent = 'Request failed';
						}
					});
			});
		}
		var qu = document.getElementById('aiba-dash-queue');
		if (qu) {
			qu.addEventListener('click', function () {
				var msg = document.getElementById('aiba-dash-msg');
				if (msg) {
					msg.textContent = 'Processing queue…';
				}
				postAjax('aiba_process_queue_now')
					.then(function (res) {
						if (msg) {
							msg.textContent = res && res.success ? res.data.message : 'Done';
						}
					})
					.catch(function () {
						if (msg) {
							msg.textContent = 'Request failed';
						}
					});
			});
		}
		var cl = document.getElementById('aiba-clear-logs');
		if (cl) {
			cl.addEventListener('click', function () {
				if (!window.confirm('Clear all log entries?')) {
					return;
				}
				postAjax('aiba_clear_logs').then(function () {
					window.location.reload();
				});
			});
		}
	}

	function initTrendPick() {
		var pick = document.getElementById('aiba_trend_pick');
		if (!pick) {
			return;
		}
		pick.addEventListener('change', function () {
			var opt = pick.options[pick.selectedIndex];
			if (!opt) {
				return;
			}
			var t = opt.value;
			var k = opt.getAttribute('data-kw') || '';
			var s = opt.getAttribute('data-sec') || '';
			var topic = document.getElementById('aiba_gen_topic');
			var prim = document.getElementById('aiba_gen_primary');
			var sec = document.getElementById('aiba_gen_secondary');
			if (t && topic) {
				topic.value = t;
			}
			if (k && prim) {
				prim.value = k;
			}
			if (s && sec) {
				sec.value = s;
			}
		});
	}

	function onReady() {
		initSettingsTabs();
		initGenerate();
		initQueueBulk();
		initDashboard();
		initTrendPick();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})();
