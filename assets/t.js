/*!
 * SNN Analytics tracker. No cookies and no storage, except one flag that remembers
 * which route works for a day when the direct PHP endpoint is blocked.
 */
(function () {
	'use strict';

	// The config is an attribute on this script's tag (see Frontend::script_tag), so it survives delay plugins.
	function readConfig() {
		var tag = document.querySelector('script[data-snn-config]');
		try {
			return tag ? JSON.parse(tag.getAttribute('data-snn-config')) : null;
		} catch (e) {
			return null;
		}
	}

	var cfg = readConfig();
	if (!cfg || navigator.webdriver) {
		return;
	}
	if (cfg.dnt && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl)) {
		return;
	}

	// Debug mode (Settings): every step is kept in window.snnADebug and printed to the console.
	var trace = window.snnADebug = [];
	function note(step, body, extra) {
		if (!cfg.dbg) {
			return;
		}
		var row = { at: Math.round(performance.now()), step: step, e: body && body.e, t: body && body.t, extra: extra };
		trace.push(row);
		console.debug('[snnA]', row);
	}

	// One random id per page view. Pings and events use it to find their view.
	function viewKey() {
		var bytes = new Uint8Array(8);
		crypto.getRandomValues(bytes);
		var key = '';
		for (var i = 0; i < 8; i++) {
			key += ('0' + bytes[i].toString(16)).slice(-2);
		}
		return key;
	}
	var vk = viewKey();

	// Three routes, tried in order: the plugin's t.php, the REST API, then admin-ajax.php, which hosts
	// that block plugin PHP files and REST for visitors usually still allow.
	// A route on this site's own host, written with www or with another scheme, is moved to the page's
	// origin. A cross-origin call gets no CORS answer, so the tracker could not tell whether it worked.
	function sameOrigin(url) {
		try {
			var u = new URL(url, location.href);
			if (u.host.replace(/^www./, '') === location.host.replace(/^www./, '')) {
				return location.origin + u.pathname + u.search;
			}
		} catch (e) {}
		return url;
	}
	var routes = [cfg.u, cfg.f, cfg.a].map(sameOrigin);
	var route = 0;
	try {
		var saved = JSON.parse(localStorage.getItem('snnARoute') || 'null');
		if (saved && Date.now() - saved.at < 864e5) {
			route = Math.min(saved.n | 0, routes.length - 1);
		}
	} catch (e) {}

	function remember() {
		try {
			localStorage.setItem('snnARoute', JSON.stringify({ n: route, at: Date.now() }));
		} catch (e) {}
	}

	function post(stage, body) {
		var options = { method: 'POST', keepalive: true };
		if (stage === 2) {
			options.body = new URLSearchParams({ action: 'snn_a_track', payload: JSON.stringify(body) });
		} else {
			options.body = JSON.stringify(body);
			options.headers = { 'Content-Type': 'text/plain' };
		}
		return fetch(routes[stage], options);
	}

	function deliver(body, stage) {
		if (stage === undefined) {
			stage = route;
		}
		return post(stage, body).then(function (res) {
			note('sent', body, { route: stage, status: res.status });
			// Every route answers 204. Anything else (403, 404, 5xx, or a redirect to an HTML page) means this
			// route is blocked, so move to the next one and remember it.
			if (res.status !== 204 && stage < routes.length - 1) {
				if (stage >= route) {
					route = stage + 1;
					remember();
				}
				return deliver(body, stage + 1);
			}
		}, function (err) {
			note('send-failed', body, { route: stage, error: String(err) });
			// A network error can be a firewall that drops the connection, or the visitor being offline.
			// Try the next route for this page, but do not remember it: offline is not a blocked route.
			if (stage < routes.length - 1) {
				if (stage >= route) {
					route = stage + 1;
				}
				return deliver(body, stage + 1);
			}
		}).then(null, function () {});
	}

	// Activity waits until the page view is stored, or the server could not find its view and would
	// drop it (a scroll restored on reload, a quick click). A hidden page sends at once: it may not come back.
	var viewStored = false;
	var viewSent = false;
	var leaving = false;
	var waiting = [];

	function flush(key) {
		// The answer of a page view from before a back/forward restore must not open the new view's queue.
		if (key !== vk) {
			return;
		}
		viewStored = true;
		waiting.splice(0).forEach(function (body) {
			deliver(body);
		});
	}

	function send(body) {
		// Nothing leaves before the page view, and the page view waits for a sign of a person (see human()).
		if (!viewSent) {
			note('queued-no-view', body, { waiting: waiting.length + 1, leaving: leaving });
			waiting.push(body);
		} else if (viewStored || leaving || document.visibilityState === 'hidden') {
			flush(vk);
			deliver(body);
		} else {
			note('queued-view-not-stored', body, { waiting: waiting.length + 1 });
			waiting.push(body);
		}
	}

	// A double click or a fast repeat of the same action within 600 ms is one event.
	var recent = {};
	function sendOnce(body) {
		var id = body.t + '|' + (body.v || '');
		var now = Date.now();
		if (recent[id] && now - recent[id] < 600) {
			return;
		}
		recent[id] = now;
		send(body);
	}

	// Active time: counts only while the tab is visible.
	var active = 0;
	var since = document.visibilityState === 'visible' ? Date.now() : 0;

	function ping() {
		if (since) {
			active += Date.now() - since;
			since = document.visibilityState === 'visible' ? Date.now() : 0;
		}
		send({ e: 'ping', vk: vk, d: Math.round(active / 1000) });
	}

	// Heartbeat while the tab is visible, so time on page and the live count survive a tab that dies
	// without a hide event (crash, killed browser, lost power).
	window.setInterval(function () {
		if (document.visibilityState === 'visible') {
			ping();
		}
	}, 30000);

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			ping();
		} else {
			since = Date.now();
		}
	});
	// Safari can still report the tab as visible during pagehide, so leaving sends the queue on its own.
	window.addEventListener('pagehide', function () {
		leaving = true;
		note('pagehide', null, { viewSent: viewSent, viewStored: viewStored, waiting: waiting.length });
		ping();
		sendTrail();
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
					return label(prev).replace(/^Unlabeled button$/, 'untitled heading');
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
				sendOnce({ e: 'ev', vk: vk, t: 6, v: String(goal.id) });
			}
		});

		var button = target.closest('button, input[type="button"], input[type="submit"], [role="button"]');
		if (button) {
			sendOnce({ e: 'ev', vk: vk, t: 8, v: label(button) });
		}

		var link = target.closest('a[href]');
		if (!link) {
			return;
		}
		// A link styled as a button is one click, and the button event already names it.
		var linkIsButton = button && (button === link || link.contains(button) || button.contains(link));

		var proto = link.protocol;
		if (proto === 'mailto:' || proto === 'tel:') {
			send({ e: 'ev', vk: vk, t: proto === 'mailto:' ? 3 : 4 });
		} else if (proto === 'http:' || proto === 'https:') {
			// Origin, path and a plain anchor only, so query strings with tokens never leave the browser.
			var url = link.origin + link.pathname + anchor(link.hash);
			if (link.host !== location.host) {
				sendOnce({ e: 'ev', vk: vk, t: 1, v: url });
			} else if (/\.(pdf|zip|docx?|xlsx?|pptx?|csv|rar|7z|dmg|exe|mp3|mp4)$/i.test(link.pathname)) {
				sendOnce({ e: 'ev', vk: vk, t: 2, v: url });
			} else if (!linkIsButton) {
				sendOnce({ e: 'ev', vk: vk, t: 7, v: url });
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
	// Mouse, touch and keyboard (shift+arrow) selections all end with one of these events.
	var lastSelection = '';
	var selections = 0;
	function selectionEnded() {
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
	}
	['mouseup', 'touchend', 'keyup'].forEach(function (type) {
		document.addEventListener(type, selectionEnded);
	});

	// Scroll depth: 25, 50, 75 and 100 percent. Each mark is sent once per page view.
	var reached = {};
	function mark(pct) {
		[25, 50, 75, 100].forEach(function (step) {
			if (pct >= step && !reached[step]) {
				reached[step] = true;
				send({ e: 'ev', vk: vk, t: 11, v: step + '%' });
			}
		});
	}

	/** Page scroll. A page that fits on one screen has been seen in full, so it counts as 100 percent. */
	function windowScrolled() {
		var room = document.documentElement.scrollHeight - window.innerHeight;
		mark(room <= 0 ? 100 : Math.min(100, Math.round(100 * window.scrollY / room)));
	}

	// Scroll events do not bubble, so one capture listener on the document sees the window and any box
	// inside the page. Only tall boxes count, so a small carousel does not register as the reader's scroll.
	document.addEventListener('scroll', function (event) {
		var el = event.target;
		if (el === document) {
			windowScrolled();
			return;
		}
		var span = el.scrollHeight - el.clientHeight;
		if (el.nodeType === 1 && el.clientHeight >= 200 && span > 0) {
			mark(Math.min(100, Math.round(100 * el.scrollTop / span)));
		}
	}, { capture: true, passive: true });

	// Pointer trails, only when the site turns them on. A sample every 250 ms while the pointer moves
	// (mouse, pen or touch). Samples go out in chunks of at most 60 seconds or 240 samples: when a chunk is
	// full, the tab is hidden or the page is left. A chunk after the first starts with a marker sample
	// (x 0x7fff, which a real x never reaches) holding its number and its start in seconds, so the server
	// appends it to the view's trail. Recording stops after 30 chunks or 30 minutes.
	var trail = null;
	var trailStart = 0;
	var trailBase = 0;
	var trailPart = 0;
	var trailTimer = 0;
	var trailDirty = false;
	var pointer = [0, 0];
	var trailDone = !cfg.cur;

	function trackPointer(event) {
		if (!trail) {
			var now = Date.now();
			trailStart = trailStart || now;
			trailBase = Math.floor((now - trailStart) / 1000) * 1000;
			if (trailBase >= 18e5) {
				trailDone = true;
				return;
			}
			trail = [];
			trailTimer = window.setInterval(takeSample, 250);
		}
		var width = document.documentElement.scrollWidth || 1;
		pointer = [clamp(Math.round(event.pageX / width * 10000), 0, 10000), clamp(Math.round(event.pageY), 0, 65535)];
		trailDirty = true;
	}

	/** Milliseconds since this chunk started. */
	function trailTime() {
		return clamp(Date.now() - trailStart - trailBase, 0, 65535);
	}

	/** The marker takes one place in every chunk after the first. */
	function trailFull() {
		return trail.length >= (trailPart ? 239 : 240) || trailTime() >= 60000;
	}

	document.addEventListener('pointermove', function (event) {
		if (!trailDone) {
			trackPointer(event);
		}
	}, { passive: true });

	// A click is its own sample, marked by the top bit of x (x never goes above 10000), so the replay
	// can show where and when the visitor clicked.
	document.addEventListener('pointerdown', function (event) {
		if (trailDone) {
			return;
		}
		trackPointer(event);
		if (!trail) {
			return;
		}
		trail.push([pointer[0] | 0x8000, pointer[1], trailTime()]);
		trailDirty = false;
		if (trailFull()) {
			sendTrail();
		}
	}, { passive: true });

	function takeSample() {
		if (trailDirty) {
			trail.push([pointer[0], pointer[1], trailTime()]);
			trailDirty = false;
		}
		if (trailFull()) {
			sendTrail();
		}
	}

	/** Sends the chunk as hex: 6 bytes per sample, x, y and t as 16-bit numbers. The next move starts a new chunk. */
	function sendTrail() {
		window.clearInterval(trailTimer);
		if (!trail) {
			return;
		}
		if (trailDirty && !trailFull()) {
			trail.push([pointer[0], pointer[1], trailTime()]);
		}
		trailDirty = false;
		if (trail.length) {
			if (trailPart) {
				trail.unshift([0x7fff, trailPart, trailBase / 1000]);
			}
			var hex = '';
			trail.forEach(function (p) {
				p.forEach(function (v) {
					hex += ('000' + v.toString(16)).slice(-4);
				});
			});
			send({ e: 'trail', vk: vk, s: hex });
			trailPart++;
		}
		trail = null;
		if (trailPart >= 30) {
			trailDone = true;
		}
	}

	// A hidden tab sends what it has and keeps recording when the visitor comes back.
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			sendTrail();
		}
	});

	// Custom event names follow the server's rule: letters, digits, space, dot, colon, dash and underscore,
	// up to 64 characters. Other characters become dashes, so the event is kept rather than silently dropped.
	var nameRule;
	try {
		nameRule = new RegExp('[^\\p{L}\\p{N} _.:\\-]', 'gu');
	} catch (e) {
		nameRule = /[^A-Za-z0-9 _.:\-]/g;
	}

	function eventName(name) {
		var clean = String(name).replace(nameRule, '-').trim();
		var chars = typeof Array.from === 'function' ? Array.from(clean) : clean.split('');
		return chars.slice(0, 64).join('');
	}

	window.snnA = {
		track: function (name) {
			var clean = eventName(name);
			if (clean !== '') {
				send({ e: 'ev', vk: vk, t: 5, v: clean });
			}
		}
	};

	/**
	 * The server reads only the referrer's host and the UTM values, so only those are sent. Long referrers
	 * and click ids (fbclid, gclid) could otherwise push the hit past the server's 4 KB limit and lose it.
	 */
	var pending = null;

	function pageview() {
		var ref = '';
		try {
			ref = document.referrer ? new URL(document.referrer).origin + '/' : '';
		} catch (e) {}
		var utm = location.search.slice(1).split('&').filter(function (pair) {
			return /^utm_(source|medium|campaign|term|content)=/.test(pair);
		}).map(function (pair) {
			return pair.slice(0, 300);
		}).join('&');

		// Screen and window size and the browser language describe the device, not the person, and stay out of the visitor hash.
		var scr = window.screen || {};
		pending = {
			e: 'pv', vk: vk, pg: cfg.pg, r: ref, q: utm ? '?' + utm : '',
			sw: scr.width, sh: scr.height, vw: window.innerWidth, vh: window.innerHeight, l: navigator.language
		};
		arm();
		// Before images load, a page can look shorter than the window and count as fully read.
		if (document.readyState === 'complete') {
			windowScrolled();
		} else {
			window.addEventListener('load', windowScrolled, { once: true });
		}
	}

	/**
	 * Vulnerability scanners and AI crawlers run real browsers: they load a page, never touch it and leave.
	 * So the view is sent on a pointer, touch, wheel or key, or once the tab has been visible for 3 seconds,
	 * so a reader who never touches the page still counts. A 404 needs a real interaction: that is where
	 * scanners probe, and a person on a broken link moves or taps to leave it. So does a headless window
	 * (see headless()). Then the view they landed on
	 * is sent with everything that waited for it. Scroll does not count, because the browser restores it
	 * by itself on reload. A prerendered tab is hidden, so its clock starts only when it is shown.
	 */
	// Headless browsers make the window exactly the size of the screen. A real browser has a toolbar (and a
	// phone a status bar), so only a fullscreen window matches, and a fullscreen reader still counts on the
	// first move or tap. Those windows need an interaction, like a 404.
	var gateTimer = 0;
	function headless() {
		var scr = window.screen || {};
		return scr.width === window.innerWidth && scr.height === window.innerHeight;
	}

	function arm() {
		window.clearTimeout(gateTimer);
		if (!viewSent && cfg.pg && cfg.pg.k !== '404' && !headless() && document.visibilityState === 'visible') {
			gateTimer = window.setTimeout(human, 3000);
		}
	}

	function human(event) {
		// Events made by a script (lazy loaders, theme code, bots) are not a person.
		if (viewSent || !pending || (event && event.isTrusted === false)) {
			return;
		}
		viewSent = true;
		note('view-released', pending, { held: waiting.length });
		window.clearTimeout(gateTimer);
		var key = vk;
		deliver(pending).then(function () {
			flush(key);
		});
		// Activity that waited for the page view. It is held again until the server has stored the view.
		var held = waiting.splice(0);
		held.forEach(send);
	}

	['pointerdown', 'pointermove', 'touchstart', 'wheel', 'keydown'].forEach(function (type) {
		document.addEventListener(type, human, { capture: true, passive: true });
	});
	document.addEventListener('visibilitychange', arm);

	// A page restored from the back/forward cache is a new view, but the script does not run again.
	window.addEventListener('pageshow', function (event) {
		if (event.persisted) {
			vk = viewKey();
			viewStored = false;
			viewSent = false;
			leaving = false;
			waiting = [];
			reached = {};
			recent = {};
			lastSelection = '';
			selections = 0;
			active = 0;
			since = document.visibilityState === 'visible' ? Date.now() : 0;
			trailDone = !cfg.cur;
			trail = null;
			trailStart = 0;
			trailPart = 0;
			trailDirty = false;
			pageview();
		}
	});

	pageview();
})();
