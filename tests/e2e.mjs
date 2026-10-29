// Real-browser tests for the SNN Analytics tracker against the Studio "block" site.
// Each scenario gets its own browser context and its own fake visitor IP (CF-Connecting-IP), then the
// database is read back through wp-cli and checked.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, copyFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const SITE = 'http://localhost:8883';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36';
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const WP = 'C:/Users/PC/AppData/Local/studio_app/bin/studio.bat';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const only = process.argv[2];

// Run: node tests/e2e.mjs [name filter]. Needs the plugin copied to the Studio site first.
copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'dump.php'), 'C:/Users/PC/Studio/block/wp-content/snn-test-dump.php');

const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=9333', '--no-first-run', '--user-data-dir=' + mkdtempSync(join(tmpdir(), 'snnch-')), '--user-agent=' + UA, 'about:blank'], { stdio: 'ignore' });
let info;
for (let i = 0; i < 50 && !info; i++) {
	await sleep(200);
	info = await fetch('http://127.0.0.1:9333/json/version').then((r) => r.json()).catch(() => null);
}
const ws = new WebSocket(info.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let seq = 0;
const pending = new Map();
const listeners = [];
ws.onmessage = (m) => {
	const msg = JSON.parse(m.data);
	if (msg.id && pending.has(msg.id)) {
		const { res, rej } = pending.get(msg.id);
		pending.delete(msg.id);
		msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result);
	} else if (msg.method) {
		listeners.forEach((l) => l(msg));
	}
};
const cdp = (method, params = {}, sessionId) => new Promise((res, rej) => {
	const id = ++seq;
	pending.set(id, { res, rej });
	ws.send(JSON.stringify({ id, method, params, sessionId }));
});

let ipCounter = Math.floor(Math.random() * 200) + 1;
const runTag = Math.floor(Math.random() * 250) + 1;

async function tab(opts = {}) {
	const ip = `10.${runTag}.${Math.floor(Math.random() * 250)}.${ipCounter++}`;
	const { browserContextId } = await cdp('Target.createBrowserContext');
	const { targetId } = await cdp('Target.createTarget', { url: 'about:blank', browserContextId });
	const { sessionId: s } = await cdp('Target.attachToTarget', { targetId, flatten: true });
	await cdp('Page.enable', {}, s);
	await cdp('Network.enable', {}, s);
	await cdp('Network.setExtraHTTPHeaders', { headers: { 'CF-Connecting-IP': ip } }, s);
	// A real browser window is smaller than the screen. One that matches it looks headless (see t.js).
	await cdp('Emulation.setDeviceMetricsOverride', { width: 1280, height: 720, deviceScaleFactor: 1, mobile: false, screenWidth: opts.screen ? 1280 : 1920, screenHeight: opts.screen ? 720 : 1080 }, s);
	const waitLoad = () => new Promise((r) => {
		const l = (m) => { if (m.sessionId === s && m.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(l), 1); r(); } };
		listeners.push(l);
	});
	const evalJs = async (expr) => (await cdp('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true }, s)).result.value;
	const t = {
		ip,
		async go(url, referrer) {
			const done = waitLoad();
			await cdp('Page.navigate', referrer ? { url, referrer } : { url }, s);
			await done;
		},
		// A click with no mouse movement first: only pointerdown can open the gate.
		async click(selector, { waitNav = true } = {}) {
			const box = await evalJs(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); if (!el) return null; el.scrollIntoView({block:'center'}); const r = el.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; })()`);
			if (!box) throw new Error('no element ' + selector);
			const done = waitNav ? waitLoad() : null;
			for (const type of ['mousePressed', 'mouseReleased']) {
				await cdp('Input.dispatchMouseEvent', { type, x: box[0], y: box[1], button: 'left', clickCount: 1 }, s);
			}
			if (done) await done;
		},
		async key(k, code) {
			await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: k, code, windowsVirtualKeyCode: { Tab: 9, Enter: 13 }[k] }, s);
			await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode: { Tab: 9, Enter: 13 }[k] }, s);
		},
		async back() {
			const done = waitLoad();
			await evalJs('history.back()');
			await Promise.race([done, sleep(3000)]);
		},
		async move(x = 50, y = 50) {
			await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y }, s);
		},
		// Hides and shows the tab, as switching to another tab and back does.
		async hide(hidden) {
			await evalJs(`Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => '${hidden ? 'hidden' : 'visible'}' }); document.dispatchEvent(new Event('visibilitychange'))`);
		},
		eval: evalJs,
		// Holds every page view answer until release(), so activity stays queued behind it.
		async holdViews() {
			const held = [];
			const l = (m) => {
				if (m.sessionId !== s || m.method !== 'Fetch.requestPaused') return;
				if ((m.params.request.postData || '').includes('"e":"pv"')) held.push(m.params.requestId);
				else cdp('Fetch.continueRequest', { requestId: m.params.requestId }, s);
			};
			listeners.push(l);
			await cdp('Fetch.enable', { patterns: [{ urlPattern: '*t.php*', requestStage: 'Request' }] }, s);
			return async () => {
				listeners.splice(listeners.indexOf(l), 1);
				for (const requestId of held) await cdp('Fetch.continueRequest', { requestId }, s);
				await cdp('Fetch.disable', {}, s);
			};
		},
		// Request bodies the page has sent so far.
		sent: [],
		async close() {
			await cdp('Target.closeTarget', { targetId });
			await cdp('Target.disposeBrowserContext', { browserContextId }).catch(() => {});
		},
	};
	listeners.push((m) => {
		if (m.sessionId === s && m.method === 'Network.requestWillBeSent' && m.params.request.postData) t.sent.push(m.params.request.postData);
	});
	return t;
}

function db(ip) {
	const out = execFileSync(WP, ['wp', 'eval-file', 'wp-content/snn-test-dump.php', ip, Buffer.from(UA).toString('base64')], { cwd: 'C:/Users/PC/Studio/block', encoding: 'utf8', shell: true });
	return JSON.parse(out.slice(out.indexOf('{')));
}

const results = [];
async function scenario(name, fn, check, opts) {
	if (only && !name.includes(only)) return;
	const t = await tab(opts);
	let data, err;
	try {
		await fn(t);
		await sleep(400);
		await t.close();
		await sleep(2500); // keepalive requests and the one-at-a-time Studio server
		data = db(t.ip);
		const problems = check(data.sessions, data);
		err = problems.filter(Boolean);
	} catch (e) {
		err = [String(e.stack || e)];
	}
	results.push({ name, ok: !err.length, err, data });
	console.log((err.length ? 'FAIL ' : 'ok   ') + name + (err.length ? '\n     ' + err.join('\n     ') + '\n     ' + JSON.stringify(data && data.sessions) : ''));
}

const expect = (cond, msg) => (cond ? null : msg);
const paths = (s) => s.pages.map((p) => p.path).join(' > ');
const HOME_LINK = 'a[href="http://localhost:8883/sample-page/"]';

// 1. The case from the question: arrive from Google, click a link half a second later.
await scenario('google -> home, click a link after 0.5s', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/search?q=x');
	await sleep(500);
	await t.click(HOME_LINK);
	await sleep(3500);
}, (S) => [
	expect(S.length === 1, `1 session, got ${S.length}`),
	expect(S[0]?.ref === 'google.com', `referrer google.com, got ${S[0]?.ref}`),
	expect(S[0] && paths(S[0]) === '/ > /sample-page/', `pages / > /sample-page/, got ${S[0] && paths(S[0])}`),
	expect(S[0]?.ev.some((e) => e.type == 7), 'internal link click event stored'),
	expect(S[0]?.is_bounce == 0, 'not a bounce'),
]);

// 2. Even faster: click 100 ms after load.
await scenario('google -> home, click after 0.1s', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(100);
	await t.click(HOME_LINK);
	await sleep(3500);
}, (S) => [
	expect(S.length === 1 && S[0].ref === 'google.com', `1 google session, got ${JSON.stringify(S.map((s) => s.ref))}`),
	expect(S[0] && S[0].pages.length === 2, `2 views, got ${S[0]?.pages.length}`),
]);

// 3. Three pages in quick succession, then stays.
await scenario('three quick pages in a row keep order and source', async (t) => {
	await t.go(SITE + '/', 'http://duckduckgo.com/');
	await sleep(300);
	await t.click(HOME_LINK);
	await sleep(300);
	await t.click('a[href="http://localhost:8883/5-2/"]');
	await sleep(3500);
}, (S) => [
	expect(S.length === 1, `1 session, got ${S.length}`),
	expect(S[0]?.ref === 'duckduckgo.com', `referrer duckduckgo.com, got ${S[0]?.ref}`),
	expect(S[0]?.entry === '/', `entry /, got ${S[0]?.entry}`),
	expect(S[0] && S[0].pages.length === 3, `3 views, got ${S[0] && paths(S[0])}`),
	expect(S[0]?.pages.map((p) => +p.seq).join() === '1,2,3', `seq 1,2,3, got ${S[0]?.pages.map((p) => p.seq)}`),
]);

// 4. A reader who never touches the page.
await scenario('passive reader counted after 3s', async (t) => {
	await t.go(SITE + '/sample-page/', 'http://news.ycombinator.com/item?id=1');
	await sleep(4500);
}, (S) => [
	expect(S.length === 1 && S[0].ref === 'news.ycombinator.com', `1 HN session, got ${JSON.stringify(S.map((s) => s.ref))}`),
]);

// 5. Passive on page 1, then page 2 opened by typing its URL (no click on page 1).
await scenario('passive landing then address-bar navigation', async (t) => {
	await t.go(SITE + '/', 'http://www.bing.com/');
	await sleep(3500);
	await t.go(SITE + '/sample-page/');
	await sleep(3500);
}, (S) => [
	expect(S.length === 1 && S[0].ref === 'bing.com', `1 bing session, got ${JSON.stringify(S.map((s) => s.ref))}`),
	expect(S[0] && S[0].pages.length === 2, `2 views, got ${S[0] && paths(S[0])}`),
]);

// 6. By design: no interaction and gone within 3 seconds is not stored.
await scenario('leave within 1s with no interaction is not counted (by design)', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(1000);
	await t.go('about:blank');
}, (S) => [expect(S.length === 0, `0 sessions, got ${S.length}`)]);

// 7. UTM campaign landing with a quick click.
await scenario('utm campaign landing, quick click keeps campaign', async (t) => {
	await t.go(SITE + '/?utm_source=Newsletter&utm_medium=email&utm_campaign=oct&fbclid=abc');
	await sleep(400);
	await t.click(HOME_LINK);
	await sleep(3500);
}, (S) => [
	expect(S.length === 1, `1 session, got ${S.length}`),
	expect(S[0]?.utm_source === 'newsletter', `utm_source newsletter, got ${S[0]?.utm_source}`),
	expect(S[0] && S[0].pages.length === 2, `2 views, got ${S[0]?.pages.length}`),
]);

// 8. Reload of the landing page repeats the referrer: still one session.
await scenario('reload of landing page stays one session', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(300);
	await t.move();
	await sleep(1500);
	await t.eval('location.reload()');
	await sleep(1500);
	await t.move();
	await sleep(2000);
}, (S) => [
	expect(S.length === 1, `1 session, got ${S.length} ${JSON.stringify(S.map((s) => s.ref))}`),
	expect(S[0] && S[0].pages.length === 2, `2 views, got ${S[0]?.pages.length}`),
]);

// 9. Direct visit, then a click from Twitter in the same half hour: the Twitter source must not be lost.
await scenario('direct, then back from another site starts a new session', async (t) => {
	await t.go(SITE + '/');
	await sleep(300);
	await t.move();
	await sleep(1500);
	await t.go(SITE + '/sample-page/', 'http://t.co/abc');
	await sleep(300);
	await t.move();
	await sleep(2000);
}, (S) => [
	expect(S.length === 2, `2 sessions, got ${S.length}`),
	expect(S[0]?.ref === null && S[1]?.ref === 't.co', `refs direct then t.co, got ${JSON.stringify(S.map((s) => s.ref))}`),
]);

// 10. Back button returns to a page: counted as another view, same session.
await scenario('back button is a new view in the same session', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(300);
	await t.click(HOME_LINK);
	await sleep(400);
	await t.move();
	await sleep(800);
	await t.back();
	await sleep(400);
	await t.move();
	await sleep(2000);
}, (S) => [
	expect(S.length === 1, `1 session, got ${S.length} ${JSON.stringify(S.map((s) => s.ref))}`),
	expect(S[0] && paths(S[0]) === '/ > /sample-page/ > /', `/ > /sample-page/ > /, got ${S[0] && paths(S[0])}`),
]);

// 11. Keyboard-only visitor: Tab to a link, Enter.
await scenario('keyboard-only navigation is counted', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(300);
	await t.eval(`document.querySelector('${HOME_LINK}').focus()`);
	const nav = t.key('Enter', 'Enter');
	await nav;
	await sleep(4000);
}, (S) => [
	expect(S.length === 1 && S[0].pages.length === 2, `1 session with 2 views, got ${JSON.stringify(S.map((s) => s.pages.length))}`),
]);

// 12. 404: no interaction is not counted, a mouse move is.
await scenario('404 with no interaction is not counted', async (t) => {
	await t.go(SITE + '/no-such-page-' + Date.now(), 'http://www.google.com/');
	await sleep(4000);
}, (S) => [expect(S.length === 0, `0 sessions, got ${S.length}`)]);
await scenario('404 with a mouse move is counted', async (t) => {
	await t.go(SITE + '/no-such-page-x', 'http://www.google.com/');
	await sleep(500);
	await t.move();
	await sleep(1500);
}, (S) => [expect(S.length === 1 && S[0].ref === 'google.com', `1 session, got ${S.length}`)]);

// 13. Time on page reaches the database when the tab closes.
await scenario('time on page is saved on close', async (t) => {
	await t.go(SITE + '/sample-page/');
	await sleep(300);
	await t.move();
	await sleep(6000);
}, (S) => [expect(S[0]?.pages[0] && +S[0].pages[0].duration >= 5, `duration >= 5, got ${S[0]?.pages[0]?.duration}`)]);

// 14. Outbound link click right before leaving.
await scenario('outbound link click is stored', async (t) => {
	await t.go(SITE + '/sample-page/');
	await t.eval(`(() => { const a = document.createElement('a'); a.href = 'https://example.org/x?token=secret#top'; a.id = 'out'; a.textContent = 'out'; a.style.cssText = 'position:fixed;top:10px;left:10px;font-size:30px;z-index:99999'; document.body.appendChild(a); })()`);
	await sleep(200);
	await t.click('#out');
	await sleep(1500);
}, (S) => [
	expect(S[0]?.ev.some((e) => e.type == 1 && e.value === 'https://example.org/x#top'), `outbound event without query, got ${JSON.stringify(S[0]?.ev)}`),
]);

// 15. Pagehide while the tab still reports itself visible (Safari): queued events must still go out.
await scenario('events queued at pagehide are sent even if tab says visible', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(200);
	const release = await t.holdViews();
	await t.eval(`(() => { Object.defineProperty(document, 'visibilityState', { get: () => 'visible' }); window.snnA.track('before-leave'); })()`);
	await t.move();
	await sleep(300);
	await t.eval(`window.snnA.track('just-before-leave'); window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: false })); 1`);
	await sleep(500);
	// The page view is still in flight: the queued event must already have left at pagehide.
	t.leftEarly = t.sent.some((b) => b.includes('just-before-leave'));
	await release();
	await sleep(1500);
	if (!t.leftEarly) throw new Error('event stayed queued after pagehide');
}, (S) => [
	expect(S.length === 1, 'view stored after release'),
	// Both events reached the server before their page view: they wait in the parking table and are applied with it.
	expect(['before-leave', 'just-before-leave'].every((v) => S[0]?.ev.some((e) => e.value === v)), `both events stored, got ${JSON.stringify(S[0]?.ev)}`),
	expect(S[0]?.pages[0]?.duration > 0, `time on page from the parked ping, got ${S[0]?.pages[0]?.duration}`),
]);

// 16. Checkout round trip: Google visitor goes to PayPal and comes back. Must stay the Google session.
await scenario('return from PayPal keeps the original session and source', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(300);
	await t.move();
	await sleep(1500);
	await t.go(SITE + '/sample-page/', 'http://www.sandbox.paypal.com/checkoutnow');
	await sleep(300);
	await t.move();
	await sleep(1500);
}, (S) => [
	expect(S.length === 1 && S[0].ref === 'google.com', `1 google session, got ${JSON.stringify(S.map((s) => s.ref))}`),
	expect(S[0]?.pages.length === 2, `2 views, got ${S[0]?.pages.length}`),
]);

// 17. Back to Google, then another result from the same search: same source, same session.
await scenario('second result from the same search engine stays one session', async (t) => {
	await t.go(SITE + '/', 'http://www.google.com/');
	await sleep(300);
	await t.move();
	await sleep(1500);
	await t.go(SITE + '/orbit-app/', 'http://www.google.com/');
	await sleep(300);
	await t.move();
	await sleep(1500);
}, (S) => [expect(S.length === 1 && S[0]?.pages.length === 2, `1 session with 2 views, got ${JSON.stringify(S.map((s) => s.pages.length))}`)]);

// 18. A different campaign link while a session is open starts a new session.
await scenario('second campaign starts a new session', async (t) => {
	await t.go(SITE + '/?utm_source=newsletter&utm_campaign=a');
	await sleep(300);
	await t.move();
	await sleep(1500);
	await t.go(SITE + '/sample-page/?utm_source=facebook&utm_campaign=b');
	await sleep(300);
	await t.move();
	await sleep(1500);
}, (S) => [
	expect(S.length === 2, `2 sessions, got ${S.length}`),
	expect(S.map((s) => s.utm_source).join() === 'newsletter,facebook', `sources newsletter,facebook, got ${S.map((s) => s.utm_source)}`),
]);

// 19. Internal link on a campaign page: the next page has no utm, so it continues the campaign session.
await scenario('pages after a campaign landing stay in its session', async (t) => {
	await t.go(SITE + '/?utm_source=newsletter');
	await sleep(300);
	await t.click(HOME_LINK);
	await sleep(300);
	await t.click('a[href="http://localhost:8883/5-2/"]');
	await sleep(3500);
}, (S) => [expect(S.length === 1 && S[0].pages.length === 3 && S[0].utm_source === 'newsletter', `1 newsletter session with 3 views, got ${JSON.stringify(S.map((s) => [s.utm_source, s.pages.length]))}`)]);

// Trails: the samples of a view, decoded the way Stats::decode_samples does. Points are [x, y, t, click].
function trail(hex) {
	const points = [];
	let base = 0;
	for (const sample of (hex || '').toLowerCase().match(/.{12}/g) || []) {
		const [x, y, t] = [0, 4, 8].map((i) => parseInt(sample.slice(i, i + 4), 16));
		if (x === 0x7fff) base = t * 1000;
		else points.push([x & 0x7fff, y, base + t, x >= 0x8000 ? 1 : 0]);
	}
	return points;
}

await scenario('window the size of the screen is not counted without interaction', async (t) => {
	await t.go(SITE + '/');
	await sleep(4500);
}, (S) => [expect(S.length === 0, `no session, got ${S.length}`)], { screen: true });

await scenario('window the size of the screen is counted after a mouse move', async (t) => {
	await t.go(SITE + '/');
	await sleep(500);
	await t.move();
	await sleep(2000);
}, (S) => [expect(S.length === 1 && S[0].pages.length === 1, `1 session with 1 view, got ${JSON.stringify(S)}`)], { screen: true });

await scenario('trail survives a tab switch and covers more than 60 seconds', async (t) => {
	await t.go(SITE + '/');
	await t.move(100, 100);
	await t.click('body', { waitNav: false });
	for (let i = 0; i < 20; i++) { await t.move(100 + i * 10, 200); await sleep(250); }
	await t.hide(true);
	await sleep(1500);
	await t.hide(false);
	for (let i = 0; i < 280; i++) { await t.move(300 + (i % 50) * 10, 300); await sleep(250); }
	await sleep(1000);
	await t.hide(true);
	await sleep(1500);
}, (S) => {
	const p = trail(S[0]?.pages[0]?.trail);
	const last = p.length ? p[p.length - 1][2] : 0;
	const ordered = p.every((q, i) => !i || q[2] >= p[i - 1][2]);
	return [
		expect(S.length === 1, `1 session, got ${S.length}`),
		expect(p.length > 250, `more than 250 samples, got ${p.length}`),
		expect(last > 65000, `trail longer than 65 s, got ${last} ms`),
		expect(ordered, 'sample times in order'),
		expect(p.some((q) => q[3]), 'click kept in trail'),
		expect(p.every((q) => q[0] <= 10000), 'no marker leaks into points'),
	];
});

ws.close();
chrome.kill();
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed}/${results.length} passed`);
process.exit(failed ? 1 : 0);
