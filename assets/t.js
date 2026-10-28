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
	window.addEventListener('pagehide', ping);

	document.addEventListener('click', function (event) {
		var link = event.target.closest && event.target.closest('a[href]');
		if (!link) {
			return;
		}

		var proto = link.protocol;
		if (proto === 'mailto:' || proto === 'tel:') {
			send({ e: 'ev', vk: vk, t: proto === 'mailto:' ? 3 : 4 });
		} else if (proto === 'http:' || proto === 'https:') {
			// Origin and path only, so query strings with tokens never leave the browser.
			var url = link.origin + link.pathname;
			if (link.host !== location.host) {
				send({ e: 'ev', vk: vk, t: 1, v: url });
			} else if (/\.(pdf|zip|docx?|xlsx?|pptx?|csv|rar|7z|dmg|exe|mp3|mp4)$/i.test(link.pathname)) {
				send({ e: 'ev', vk: vk, t: 2, v: url });
			}
		}
	});

	window.snnA = {
		track: function (name) {
			send({ e: 'ev', vk: vk, t: 5, v: String(name) });
		}
	};

	send({ e: 'pv', vk: vk, pg: cfg.pg, r: document.referrer, q: location.search });
})();
