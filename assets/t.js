/*!
 * SNN Analytics tracker. No cookies and no storage, except one flag that remembers
 * the REST fallback for a day when the PHP endpoint is blocked.
 */
(function () {
	'use strict';

	var cfg = window.snnAConfig;
	if (!cfg || navigator.webdriver) {
		return;
	}
	if (cfg.dnt && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl)) {
		return;
	}

	// One random id per page load. Pings and events use it to find their view.
	var bytes = new Uint8Array(8);
	crypto.getRandomValues(bytes);
	var vk = '';
	for (var i = 0; i < 8; i++) {
		vk += ('0' + bytes[i].toString(16)).slice(-2);
	}

	var endpoint = cfg.u;
	try {
		if (Date.now() - Number(localStorage.getItem('snnAFallback')) < 864e5) {
			endpoint = cfg.f;
		}
	} catch (e) {}

	function post(url, body) {
		return fetch(url, {
			method: 'POST',
			body: JSON.stringify(body),
			keepalive: true,
			headers: { 'Content-Type': 'text/plain' }
		});
	}

	function send(body) {
		post(endpoint, body).then(function (res) {
			// Only a refusal (403, 404, 405, 5xx) switches to REST. A network error does not.
			if (!res.ok && endpoint === cfg.u) {
				endpoint = cfg.f;
				try {
					localStorage.setItem('snnAFallback', String(Date.now()));
				} catch (e) {}
				post(endpoint, body);
			}
		}, function () {});
	}

	// Active time: counts only while the tab is visible.
	var active = 0;
	var since = document.visibilityState === 'visible' ? Date.now() : 0;

	function ping() {
		if (since) {
			active += Date.now() - since;
			since = 0;
		}
		send({ e: 'ping', vk: vk, d: Math.round(active / 1000) });
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			ping();
		} else {
			since = Date.now();
		}
	});
	window.addEventListener('pagehide', function () {
		ping();
		finishTrail();
	});

	/** Keeps a number inside a range. */
	function clamp(value, low, high) {
		return Math.min(high, Math.max(low, value));
	}

	/** The fragment of a link when it is a plain anchor id. Same rule as the server, so tokens never pass. */
	function anchor(hash) {
		return /^#[A-Za-z0-9_\-:]{1,64}$/.test(hash) ? hash : '';
	}

	/** Visible text of a button or link, cleaned and capped. Input values are never read. */
	function label(el) {
		var text = (el.getAttribute('aria-label') || el.textContent || el.value || '').replace(/\s+/g, ' ').trim();
		return text.slice(0, 80) || 'Unlabeled button';
	}

	/** The nearest heading above an element, so a text selection can be placed on the page. */
	function heading(node) {
		var el = node && node.nodeType === 1 ? node : node && node.parentElement;
		for (var step = el; step && step !== document.body; step = step.parentElement) {
			for (var prev = step; prev; prev = prev.previousElementSibling) {
				if (/^H[1-6]$/.test(prev.tagName)) {
					return label(prev);
				}
			}
		}
		return 'top of page';
	}

	document.addEventListener('click', function (event) {
		var target = event.target;

		// Heatmap: where the click happened, counted per cell. Keyboard activations (detail 0) have no position.
		if (cfg.hm && event.detail > 0) {
			var width = document.documentElement.scrollWidth || 1;
			send({ e: 'heat', vk: vk, x: clamp(Math.floor(event.pageX / width * 50), 0, 49), y: clamp(Math.floor(event.pageY / 40), 0, 1999) });
		}
		if (!target || !target.closest) {
			return;
		}

		// Click goals: a configured selector that matches the clicked element (or an ancestor).
		(cfg.goals || []).forEach(function (goal) {
			var hit = null;
			try {
				hit = target.closest(goal.selector);
			} catch (e) {}
			if (hit) {
				send({ e: 'ev', vk: vk, t: 6, v: String(goal.id) });
			}
		});

		var button = target.closest('button, input[type="button"], input[type="submit"], [role="button"]');
		if (button) {
			send({ e: 'ev', vk: vk, t: 8, v: label(button) });
		}

		var link = target.closest('a[href]');
		if (!link) {
			return;
		}

		var proto = link.protocol;
		if (proto === 'mailto:' || proto === 'tel:') {
			send({ e: 'ev', vk: vk, t: proto === 'mailto:' ? 3 : 4 });
		} else if (proto === 'http:' || proto === 'https:') {
			// Origin, path and a plain anchor only, so query strings with tokens never leave the browser.
			var url = link.origin + link.pathname + anchor(link.hash);
			if (link.host !== location.host) {
				send({ e: 'ev', vk: vk, t: 1, v: url });
			} else if (/\.(pdf|zip|docx?|xlsx?|pptx?|csv|rar|7z|dmg|exe|mp3|mp4)$/i.test(link.pathname)) {
				send({ e: 'ev', vk: vk, t: 2, v: url });
			} else {
				send({ e: 'ev', vk: vk, t: 7, v: url });
			}
		}
	});

	// Form submits: the form's name or id, or where it sends the visitor. Field values are never read.
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.getAttribute) {
			return;
		}
		var name = form.getAttribute('aria-label') || form.id || form.getAttribute('name');
		if (!name) {
			try {
				var action = new URL(form.getAttribute('action') || location.href, location.href);
				name = 'Form to ' + action.host + action.pathname;
			} catch (e) {
				name = 'Form';
			}
		}
		send({ e: 'ev', vk: vk, t: 9, v: String(name).replace(/\s+/g, ' ').trim().slice(0, 80) });
	}, true);

	// Text selections: that a selection happened, and under which heading. The selected text is never sent.
	var lastSelection = '';
	var selections = 0;
	document.addEventListener('mouseup', function () {
		window.setTimeout(function () {
			var sel = window.getSelection ? window.getSelection() : null;
			var text = sel ? sel.toString().trim() : '';
			if (text.length < 3 || text === lastSelection || selections >= 20) {
				return;
			}
			lastSelection = text;
			selections++;
			var size = text.length <= 20 ? 'short' : text.length <= 100 ? 'medium' : 'long';
			send({ e: 'ev', vk: vk, t: 10, v: 'Under “' + heading(sel.anchorNode) + '”, ' + size + ' selection' });
		}, 0);
	});

	// Scroll depth: 25, 50, 75 and 100 percent. Each mark is sent once per page view.
	var reached = {};
	var scrollQueued = false;
	function scrolled() {
		scrollQueued = false;
		var room = document.documentElement.scrollHeight - window.innerHeight;
		if (room <= 0) {
			return;
		}
		var pct = Math.min(100, Math.round(100 * window.scrollY / room));
		[25, 50, 75, 100].forEach(function (mark) {
			if (pct >= mark && !reached[mark]) {
				reached[mark] = true;
				send({ e: 'ev', vk: vk, t: 11, v: mark + '%' });
			}
		});
	}
	window.addEventListener('scroll', function () {
		if (!scrollQueued) {
			scrollQueued = true;
			window.requestAnimationFrame(scrolled);
		}
	}, { passive: true });

	// Pointer trails, only when the site turns them on. One trail per view: a sample every 250 ms while the
	// pointer moves (mouse, pen or touch), at most 240 samples. It is sent once: when the tab is hidden,
	// the page is left, or 60 seconds pass.
	var trail = null;
	var trailStart = 0;
	var trailTimer = 0;
	var trailDirty = false;
	var pointer = [0, 0];
	var trailDone = !cfg.cur;

	document.addEventListener('pointermove', function (event) {
		if (trailDone) {
			return;
		}
		if (!trail) {
			trail = [];
			trailStart = Date.now();
			trailTimer = window.setInterval(takeSample, 250);
		}
		var width = document.documentElement.scrollWidth || 1;
		pointer = [clamp(Math.round(event.pageX / width * 10000), 0, 10000), clamp(Math.round(event.pageY), 0, 65535)];
		trailDirty = true;
	}, { passive: true });

	function takeSample() {
		var elapsed = Date.now() - trailStart;
		if (trailDirty) {
			trail.push([pointer[0], pointer[1], clamp(elapsed, 0, 60000)]);
			trailDirty = false;
		}
		if (trail.length >= 240 || elapsed >= 60000) {
			finishTrail();
		}
	}

	/** Sends the samples as hex: 6 bytes each, x, y and t as 16-bit numbers. */
	function finishTrail() {
		if (trailDone) {
			return;
		}
		trailDone = true;
		window.clearInterval(trailTimer);
		if (trail && trail.length) {
			var hex = '';
			trail.forEach(function (p) {
				p.forEach(function (v) {
					hex += ('000' + v.toString(16)).slice(-4);
				});
			});
			send({ e: 'trail', vk: vk, s: hex });
		}
		trail = null;
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			finishTrail();
		}
	});

	window.snnA = {
		track: function (name) {
			send({ e: 'ev', vk: vk, t: 5, v: String(name) });
		}
	};

	send({ e: 'pv', vk: vk, pg: cfg.pg, r: document.referrer, q: location.search });
})();
