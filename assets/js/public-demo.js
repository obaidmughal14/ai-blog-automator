/**
 * [aiba_product_demo] — checklist persistence + sandbox generate simulation (no API).
 */
(function () {
	'use strict';

	function getL10n() {
		return window.aibaPublicDemo && typeof window.aibaPublicDemo === 'object' ? window.aibaPublicDemo : {};
	}

	function getI18n(key, fallback) {
		var pack = getL10n().i18n;
		if (pack && typeof pack[key] === 'string' && pack[key] !== '') {
			return pack[key];
		}
		return fallback;
	}

	function wait(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	function randBetween(min, max) {
		return min + Math.floor(Math.random() * (max - min + 1));
	}

	function initChecklist(root) {
		var site = root.getAttribute('data-site') || 'default';
		var inst = root.getAttribute('data-instance') || '0';
		var key = 'aiba_demo_checklist_' + site + '_' + inst;
		var boxes = root.querySelectorAll('.aiba-demo-checklist__input');
		if (!boxes.length) {
			return;
		}

		function load() {
			var raw;
			try {
				raw = window.localStorage.getItem(key);
			} catch (e) {
				return;
			}
			if (!raw) {
				return;
			}
			var state;
			try {
				state = JSON.parse(raw);
			} catch (e2) {
				return;
			}
			if (!state || typeof state !== 'object') {
				return;
			}
			boxes.forEach(function (el) {
				var id = el.getAttribute('data-check-id');
				if (id && state[id]) {
					el.checked = true;
				}
			});
		}

		function save() {
			var state = {};
			boxes.forEach(function (el) {
				var id = el.getAttribute('data-check-id');
				if (id) {
					state[id] = !!el.checked;
				}
			});
			try {
				window.localStorage.setItem(key, JSON.stringify(state));
			} catch (e) {
				/* private mode / quota */
			}
		}

		boxes.forEach(function (el) {
			el.addEventListener('change', save);
		});
		load();

		var reset = root.querySelector('.aiba-demo-checklist__reset');
		if (reset) {
			reset.addEventListener('click', function () {
				boxes.forEach(function (el) {
					el.checked = false;
				});
				try {
					window.localStorage.removeItem(key);
				} catch (e) {
					/* ignore */
				}
			});
		}
	}

	function initSandbox(box) {
		var inputs = box.querySelectorAll('.aiba-demo-sandbox__input');
		var topicIn = inputs[0];
		var keyIn = inputs[1];
		var runBtn = box.querySelector('.aiba-demo-sandbox__run');
		var errEl = box.querySelector('.aiba-demo-sandbox__err');
		var progEl = box.querySelector('.aiba-demo-sandbox__progress');
		var statusEl = box.querySelector('.aiba-demo-sandbox__status');
		var stepsEl = box.querySelector('.aiba-demo-sandbox__steps');
		var resultEl = box.querySelector('.aiba-demo-sandbox__result');
		if (!runBtn || !topicIn || !keyIn || !progEl || !statusEl || !stepsEl || !resultEl) {
			return;
		}

		var l10n = getL10n();
		var stepLabels = Array.isArray(l10n.sandboxSteps) && l10n.sandboxSteps.length
			? l10n.sandboxSteps
			: [
					'Preparing job…',
					'Building outline & sections…',
					'Adding SEO, images & internal links…',
					'Publishing to your site (simulated)…',
			  ];
		var genUrl = typeof l10n.generateUrl === 'string' ? l10n.generateUrl : '';
		var busy = false;
		var ranOnce = false;

		function setErr(msg) {
			if (!errEl) {
				return;
			}
			if (msg) {
				errEl.textContent = msg;
				errEl.hidden = false;
			} else {
				errEl.textContent = '';
				errEl.hidden = true;
			}
		}

		function buildStepsOnce() {
			if (stepsEl.getAttribute('data-built') === '1') {
				return;
			}
			stepsEl.textContent = '';
			stepLabels.forEach(function (label) {
				var li = document.createElement('li');
				li.className = 'aiba-demo-sandbox__step';
				li.textContent = label;
				stepsEl.appendChild(li);
			});
			stepsEl.setAttribute('data-built', '1');
		}

		function setRunLabelAfterRun() {
			if (ranOnce) {
				runBtn.textContent = getI18n('sandboxRunAgain', 'Run demo again');
			}
		}

		async function onRun() {
			if (busy) {
				return;
			}
			var topic = (topicIn.value || '').trim();
			var kw = (keyIn.value || '').trim();
			if (!topic || !kw) {
				setErr(getI18n('sandboxErrRequired', 'Enter a topic and a primary keyword to run the demo.'));
				return;
			}
			setErr('');
			busy = true;
			runBtn.disabled = true;
			resultEl.hidden = true;
			resultEl.textContent = '';
			buildStepsOnce();
			progEl.hidden = false;
			statusEl.textContent = getI18n('sandboxRunning', 'Running simulation…');

			var lis = stepsEl.querySelectorAll('.aiba-demo-sandbox__step');
			lis.forEach(function (li) {
				li.classList.remove('is-active', 'is-done');
			});

			for (var i = 0; i < lis.length; i++) {
				lis[i].classList.add('is-active');
				await wait(randBetween(520, 1100));
				lis[i].classList.remove('is-active');
				lis[i].classList.add('is-done');
			}

			await wait(randBetween(350, 700));
			progEl.hidden = true;
			statusEl.textContent = '';

			var wrap = document.createElement('div');
			wrap.className = 'aiba-demo-sandbox__result-inner';

			var h = document.createElement('h4');
			h.className = 'aiba-demo-sandbox__result-title';
			h.textContent = getI18n('sandboxDoneTitle', 'Demo run complete');
			wrap.appendChild(h);

			var p1 = document.createElement('p');
			p1.className = 'aiba-demo-sandbox__result-text';
			p1.textContent = getI18n(
				'sandboxDoneBody',
				'No API was called and no post was created. This page is only showing what the timing feels like after you click Generate in wp-admin.'
			);
			wrap.appendChild(p1);

			var p2 = document.createElement('p');
			p2.className = 'aiba-demo-sandbox__result-meta';
			var strongT = document.createElement('strong');
			strongT.textContent = topic;
			p2.appendChild(document.createTextNode('Topic: '));
			p2.appendChild(strongT);
			p2.appendChild(document.createTextNode(' · Keyword: '));
			var strongK = document.createElement('strong');
			strongK.textContent = kw;
			p2.appendChild(strongK);
			wrap.appendChild(p2);

			if (genUrl) {
				var pa = document.createElement('p');
				pa.className = 'aiba-demo-sandbox__result-actions';
				var a = document.createElement('a');
				a.href = genUrl;
				a.className = 'aiba-demo-sandbox__result-link aiba-public-demo__btn aiba-public-demo__btn--ghost';
				a.textContent = getI18n('sandboxOpenReal', 'Open real Generate now');
				pa.appendChild(a);
				wrap.appendChild(pa);
			}

			resultEl.appendChild(wrap);
			resultEl.hidden = false;
			try {
				resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			} catch (eScroll) {
				resultEl.scrollIntoView(true);
			}
			busy = false;
			runBtn.disabled = false;
			ranOnce = true;
			setRunLabelAfterRun();
		}

		runBtn.addEventListener('click', function () {
			onRun().catch(function () {
				busy = false;
				runBtn.disabled = false;
				progEl.hidden = true;
				setErr(getI18n('sandboxErrGeneric', 'Something went wrong. Please try again.'));
			});
		});
	}

	document.querySelectorAll('.aiba-public-demo[data-aiba-demo]').forEach(function (root) {
		initChecklist(root);
		root.querySelectorAll('.aiba-demo-sandbox[data-aiba-sandbox]').forEach(initSandbox);
	});
})();
