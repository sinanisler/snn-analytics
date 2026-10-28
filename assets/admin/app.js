/*!
 * SNN Analytics admin app. Plain JavaScript, no build step.
 * Data is written with textContent and attributes only, never innerHTML.
 */
(function () {
	'use strict';

	var cfg = window.SNNA || {};
	var root = document.getElementById('snn-a-app');
	var tools = document.getElementById('snn-a-tools');
	var charts = [];
	var teardown = null;
	var SVG_NS = 'http://www.w3.org/2000/svg';
	var PER_PAGE = 25;

	var ACCENT = '#2271b1';
	var COLOR_VISITORS = ACCENT;
	var COLOR_VIEWS = '#8c5cd6';
	var PALETTE = ['#2271b1', '#00a32a', '#dba617', '#8c5cd6', '#d63638', '#72aee6', '#8c8f94'];
	var CHANNEL_TYPES = { 1: 'Direct', 2: 'Search', 3: 'Social', 4: 'Referral', 5: 'Ad', 6: 'Email' };
	var DEVICE_LABELS = { 1: 'Desktop', 2: 'Mobile', 3: 'Tablet' };

	/** "Desktop · Chrome 131 · Windows" for a session from stats/journey. */
	function deviceLine(s) {
		var browser = s.browser ? s.browser + (s.browser_version ? ' ' + s.browser_version : '') : '';
		return [DEVICE_LABELS[s.device] || 'Other', browser, s.os].filter(Boolean).join(' · ');
	}

	/** "Screen 1920×1080 · Window 1536×730 · en-US". Empty for sessions stored before these were recorded. */
	function screenLine(s) {
		return [
			s.screen ? 'Screen ' + s.screen[0] + '×' + s.screen[1] : '',
			s.viewport ? 'Window ' + s.viewport[0] + '×' + s.viewport[1] : '',
			s.language
		].filter(Boolean).join(' · ');
	}
	var CARD_LABELS = {
		chart: 'Traffic over time',
		pages: 'Top pages',
		geo: 'Geographic traffic',
		newreturning: 'New vs. returning',
		devices: 'Devices',
		sources: 'Traffic sources',
		recent: 'Recent views',
		realtime: 'Realtime',
		campaigns: 'Campaigns',
		goals: 'Goals'
	};
	var PRESETS = [
		['today', 'Today'],
		['7d', '7 days'],
		['30d', '30 days'],
		['90d', '90 days'],
		['12m', '12 months'],
		['custom', 'Custom']
	];
	var RANGE_LABELS = { today: 'Today', '7d': 'Last 7 days', '30d': 'Last 30 days', '90d': 'Last 90 days', '12m': 'Last 12 months' };

	if (window.Chart) {
		window.Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
		window.Chart.defaults.font.size = 12;
		window.Chart.defaults.color = '#646970';
		window.Chart.defaults.borderColor = '#f0f0f1';
	}

	// ------------------------------------------------------------ helpers

	function h(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (key) {
			var value = attrs[key];
			if (value === null || value === undefined || value === false) {
				return;
			}
			if (key === 'class') {
				node.className = value;
			} else if (key === 'text') {
				node.textContent = value;
			} else if (key.indexOf('on') === 0) {
				node.addEventListener(key.slice(2), value);
			} else {
				node.setAttribute(key, value === true ? '' : value);
			}
		});
		flatten([children]).forEach(function (child) {
			if (child === null || child === undefined || child === false) {
				return;
			}
			node.appendChild(typeof child === 'string' || typeof child === 'number' ? document.createTextNode(String(child)) : child);
		});
		return node;
	}

	/** Flattens arrays of children at any depth, so h() accepts lists of lists. */
	function flatten(list) {
		return list.reduce(function (out, item) {
			return out.concat(Array.isArray(item) ? flatten(item) : [item]);
		}, []);
	}

	// ----------------------------------------------------------- tooltips

	/** Plain-language explanations, shown next to any table heading, card title or figure with this exact label. */
	var GLOSSARY = {
		'Visitors': 'Different people who visited. Someone who comes back several times is counted once.',
		'New visitors': 'People visiting your site for the first time in this period.',
		'Sessions': 'One visit. A session is a run of page views by the same person and ends after a spell of inactivity.',
		'Views': 'Total page views, including repeat views of the same page.',
		'Bounce rate': 'The share of visits that left after seeing only one page. Lower usually means people stayed and looked around.',
		'Avg. time': 'How long a visit lasts on average.',
		'Active time': 'Time spent with the page open and in use, not sitting in a background tab.',
		'Avg. time on page': 'How long visitors actively spend on this page on average.',
		'Views per session': 'How many pages a visit looks at on average.',
		'Views per visitor': 'How many pages each person looks at on average.',
		'Events': 'Actions tracked besides page views, such as clicks and custom events.',
		'Entrances (landing page)': 'Visits that started on this page.',
		'Clicks recorded': 'Clicks on this page that were tracked for the heatmap.',
		'Exit rate': 'The share of views of this page that were the last page of the visit.',
		'Exits': 'Visits that ended on this page.',
		'Conversions': 'Times visitors completed a goal. Each visit counts once per goal.',
		'Goal': 'An action you want visitors to take.',
		'Type': 'What kind of action the goal watches for.',
		'Rule': 'What a visit has to do to count as a conversion.',
		'Source': 'Where the visit came from, such as a newsletter, Facebook or Google.',
		'Medium': 'The kind of channel, such as email, social or cpc (paid ads).',
		'Campaign': 'The name you gave the link or promotion.',
		'Term': 'The search keyword, for paid search campaigns.',
		'Content': 'Tells apart different links or ads inside one campaign.',
		'Traffic over time': 'Visitors and views for every day in the selected period.',
		'Top pages': 'The pages that were viewed most.',
		'Geographic traffic': 'Where in the world your visitors are.',
		'New vs. returning': 'How many visitors are new and how many came back.',
		'Devices': 'Whether people use desktop, mobile or tablet.',
		'Traffic sources': 'The channels that send you visitors.',
		'Campaigns': 'Tagged links you shared and the visits and conversions they brought.',
		'Goals': 'Actions you want visitors to take, and how often they happened.',
		'Realtime': 'Visitors on your site right now.',
		'Channels': 'Visits grouped by how people found you: direct, search, social, referral, ads or email.',
		'Domains': 'The websites that sent visitors to you.',
		'Browsers': 'The browsers your visitors use.',
		'Operating systems': 'The operating systems your visitors use.',
		'Link builder': 'Adds tracking tags to a web address so visits from that link are counted as a campaign.',
		'Campaign results': 'Visits and conversions for every campaign link, plus visits without tags.'
	};

	/** A small "?" that explains a thing when hovered or focused. */
	function tip(text) {
		return h('span', { class: 'snn-a-tip', tabindex: '0', role: 'img', 'aria-label': text, 'data-tip': text, text: '?' });
	}

	/** The glossary tip for a label, or null. */
	function tipFor(label) {
		return Object.prototype.hasOwnProperty.call(GLOSSARY, label) ? tip(GLOSSARY[label]) : null;
	}

	/**
	 * One floating bubble for every [data-tip] element. It is positioned on screen, not inside
	 * the element, so scroll boxes and table headers never clip it.
	 */
	(function () {
		var bubble = null;
		var current = null;

		function hide() {
			current = null;
			if (bubble && bubble.parentNode) {
				bubble.parentNode.removeChild(bubble);
			}
		}

		function show(target) {
			var text = target.getAttribute('data-tip');
			if (!text || target === current) {
				return;
			}
			current = target;
			bubble = bubble || h('div', { class: 'snn-a-tooltip', role: 'tooltip' });
			bubble.textContent = text;
			document.body.appendChild(bubble);

			var r = target.getBoundingClientRect();
			var w = bubble.offsetWidth;
			var hgt = bubble.offsetHeight;
			var left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
			var top = r.top - hgt - 8;
			if (top < 8) {
				top = r.bottom + 8;
			}
			bubble.style.left = Math.round(left) + 'px';
			bubble.style.top = Math.round(top) + 'px';
		}

		function find(event) {
			return event.target && event.target.closest ? event.target.closest('[data-tip]') : null;
		}

		document.addEventListener('mouseover', function (event) {
			var target = find(event);
			if (target) { show(target); } else { hide(); }
		});
		document.addEventListener('focusin', function (event) {
			var target = find(event);
			if (target) { show(target); }
		});
		document.addEventListener('focusout', hide);
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { hide(); }
		});
		window.addEventListener('scroll', hide, true);
	})();

	function clear(node) {
		while (node.firstChild) {
			node.removeChild(node.firstChild);
		}
	}

	function n(value) {
		return Number(value || 0).toLocaleString();
	}

	function pct(value) {
		return (Math.round(Number(value || 0) * 10) / 10) + '%';
	}

	function duration(seconds) {
		seconds = Math.round(Number(seconds || 0));
		if (seconds < 60) {
			return seconds + 's';
		}
		var m = Math.floor(seconds / 60);
		var s = seconds % 60;
		if (m < 60) {
			return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
		}
		return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
	}

	/**
	 * Compares two numbers for the KPI tiles. invert means a lower value is good (bounce rate).
	 * points means the change is shown in percentage points, not percent.
	 */
	function change(current, previous, opts) {
		var invert = !!(opts && opts.invert);
		var points = !!(opts && opts.points);
		if (!previous && !current) {
			return { text: 'No change', cls: '' };
		}
		if (!previous) {
			return { text: 'New in this period', cls: invert ? 'bad' : 'good' };
		}
		var diff = points
			? Math.round((current - previous) * 10) / 10
			: Math.round((current - previous) / previous * 1000) / 10;
		if (!diff) {
			return { text: 'No change', cls: '' };
		}
		var up = diff > 0;
		return {
			text: (up ? '▲ +' : '▼ −') + Math.abs(diff) + (points ? ' pts' : '%'),
			cls: up !== invert ? 'good' : 'bad'
		};
	}

	/** "42 seconds ago" or "5 minutes ago". Only used for the last hour. */
	function ago(ts) {
		var s = Math.max(0, Math.round(Date.now() / 1000 - ts));
		if (s < 60) { return s + (s === 1 ? ' second ago' : ' seconds ago'); }
		var m = Math.round(s / 60);
		return m + (m === 1 ? ' minute ago' : ' minutes ago');
	}

	function when(ts) {
		return new Date(ts * 1000).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
	}

	/** Visits from the last hour read "5 minutes ago". Older visits show their date and time. */
	function started(ts) {
		return Date.now() / 1000 - ts < 3600 ? ago(ts) : when(ts);
	}

	function clock(ts) {
		return new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	/** Series dates arrive as YYYY-MM-DD in the site timezone. Parse them as local dates so they do not shift. */
	function dayLabel(date) {
		return new Date(date + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
	}

	var regionNames = null;
	try {
		regionNames = new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' });
	} catch (e) {
		regionNames = null;
	}

	function countryName(code) {
		if (!code) {
			return 'Unknown';
		}
		try {
			return (regionNames && regionNames.of(code)) || code;
		} catch (e) {
			return code;
		}
	}

	/** Small flag for a country code, from the SVG set in assets/flags. Nothing is shown for an unknown country. */
	function flag(code) {
		if (!code) {
			return null;
		}
		return h('img', { class: 'snn-a-flag', src: (cfg.flags || '') + code.toLowerCase() + '.svg', alt: '', width: '20', height: '15', loading: 'lazy' });
	}

	function isTagged(c) {
		return !!(c.source || c.medium || c.campaign);
	}

	function campaignName(c) {
		return [c.source, c.medium, c.campaign].filter(Boolean).join(' / ');
	}

	function api(path, params) {
		// Build the URL with the URL API. With plain permalinks the REST base is
		// index.php?rest_route=..., which already has a query string.
		var url = new URL(cfg.rest + path, window.location.href);
		Object.keys(params || {}).forEach(function (key) {
			url.searchParams.set(key, params[key]);
		});
		return fetch(url.toString(), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce }
		}).then(parse);
	}

	function send(path, body) {
		return fetch(cfg.rest + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify(body || {})
		}).then(parse);
	}

	function parse(res) {
		return res.json().then(function (data) {
			if (!res.ok) {
				throw new Error((data && data.message) || ('Request failed (' + res.status + ')'));
			}
			return data;
		});
	}

	function pageLink(slug) {
		return cfg.pageUrl + slug + '&' + new URLSearchParams(currentRange()).toString();
	}

	// -------------------------------------------------------------- range

	function currentRange() {
		var p = new URLSearchParams(window.location.search);
		return {
			range: p.get('range') || '30d',
			from: p.get('from') || '',
			to: p.get('to') || ''
		};
	}

	function rangeLabel(rp) {
		if (rp.range === 'custom') {
			return (rp.from || '?') + ' – ' + (rp.to || '?');
		}
		return RANGE_LABELS[rp.range] || 'Last 30 days';
	}

	function setRange(params) {
		var p = new URLSearchParams(window.location.search);
		p.set('range', params.range);
		if (params.range === 'custom') {
			p.set('from', params.from || '');
			p.set('to', params.to || '');
		} else {
			p.delete('from');
			p.delete('to');
		}
		window.history.replaceState(null, '', '?' + p.toString());
		render();
	}

	function rangeControl() {
		var current = currentRange();
		var hint = h('p', { class: 'snn-a-hint', role: 'status' });
		var from = h('input', { type: 'date', value: current.from, 'aria-label': 'Start date' });
		var to = h('input', { type: 'date', value: current.to, 'aria-label': 'End date' });
		var custom = h('div', { class: 'snn-a-custom', hidden: current.range !== 'custom' }, [
			from,
			h('span', { class: 'snn-a-muted', text: 'to' }),
			to,
			h('button', { type: 'button', class: 'button', onclick: function () {
				if (!from.value || !to.value) {
					hint.textContent = 'Choose both a start and an end date.';
				} else if (from.value > to.value) {
					hint.textContent = 'The start date must be before the end date.';
				} else {
					setRange({ range: 'custom', from: from.value, to: to.value });
				}
			} }, 'Apply')
		]);

		return h('div', { class: 'snn-a-range' }, [
			h('div', { class: 'snn-a-segment', role: 'group', 'aria-label': 'Date range' }, PRESETS.map(function (preset) {
				var active = current.range === preset[0];
				return h('button', {
					type: 'button',
					class: 'snn-a-seg' + (active ? ' is-active' : ''),
					'aria-pressed': active ? 'true' : 'false',
					onclick: function () {
						if (preset[0] === 'custom') {
							custom.hidden = false;
							from.focus();
							return;
						}
						setRange({ range: preset[0] });
					}
				}, preset[1]);
			})),
			custom,
			hint
		]);
	}

	// -------------------------------------------------------- components

	function numCol(label) {
		return [label, true];
	}

	function notice(text, kind) {
		return h('div', { class: 'snn-a-notice is-' + (kind || 'info'), role: kind === 'error' ? 'alert' : 'status' }, text);
	}

	function failed(err, retry) {
		return h('div', { class: 'snn-a-notice is-error', role: 'alert' }, [
			h('span', { text: 'Could not load this data. ' + err.message + ' ' }),
			retry ? h('button', { type: 'button', class: 'button', onclick: retry }, 'Try again') : null
		]);
	}

	function skeleton(lines) {
		var bars = [];
		for (var i = 0; i < (lines || 3); i++) {
			bars.push(h('span', { class: 'snn-a-skel', style: 'width:' + (58 + ((i * 23) % 40)) + '%' }));
		}
		return h('div', { class: 'snn-a-skeleton', 'aria-busy': 'true', 'aria-label': 'Loading' }, bars);
	}

	function empty(text, action) {
		return h('div', { class: 'snn-a-empty' }, [
			h('p', { text: text || 'No data for this range yet.' }),
			action || null
		]);
	}

	/**
	 * A table inside a horizontal scroll box, so wide tables never push the page sideways.
	 * heads: strings, or numCol() for right-aligned numeric columns.
	 * onRow(index, row): optional. Makes rows selectable with the mouse and the keyboard.
	 */
	function table(heads, rows, emptyText, onRow) {
		if (!rows.length) {
			return empty(emptyText);
		}
		var body = rows.map(function (cells, index) {
			var attrs = { 'data-index': index };
			if (onRow) {
				attrs.class = 'is-selectable';
				attrs.tabindex = '0';
				attrs.onclick = function (event) { onRow(index, event.currentTarget); };
				attrs.onkeydown = function (event) {
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						onRow(index, event.currentTarget);
					}
				};
			}
			return h('tr', attrs, cells.map(function (cell, i) {
				var numeric = Array.isArray(heads[i]);
				return h('td', { class: numeric ? 'num' : '' }, cell === null || cell === undefined ? '' : cell);
			}));
		});
		return h('div', { class: 'snn-a-scroll' }, h('table', { class: 'snn-a-table' }, [
			h('thead', {}, h('tr', {}, heads.map(function (head) {
				var numeric = Array.isArray(head);
				var label = numeric ? head[0] : head;
				return h('th', { class: numeric ? 'num' : '', scope: 'col' }, [label, tipFor(label)]);
			}))),
			h('tbody', {}, body)
		]));
	}

	function pager(page, pages, go) {
		if (pages <= 1) {
			return document.createDocumentFragment();
		}
		return h('nav', { class: 'snn-a-pager', 'aria-label': 'Pagination' }, [
			h('button', { type: 'button', class: 'button', disabled: page <= 1 ? true : null, onclick: function () { go(page - 1); } }, '‹ Previous'),
			h('span', { class: 'snn-a-muted', text: 'Page ' + page + ' of ' + pages }),
			h('button', { type: 'button', class: 'button', disabled: page >= pages ? true : null, onclick: function () { go(page + 1); } }, 'Next ›')
		]);
	}

	/** Segmented control for the device filter on the heatmap and the trails. */
	function deviceSegment(current, pick) {
		var options = [[0, 'All devices'], [1, 'Desktop'], [2, 'Mobile'], [3, 'Tablet']];
		return h('div', { class: 'snn-a-segment', role: 'group', 'aria-label': 'Device' }, options.map(function (option) {
			var active = current === option[0];
			return h('button', {
				type: 'button',
				class: 'snn-a-seg' + (active ? ' is-active' : ''),
				'aria-pressed': active ? 'true' : 'false',
				onclick: function () { pick(option[0]); }
			}, option[1]);
		}));
	}

	function card(title, body, opts) {
		opts = opts || {};
		var section = h('section', { class: 'snn-a-card' + (opts.wide ? ' is-wide' : ''), 'aria-label': title }, [
			h('header', { class: 'snn-a-card-head' }, [
				opts.grip ? h('span', { class: 'snn-a-grip', 'aria-hidden': 'true', title: 'Drag to move', text: '⠿' }) : null,
				h('div', { class: 'snn-a-card-title' }, [
					h('h2', {}, [title, opts.tip ? tip(opts.tip) : tipFor(title)]),
					opts.sub ? h('p', { class: 'snn-a-card-sub', text: opts.sub }) : null
				]),
				h('div', { class: 'snn-a-card-actions' }, [
					opts.actions || null,
					opts.link ? h('a', { class: 'snn-a-link', href: opts.link }, opts.linkText || 'View report →') : null
				])
			]),
			h('div', { class: 'snn-a-card-body' }, body)
		]);
		if (opts.id) {
			section.setAttribute('data-card', opts.id);
		}
		return section;
	}

	function kpi(label, value, delta) {
		return h('div', { class: 'snn-a-kpi' }, [
			h('div', { class: 'snn-a-kpi-label' }, [label, tipFor(label)]),
			h('div', { class: 'snn-a-kpi-value', text: value }),
			h('div', { class: 'snn-a-kpi-delta ' + (delta.cls || ''), text: delta.text })
		]);
	}

	function liveCount(count) {
		return h('div', { class: 'snn-a-live' }, [
			h('span', { class: 'snn-a-live-dot', 'aria-hidden': 'true' }),
			h('strong', { text: n(count) }),
			h('span', { text: ' visitor' + (count === 1 ? '' : 's') + ' in the last 30 minutes' })
		]);
	}

	function pageCell(row) {
		return h('div', { class: 'snn-a-page-cell' }, row.title
			? [h('strong', { text: row.title }), h('span', { class: 'snn-a-muted', text: row.path })]
			: [h('strong', { text: row.path })]);
	}

	function chartBox(height) {
		return h('div', { class: 'snn-a-chart', style: 'height:' + (height || 260) + 'px' }, h('canvas', {}));
	}

	function fadeColor(color, alpha) {
		var hex = color.replace('#', '');
		var r = parseInt(hex.slice(0, 2), 16);
		var g = parseInt(hex.slice(2, 4), 16);
		var b = parseInt(hex.slice(4, 6), 16);
		return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
	}

	function lineChart(canvas, labels, sets) {
		var chart = new window.Chart(canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: sets.map(function (set) {
					return {
						label: set.label,
						data: set.data,
						borderColor: set.color,
						backgroundColor: set.fill ? fadeColor(set.color, 0.12) : set.color,
						borderWidth: 2,
						borderDash: set.dash || [],
						fill: !!set.fill,
						tension: 0.3,
						pointRadius: 0,
						pointHoverRadius: 4
					};
				})
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } }
				},
				scales: {
					x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkipPadding: 12 } },
					y: { beginAtZero: true, grid: { color: '#f0f0f1' }, ticks: { precision: 0 } }
				}
			}
		});
		charts.push(chart);
		return chart;
	}

	/** Doughnut with a text legend next to it. Each row shows the value and its share. */
	function breakdown(labels, values, colors) {
		colors = colors || PALETTE;
		var total = values.reduce(function (sum, v) { return sum + Number(v || 0); }, 0);
		if (!total) {
			return empty('No data for this range yet.');
		}
		var canvas = h('canvas', {});
		var chart = new window.Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '68%',
				plugins: { legend: { display: false } }
			}
		});
		charts.push(chart);
		return h('div', { class: 'snn-a-breakdown' }, [
			h('div', { class: 'snn-a-donut' }, canvas),
			h('ul', { class: 'snn-a-legend' }, labels.map(function (label, i) {
				return h('li', {}, [
					h('i', { class: 'snn-a-dot', style: 'background:' + colors[i % colors.length], 'aria-hidden': 'true' }),
					h('span', { class: 'snn-a-legend-label', text: label }),
					h('strong', { text: n(values[i]) }),
					h('span', { class: 'snn-a-muted', text: pct(100 * values[i] / total) })
				]);
			}))
		]);
	}

	// ------------------------------------------------------------- map

	var worldPromise = null;

	function loadWorld() {
		if (!worldPromise) {
			worldPromise = fetch(cfg.world, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
		}
		return worldPromise;
	}

	/** One path per country, shaded by sessions. Used by the dashboard and the Geographic screen. */
	function worldMap(countries, world) {
		var sessionsBy = {};
		var max = 0;
		countries.forEach(function (c) {
			if (c.code) {
				sessionsBy[c.code] = c.sessions;
				max = Math.max(max, c.sessions);
			}
		});

		var svg = document.createElementNS(SVG_NS, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + world.width + ' ' + world.height);
		svg.setAttribute('class', 'snn-a-map');
		svg.setAttribute('role', 'img');
		svg.setAttribute('aria-label', 'World map of sessions by country');

		Object.keys(world.countries).forEach(function (code) {
			var count = sessionsBy[code] || 0;
			var strength = max ? count / max : 0;
			var path = document.createElementNS(SVG_NS, 'path');
			var title = document.createElementNS(SVG_NS, 'title');
			path.setAttribute('d', world.countries[code]);
			path.setAttribute('fill', count ? fadeColor(ACCENT, (0.2 + 0.8 * strength).toFixed(2)) : '#eef0f2');
			path.setAttribute('stroke', '#ffffff');
			path.setAttribute('stroke-width', '0.5');
			title.textContent = countryName(code) + ': ' + n(count) + ' sessions';
			path.appendChild(title);
			svg.appendChild(path);
		});

		return svg;
	}

	function mapLegend() {
		return h('div', { class: 'snn-a-scale' }, [
			h('span', { class: 'snn-a-muted', text: 'Fewer sessions' }),
			h('i', { 'aria-hidden': 'true' }),
			h('span', { class: 'snn-a-muted', text: 'More sessions' })
		]);
	}

	function cityNote() {
		return h('div', { class: 'snn-a-notice is-info', role: 'status' }, [
			h('span', { text: 'City data needs the free city database. ' }),
			h('a', { href: cfg.pageUrl + 'snn-analytics-settings' }, 'Install it in Settings →')
		]);
	}

	// ------------------------------------------------------------- views

	/** Last 30 one-minute slots, oldest first. The API returns only minutes that had views, so the gaps are filled with 0. */
	function minuteSlots(perMinute) {
		var end = Math.floor(Date.now() / 1000 / 60) * 60;
		var byMinute = {};
		(perMinute || []).forEach(function (m) {
			byMinute[m.minute] = m.views;
		});
		var slots = [];
		for (var i = 29; i >= 0; i--) {
			var at = end - i * 60;
			slots.push({ at: at, views: byMinute[at] || 0 });
		}
		return slots;
	}

	/** Column chart of views for the last 30 minutes. Each column is one minute. */
	function minuteBars(perMinute, height) {
		var slots = minuteSlots(perMinute);
		var max = Math.max(1, Math.max.apply(null, slots.map(function (s) { return s.views; })));
		return h('div', { class: 'snn-a-bars-wrap' }, [
			h('div', { class: 'snn-a-bars', style: 'height:' + (height || 120) + 'px', role: 'img', 'aria-label': 'Views per minute, last 30 minutes' }, slots.map(function (s) {
				var share = s.views ? Math.max(4, Math.round(100 * s.views / max)) : 0;
				return h('span', { class: 'snn-a-bar-col', title: clock(s.at) + ': ' + n(s.views) + ' views' }, h('i', { style: 'height:' + share + '%' }));
			})),
			h('div', { class: 'snn-a-bars-axis' }, [h('span', { text: clock(slots[0].at) }), h('span', { text: 'now' })])
		]);
	}

	/** Ranked list with a bar under each label. rows: [label, value]. */
	function barList(rows, emptyText) {
		if (!rows.length) {
			return empty(emptyText);
		}
		var max = Math.max(1, Math.max.apply(null, rows.map(function (r) { return r[1]; })));
		return h('ul', { class: 'snn-a-barlist' }, rows.map(function (r) {
			return h('li', {}, [
				h('div', { class: 'snn-a-barlist-row' }, [h('span', { class: 'snn-a-barlist-label', text: r[0] }), h('strong', { text: n(r[1]) })]),
				h('div', { class: 'snn-a-bar-track' }, h('div', { class: 'snn-a-bar', style: 'width:' + Math.round(100 * r[1] / max) + '%' }))
			]);
		}));
	}

	function liveSummary(data) {
		return h('div', { class: 'snn-a-live-summary' }, [
			h('div', { class: 'snn-a-live' }, [
				h('span', { class: 'snn-a-live-dot', 'aria-hidden': 'true' }),
				h('strong', { class: 'snn-a-live-count', text: n(data.active) })
			]),
			h('p', { class: 'snn-a-muted', text: 'visitors active in the last 30 minutes' })
		]);
	}

	function pageName(p) {
		return p.title || p.path;
	}

	function renderDashboard(rp) {
		var period = rangeLabel(rp);
		var grid = h('div', { class: 'snn-a-grid' });
		var kpis = h('div', { class: 'snn-a-kpis', 'aria-busy': 'true' }, [1, 2, 3, 4, 5, 6, 7, 8].map(function () {
			return h('div', { class: 'snn-a-kpi' }, skeleton(2));
		}));
		var customize = h('div', { class: 'snn-a-customize', hidden: true });
		var status = h('span', { class: 'snn-a-muted', role: 'status' });
		var overview = api('stats/overview', rp);

		var toggle = h('button', { type: 'button', class: 'button', 'aria-expanded': 'false', onclick: function () {
			var editing = !grid.classList.contains('is-editing');
			grid.classList.toggle('is-editing', editing);
			customize.hidden = !editing;
			toggle.textContent = editing ? 'Done' : 'Edit layout';
			toggle.classList.toggle('button-primary', editing);
			toggle.setAttribute('aria-expanded', String(editing));
		} }, 'Edit layout');
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, [rangeControl(), toggle]));
		root.appendChild(h('p', { class: 'snn-a-caption', text: 'Compared with the previous period of the same length.' }));
		root.appendChild(kpis);
		root.appendChild(customize);
		root.appendChild(grid);

		overview.then(function (data) {
			var c = data.current;
			var p = data.previous;
			clear(kpis);
			kpis.removeAttribute('aria-busy');
			[
				['Visitors', n(c.visitors), change(c.visitors, p.visitors)],
				['New visitors', n(c.new_visitors), change(c.new_visitors, p.new_visitors)],
				['Sessions', n(c.sessions), change(c.sessions, p.sessions)],
				['Views', n(c.views), change(c.views, p.views)],
				['Bounce rate', pct(c.bounce_rate), change(c.bounce_rate, p.bounce_rate, { invert: true, points: true })],
				['Avg. time', duration(c.avg_duration), change(c.avg_duration, p.avg_duration)],
				['Views per session', Number(c.views_per_session).toFixed(2), change(c.views_per_session, p.views_per_session)],
				['Events', n(c.events), change(c.events, p.events)]
			].forEach(function (m) {
				kpis.appendChild(kpi(m[0], m[1], m[2]));
			});
		}).catch(function (err) {
			clear(kpis);
			kpis.appendChild(failed(err, render));
		});

		function mountCard(id, title, sub, link, load) {
			var content = h('div', {}, skeleton(4));
			var box = card(title, content, { id: id, sub: sub, link: link, grip: true });
			box.setAttribute('data-size', 'half');
			box.appendChild(editTools(box));
			grid.appendChild(box);
			load(content).catch(function (err) {
				clear(content);
				content.appendChild(failed(err));
			});
			return content;
		}

		// Live cards reload every 10 seconds while the tab is visible, and at once when it is shown again.
		// A failed refresh keeps the last good content instead of replacing it with an error.
		var live = [];
		var liveTimer = null;
		function refreshLive() {
			live.forEach(function (item) {
				item.load(item.content).catch(function () {});
			});
		}
		function onLiveVisibility() {
			window.clearInterval(liveTimer);
			liveTimer = null;
			if (document.visibilityState === 'visible') {
				refreshLive();
				liveTimer = window.setInterval(refreshLive, 10000);
			}
		}
		function mountLiveCard(id, title, sub, link, load) {
			live.push({ content: mountCard(id, title, sub, link, load), load: load });
		}

		/** Per-card controls. They only show in edit mode (.is-editing on the grid). */
		function editTools(box) {
			function sizeButton(label, size) {
				return h('button', { type: 'button', class: 'snn-a-seg', 'data-size': size, onclick: function () { setSize(box, size); } }, label);
			}
			return h('div', { class: 'snn-a-edit-tools' }, [
				h('button', { type: 'button', class: 'button', 'aria-label': 'Move up', title: 'Move up', onclick: function () { move(box, -1); } }, '↑'),
				h('button', { type: 'button', class: 'button', 'aria-label': 'Move down', title: 'Move down', onclick: function () { move(box, 1); } }, '↓'),
				h('span', { class: 'snn-a-segment', role: 'group', 'aria-label': 'Card width' }, [sizeButton('Half', 'half'), sizeButton('Full width', 'full')]),
				h('button', { type: 'button', class: 'button-link-delete', onclick: function () { hide(box); } }, 'Hide')
			]);
		}

		function syncSizeButtons(box) {
			Array.prototype.forEach.call(box.querySelectorAll('.snn-a-seg'), function (button) {
				var active = button.getAttribute('data-size') === box.getAttribute('data-size');
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-pressed', String(active));
			});
		}

		function visibleCards() {
			return Array.prototype.slice.call(grid.children).filter(function (c) { return !c.hidden; });
		}

		function setSize(box, size) {
			box.setAttribute('data-size', size);
			syncSizeButtons(box);
			flashDrop(box);
			saveLayout();
		}

		function move(box, direction) {
			var list = visibleCards();
			var target = list[list.indexOf(box) + direction];
			if (!target) {
				return;
			}
			grid.insertBefore(box, direction < 0 ? target : target.nextElementSibling);
			flashDrop(box);
			saveLayout();
		}

		function hide(box) {
			box.hidden = true;
			syncToggles();
			saveLayout();
		}

		/** Briefly highlights a card that just moved, so the user can see where it landed. */
		function flashDrop(box) {
			box.classList.remove('is-dropped');
			void box.offsetWidth;
			box.classList.add('is-dropped');
		}

		function syncToggles() {
			Array.prototype.forEach.call(customize.querySelectorAll('input[type="checkbox"]'), function (input) {
				var target = grid.querySelector('[data-card="' + input.value + '"]');
				input.checked = !!target && !target.hidden;
			});
		}

		mountCard('chart', 'Traffic over time', period, null, function (content) {
			return overview.then(function (data) {
				clear(content);
				var box = chartBox(300);
				content.appendChild(box);
				lineChart(box.querySelector('canvas'), data.series.map(function (d) { return dayLabel(d.date); }), [
					{ label: 'Visitors', color: COLOR_VISITORS, data: data.series.map(function (d) { return d.visitors; }), fill: true },
					{ label: 'Views', color: COLOR_VIEWS, data: data.series.map(function (d) { return d.views; }), dash: [4, 3] }
				]);
			});
		});

		mountCard('pages', 'Top pages', period, pageLink('snn-analytics-pages'), function (content) {
			return api('stats/pages', Object.assign({}, rp, { orderby: 'views', per_page: 10 })).then(function (data) {
				clear(content);
				content.appendChild(table(['Page', numCol('Views'), numCol('Visitors')], data.rows.map(function (r) {
					return [pageCell(r), n(r.views), n(r.visitors)];
				}), 'No page views in this range.'));
			});
		});

		mountCard('geo', 'Geographic traffic', period, pageLink('snn-analytics-geo'), function (content) {
			return Promise.all([api('stats/geo', rp), loadWorld()]).then(function (results) {
				clear(content);
				if (!results[0].countries.length) {
					content.appendChild(empty());
					return;
				}
				content.appendChild(worldMap(results[0].countries, results[1]));
				content.appendChild(mapLegend());
			});
		});

		mountCard('newreturning', 'New vs. returning', period, null, function (content) {
			return overview.then(function (data) {
				clear(content);
				var c = data.current;
				content.appendChild(breakdown(
					['New visitors', 'Returning visitors'],
					[c.new_visitors, Math.max(0, c.visitors - c.new_visitors)],
					[PALETTE[0], PALETTE[5]]
				));
			});
		});

		mountCard('devices', 'Devices', period, pageLink('snn-analytics-devices'), function (content) {
			return api('stats/devices', rp).then(function (data) {
				clear(content);
				content.appendChild(breakdown(
					data.devices.map(function (d) { return d.label; }),
					data.devices.map(function (d) { return d.sessions; })
				));
			});
		});

		mountCard('sources', 'Traffic sources', period, pageLink('snn-analytics-referrers'), function (content) {
			return api('stats/referrers', rp).then(function (data) {
				clear(content);
				var types = Object.keys(data.types);
				content.appendChild(breakdown(
					types.map(function (t) { return CHANNEL_TYPES[t] || 'Other'; }),
					types.map(function (t) { return data.types[t]; })
				));
			});
		});

		mountLiveCard('recent', 'Recent views', 'The last 10 page views', pageLink('snn-analytics-journeys'), function (content) {
			return api('stats/recent', { limit: 10 }).then(function (rows) {
				clear(content);
				content.appendChild(table(['Time', 'Page', 'Country', 'Device', 'Browser'], rows.map(function (r) {
					return [clock(r.time), r.title, countryName(r.country), r.device, r.browser || r.os];
				}), 'No views yet.'));
			});
		});

		mountLiveCard('realtime', 'Realtime', 'Views per minute, last 30 minutes', pageLink('snn-analytics-realtime'), function (content) {
			return api('stats/realtime').then(function (data) {
				clear(content);
				content.appendChild(liveSummary(data));
				content.appendChild(minuteBars(data.per_minute, 110));
				content.appendChild(h('p', { class: 'snn-a-subhead', text: 'Live pages' }));
				content.appendChild(barList(data.pages.slice(0, 5).map(function (p) { return [pageName(p), p.visitors]; }), 'Nobody is on the site right now.'));
			});
		});

		mountCard('campaigns', 'Campaigns', period, pageLink('snn-analytics-goals') + '&tab=campaigns', function (content) {
			return api('stats/campaigns', rp).then(function (rows) {
				clear(content);
				content.appendChild(table(['Campaign', numCol('Sessions')], rows.filter(isTagged).slice(0, 5).map(function (c) {
					return [campaignName(c), n(c.sessions)];
				}), 'No tagged campaign visits in this range. Build tagged links under Conversion Tracking.'));
			});
		});

		mountCard('goals', 'Goals', period, pageLink('snn-analytics-goals'), function (content) {
			return api('goals', rp).then(function (data) {
				clear(content);
				content.appendChild(table(['Goal', numCol('Conversions')], data.map(function (g) {
					return [g.name, n(g.conversions)];
				}), 'No goals yet. Add one under Conversion Tracking.'));
			});
		});

		// ------------------------------------------------ layout and cards

		/** Puts cards in the saved order, at their saved widths, and hides the ones turned off. */
		function applyLayout(layout) {
			var order = layout.order && layout.order.length ? layout.order : (cfg.cards || []);
			var hidden = layout.hidden || [];
			var sizes = layout.sizes || {};
			var cards = Array.prototype.slice.call(grid.children);
			var rank = function (id) {
				var i = order.indexOf(id);
				return i < 0 ? 999 : i;
			};
			cards.sort(function (a, b) {
				return rank(a.getAttribute('data-card')) - rank(b.getAttribute('data-card'));
			});
			cards.forEach(function (c) {
				var id = c.getAttribute('data-card');
				grid.appendChild(c);
				c.hidden = hidden.indexOf(id) >= 0;
				c.setAttribute('data-size', sizes[id] || 'half');
				syncSizeButtons(c);
			});
			buildCustomize(hidden);
		}

		function saveLayout() {
			var all = Array.prototype.slice.call(grid.children);
			var sizes = {};
			all.forEach(function (c) {
				sizes[c.getAttribute('data-card')] = c.getAttribute('data-size');
			});
			status.textContent = 'Saving…';
			send('prefs/dashboard', {
				order: all.map(function (c) { return c.getAttribute('data-card'); }),
				hidden: all.filter(function (c) { return c.hidden; }).map(function (c) { return c.getAttribute('data-card'); }),
				sizes: sizes
			}).then(function () {
				status.textContent = 'Layout saved.';
			}).catch(function (err) {
				status.textContent = 'Could not save the layout: ' + err.message;
			});
		}

		function buildCustomize(hidden) {
			clear(customize);
			var reset = h('button', { type: 'button', class: 'button-link', onclick: function () {
				applyLayout({ order: cfg.cards || [], hidden: [], sizes: {} });
				saveLayout();
			} }, 'Reset layout');
			customize.appendChild(h('div', { class: 'snn-a-customize-head' }, [
				h('p', { class: 'snn-a-muted', text: 'Show or hide cards here. Move them with the arrows or by dragging, and pick Half or Full width on each card. Changes save automatically.' }),
				reset
			]));
			customize.appendChild(h('div', { class: 'snn-a-toggles' }, (cfg.cards || []).map(function (id) {
				var input = h('input', { type: 'checkbox', value: id });
				input.checked = hidden.indexOf(id) < 0;
				input.addEventListener('change', function () {
					var target = grid.querySelector('[data-card="' + id + '"]');
					if (target) {
						target.hidden = !input.checked;
					}
					saveLayout();
				});
				return h('label', { class: 'snn-a-toggle' }, [input, h('span', { text: CARD_LABELS[id] || id })]);
			})));
			customize.appendChild(h('p', { class: 'snn-a-muted snn-a-status' }, [status]));
		}

		// Drag any card to move it. A press on a link, button or input keeps its normal action.
		var dragging = null;
		grid.addEventListener('mousedown', function (event) {
			Array.prototype.forEach.call(grid.children, function (c) { c.draggable = false; });
			var box = event.target.closest('.snn-a-card');
			if (box && !event.target.closest('a, button, input, select, label')) {
				box.draggable = true;
			}
		});
		grid.addEventListener('dragstart', function (event) {
			dragging = event.target.closest('.snn-a-card');
			if (!dragging) {
				return;
			}
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', dragging.getAttribute('data-card'));
			dragging.classList.add('is-dragging');
		});
		grid.addEventListener('dragover', function (event) {
			if (!dragging) {
				return;
			}
			event.preventDefault();
			var over = event.target.closest('.snn-a-card');
			Array.prototype.forEach.call(grid.children, function (c) {
				c.classList.toggle('is-drop-target', c === over && over !== dragging);
			});
			if (!over || over === dragging) {
				return;
			}
			var rect = over.getBoundingClientRect();
			var before = event.clientY < rect.top + rect.height / 2;
			var target = before ? over : over.nextElementSibling;
			if (target !== dragging && target !== dragging.nextElementSibling) {
				grid.insertBefore(dragging, target);
			}
		});
		grid.addEventListener('dragend', function () {
			Array.prototype.forEach.call(grid.children, function (c) {
				c.classList.remove('is-drop-target');
			});
			if (dragging) {
				dragging.classList.remove('is-dragging');
				dragging.draggable = false;
				flashDrop(dragging);
			}
			dragging = null;
			saveLayout();
		});

		api('prefs/dashboard').then(applyLayout).catch(function () {
			applyLayout({ order: cfg.cards || [], hidden: [] });
		});

		if (document.visibilityState === 'visible') {
			liveTimer = window.setInterval(refreshLive, 10000);
		}
		document.addEventListener('visibilitychange', onLiveVisibility);
		teardown = function () {
			window.clearInterval(liveTimer);
			document.removeEventListener('visibilitychange', onLiveVisibility);
		};
	}

	function renderRealtime() {
		var updated = h('span', { class: 'snn-a-muted', role: 'status' });
		var content = h('div', {}, skeleton(6));
		var timer = null;

		root.appendChild(h('div', { class: 'snn-a-toolbar snn-a-toolbar-between' }, [
			h('p', { class: 'snn-a-muted', text: 'Refreshes every 10 seconds while this tab is open.' }),
			updated
		]));
		root.appendChild(content);

		// Rebuilt in one step each refresh, so nothing visibly flickers.
		function paint(data) {
			clear(content);
			content.appendChild(card('Active now', [liveSummary(data), minuteBars(data.per_minute, 200)], {
				sub: 'Views per minute, last 30 minutes',
				wide: true
			}));
			content.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Live pages', barList(data.pages.map(function (p) { return [pageName(p), p.visitors]; }), 'Nobody is on the site right now.'), { sub: 'Visitors in the last 30 minutes' }),
				card('Live countries', barList(data.countries.map(function (c) { return [countryName(c.code), c.visitors]; }), 'No visitors.'), { sub: 'Visitors in the last 30 minutes' }),
				card('Live referrers', barList(data.referrers.map(function (r) { return [r.domain, r.visitors]; }), 'No visitors.'), { sub: 'Visitors in the last 30 minutes' })
			]));
			updated.textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		}

		function tick() {
			api('stats/realtime').then(paint).catch(function (err) {
				clear(content);
				content.appendChild(failed(err, tick));
			});
		}

		function start() {
			if (!timer) {
				tick();
				timer = window.setInterval(tick, 10000);
			}
		}
		function stop() {
			window.clearInterval(timer);
			timer = null;
		}
		function onVisibility() {
			if (document.visibilityState === 'hidden') {
				stop();
			} else {
				start();
			}
		}

		document.addEventListener('visibilitychange', onVisibility);
		start();

		teardown = function () {
			stop();
			document.removeEventListener('visibilitychange', onVisibility);
		};
	}

	function renderGeo(rp) {
		var body = h('div', {}, skeleton(8));
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(body);

		Promise.all([api('stats/geo', rp), loadWorld()]).then(function (results) {
			var data = results[0];
			clear(body);
			if (!data.countries.length) {
				body.appendChild(empty('No visits in this range yet.'));
				return;
			}
			body.appendChild(card('Sessions by country', [worldMap(data.countries, results[1]), mapLegend()], { sub: rangeLabel(rp), wide: true }));
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Countries', table(['Country', numCol('Sessions'), numCol('Visitors'), numCol('Views')], data.countries.map(function (c) {
					return [countryName(c.code), n(c.sessions), n(c.visitors), n(c.views)];
				}))),
				card('Cities', data.cities.length
					? table(['City', 'Region', 'Country', numCol('Sessions'), numCol('Visitors')], data.cities.map(function (c) {
						return [c.name, c.region, countryName(c.code), n(c.sessions), n(c.visitors)];
					}))
					: cityNote())
			]));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err, render));
		});
	}

	/*
	 * Heatmaps, cursor trails and replays draw on top of the real page. The page is loaded in an
	 * iframe at the width of the device (with ?snn_a_preview=1, so the load is not tracked), and a
	 * canvas of the same size sits over it. Clicks are stored on a grid of 50 columns across the page
	 * width and 40 px rows; trail points as a share of the width (per 10000) and px from the top.
	 */
	var DEVICE_WIDTHS = { 0: 1280, 1: 1280, 2: 390, 3: 820 };
	// The canvas is drawn at half size, so a long page does not need a huge bitmap.
	var STAGE_RES = 0.5;
	var STAGE_MAX_HEIGHT = 20000;

	function urlParam(name) {
		return new URLSearchParams(window.location.search).get(name);
	}

	/** Puts a value in the address bar without reloading, so a link to this view can be shared. */
	function setUrlParam(name, value) {
		var url = new URL(window.location.href);
		if (value === null || value === '') {
			url.searchParams.delete(name);
		} else {
			url.searchParams.set(name, value);
		}
		window.history.replaceState(null, '', url.toString());
	}

	function previewUrl(path) {
		return cfg.site + path + (path.indexOf('?') === -1 ? '?' : '&') + 'snn_a_preview=1';
	}

	/**
	 * The page at device width, scaled down to fit, with a canvas on top. draw(ctx, width, height) is
	 * called with the context in page pixels each time the page height is known or changes.
	 * opts: minHeight (px the data needs), showPage (false draws on a blank sheet).
	 */
	function pageStage(path, device, draw, opts) {
		opts = opts || {};
		var width = DEVICE_WIDTHS[device] || DEVICE_WIDTHS[0];
		var height = Math.min(STAGE_MAX_HEIGHT, Math.max(800, opts.minHeight || 0));
		var scale = 1;
		var showPage = opts.showPage !== false;

		var frame = h('iframe', { class: 'snn-a-stage-frame', title: 'Page preview', tabindex: '-1', loading: 'lazy', scrolling: 'no' });
		var canvas = h('canvas', { class: 'snn-a-stage-canvas', 'aria-hidden': 'true' });
		var sheet = h('div', { class: 'snn-a-stage-page' }, [frame, canvas]);
		var inner = h('div', { class: 'snn-a-stage-inner' }, sheet);
		var status = h('p', { class: 'snn-a-stage-status', text: showPage ? 'Loading the page…' : '' });
		var outer = h('div', { class: 'snn-a-stage' + (showPage ? '' : ' is-blank') }, [inner, status]);
		var timers = [];

		function layout() {
			var room = outer.clientWidth || width;
			scale = Math.min(1, room / width);
			sheet.style.width = width + 'px';
			sheet.style.height = height + 'px';
			sheet.style.transform = 'scale(' + scale + ')';
			inner.style.width = Math.round(width * scale) + 'px';
			inner.style.height = Math.round(height * scale) + 'px';
			frame.style.width = width + 'px';
			frame.style.height = height + 'px';
			canvas.width = Math.round(width * STAGE_RES);
			canvas.height = Math.round(height * STAGE_RES);
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';
			paint();
		}

		function paint() {
			var ctx = canvas.getContext('2d');
			ctx.setTransform(1, 0, 0, 1, 0, 0);
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			ctx.setTransform(STAGE_RES, 0, 0, STAGE_RES, 0, 0);
			draw(ctx, width, height);
		}

		/** The page can grow after load (images, lazy content), so it is measured twice. */
		function measure() {
			var docHeight = 0;
			try {
				var doc = frame.contentDocument;
				docHeight = Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0);
			} catch (e) {
				docHeight = 0;
			}
			status.textContent = docHeight ? '' : 'The page could not be measured, so its height is estimated from the data.';
			var next = Math.min(STAGE_MAX_HEIGHT, Math.max(height, docHeight));
			if (next !== height) {
				height = next;
				layout();
			}
		}

		frame.addEventListener('load', function () {
			measure();
			timers.push(window.setTimeout(measure, 1500));
		});

		window.requestAnimationFrame(function () {
			layout();
			if (showPage) {
				frame.src = previewUrl(path);
			}
		});

		var onResize = function () { layout(); };
		window.addEventListener('resize', onResize);

		return {
			el: outer,
			width: width,
			sheet: sheet,
			redraw: paint,
			scale: function () { return scale; },
			/** Keeps a point of the page (px from the top) in view, for the replay cursor. */
			follow: function (y) {
				var top = y * scale;
				if (top < outer.scrollTop + outer.clientHeight * 0.2 || top > outer.scrollTop + outer.clientHeight * 0.8) {
					outer.scrollTop = Math.max(0, top - outer.clientHeight / 2);
				}
			},
			destroy: function () {
				window.removeEventListener('resize', onResize);
				timers.forEach(window.clearTimeout);
				frame.src = 'about:blank';
			}
		};
	}

	/** Blue to red, by how many clicks a spot has compared with the busiest one. */
	var HEAT_STOPS = [[0, [37, 99, 235]], [0.35, [16, 185, 129]], [0.6, [250, 204, 21]], [0.8, [249, 115, 22]], [1, [220, 38, 38]]];

	function heatColor(v) {
		for (var i = 1; i < HEAT_STOPS.length; i++) {
			if (v <= HEAT_STOPS[i][0]) {
				var a = HEAT_STOPS[i - 1];
				var b = HEAT_STOPS[i];
				var f = (v - a[0]) / (b[0] - a[0]);
				return a[1].map(function (c, k) { return Math.round(c + (b[1][k] - c) * f); });
			}
		}
		return HEAT_STOPS[HEAT_STOPS.length - 1][1];
	}

	/**
	 * Each cell adds a soft spot to an alpha layer; the summed alpha is then mapped to colours, so
	 * spots that overlap get hotter instead of just darker.
	 */
	function drawHeat(ctx, width, cells, max) {
		var colW = width / 50;
		var radius = Math.max(18, colW * 1.4);
		ctx.save();
		ctx.globalCompositeOperation = 'source-over';
		cells.forEach(function (cell) {
			var x = (cell[0] + 0.5) * colW;
			var y = cell[1] * 40 + 20;
			var strength = Math.max(0.08, Math.sqrt(cell[2] / max));
			var g = ctx.createRadialGradient(x, y, 0, x, y, radius);
			g.addColorStop(0, 'rgba(0,0,0,' + strength.toFixed(3) + ')');
			g.addColorStop(1, 'rgba(0,0,0,0)');
			ctx.fillStyle = g;
			ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
		});
		ctx.restore();

		var image = ctx.getImageData(0, 0, ctx.canvas.width, ctx.canvas.height);
		var px = image.data;
		for (var i = 0; i < px.length; i += 4) {
			var alpha = px[i + 3];
			if (!alpha) {
				continue;
			}
			var rgb = heatColor(Math.min(1, alpha / 255));
			px[i] = rgb[0];
			px[i + 1] = rgb[1];
			px[i + 2] = rgb[2];
			px[i + 3] = Math.min(210, 60 + alpha);
		}
		ctx.putImageData(image, 0, 0);
	}

	function drawTrail(ctx, width, trail, color, upTo) {
		var points = trail.filter(function (p) { return upTo === undefined || p[2] <= upTo; });
		if (!points.length) {
			return;
		}
		ctx.strokeStyle = color;
		ctx.lineWidth = 3;
		ctx.lineJoin = 'round';
		ctx.lineCap = 'round';
		ctx.beginPath();
		points.forEach(function (p, i) {
			var x = p[0] / 10000 * width;
			if (i === 0) {
				ctx.moveTo(x, p[1]);
			} else {
				ctx.lineTo(x, p[1]);
			}
		});
		ctx.stroke();
		points.forEach(function (p) {
			if (p[3]) {
				ctx.fillStyle = 'rgba(220,38,38,0.85)';
				ctx.beginPath();
				ctx.arc(p[0] / 10000 * width, p[1], 9, 0, Math.PI * 2);
				ctx.fill();
			}
		});
	}

	/** A select with the most viewed pages in the range. Resolves with the list once it is filled. */
	function pagePicker(rp, current, pick) {
		var select = h('select', { class: 'snn-a-pagepick', 'aria-label': 'Page', onchange: function () { pick(Number(select.value)); } },
			h('option', { value: '' , text: 'Loading pages…' }));
		var ready = api('stats/pages', Object.assign({}, rp, { orderby: 'views', order: 'desc', per_page: 100 })).then(function (data) {
			clear(select);
			if (!data.rows.length) {
				select.appendChild(h('option', { value: '', text: 'No page views in this range' }));
				return [];
			}
			var rows = data.rows.slice();
			if (current && !rows.some(function (r) { return r.id === current; })) {
				current = 0;
			}
			rows.forEach(function (row) {
				select.appendChild(h('option', {
					value: String(row.id),
					text: (row.title || row.path) + ' · ' + row.path + ' (' + n(row.views) + ' views)',
					selected: row.id === current ? true : null
				}));
			});
			return rows;
		});
		return { el: select, ready: ready };
	}

	/** Shared shell for the heatmap and trail screens: range, page and device, then the stage. */
	function mapScreen(rp, kind) {
		var state = { page: Number(urlParam('pid')) || 0, device: Number(urlParam('device')) || 0, showPage: true };
		var rows = [];
		var bar = h('div', { class: 'snn-a-toolbar' });
		var body = h('div', {}, skeleton(6));
		var stage = null;

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(bar);
		root.appendChild(body);

		teardown = function () {
			if (stage) {
				stage.destroy();
			}
		};

		var picker = pagePicker(rp, state.page, function (id) {
			state.page = id;
			setUrlParam('pid', id);
			load();
		});

		function toolbar() {
			clear(bar);
			bar.appendChild(h('div', { class: 'snn-a-mapbar' }, [
				picker.el,
				deviceSegment(state.device, function (value) {
					state.device = value;
					setUrlParam('device', value || null);
					toolbar();
					load();
				}),
				h('label', { class: 'snn-a-check' }, [
					h('input', { type: 'checkbox', checked: state.showPage ? true : null, onchange: function (event) {
						state.showPage = event.target.checked;
						load();
					} }),
					' Show the page'
				])
			]));
		}

		function load() {
			if (stage) {
				stage.destroy();
				stage = null;
			}
			clear(body);
			var row = rows.filter(function (r) { return r.id === state.page; })[0];
			if (!row) {
				body.appendChild(empty('No page views in this range.'));
				return;
			}
			body.appendChild(skeleton(6));
			var query = Object.assign({}, rp, { id: row.id, device: state.device });
			api(kind === 'heat' ? 'stats/heatmap' : 'stats/trails', query).then(function (data) {
				clear(body);
				if (kind === 'heat') {
					showHeat(row, data);
				} else {
					showTrails(row, data);
				}
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err, load));
			});
		}

		function intro(row, text) {
			return h('p', { class: 'snn-a-muted' }, [
				h('strong', { text: row.title || row.path }),
				' · ',
				h('a', { href: cfg.site + row.path, target: '_blank', rel: 'noopener', text: row.path }),
				' · ' + text
			]);
		}

		function showHeat(row, data) {
			if (!data.total) {
				body.appendChild(intro(row, 'no clicks'));
				body.appendChild(empty('No clicks on this page in this range yet. Clicks are recorded only when the click heatmap is turned on in Settings.'));
				return;
			}
			var lowest = data.cells.reduce(function (most, c) { return Math.max(most, c[1]); }, 0);
			body.appendChild(intro(row, n(data.total) + ' clicks in this range'));
			body.appendChild(h('div', { class: 'snn-a-heatlegend' }, [
				h('span', { text: 'Fewer clicks' }),
				h('span', { class: 'snn-a-heatscale', 'aria-hidden': 'true' }),
				h('span', { text: 'More clicks' })
			]));
			stage = pageStage(row.path, state.device, function (ctx, width) {
				drawHeat(ctx, width, data.cells, data.max);
			}, { minHeight: (lowest + 2) * 40, showPage: state.showPage });
			body.appendChild(stage.el);
			body.appendChild(note());
		}

		function showTrails(row, data) {
			if (!data.trails.length) {
				body.appendChild(intro(row, 'no trails'));
				body.appendChild(empty('No cursor trails on this page in this range. They are recorded only when cursor trails are turned on in Settings.'));
				return;
			}
			var pick = -1;
			var lowest = data.trails.reduce(function (most, trail) {
				return trail.reduce(function (inner, p) { return Math.max(inner, p[1]); }, most);
			}, 0);
			var shown = data.total > data.trails.length ? ', showing the latest ' + data.trails.length : '';
			var select = h('select', { 'aria-label': 'Trail', onchange: function () {
				pick = Number(select.value);
				stage.redraw();
			} }, [h('option', { value: '-1', text: 'All trails together' })].concat(data.trails.map(function (trail, i) {
				var clicks = trail.filter(function (p) { return p[3]; }).length;
				var secs = trail.length ? Math.round(trail[trail.length - 1][2] / 1000) : 0;
				return h('option', { value: String(i), text: 'Trail ' + (i + 1) + ' · ' + secs + ' s' + (clicks ? ' · ' + clicks + (clicks === 1 ? ' click' : ' clicks') : '') });
			})));
			body.appendChild(intro(row, n(data.total) + ' trails in this range' + shown));
			body.appendChild(h('div', { class: 'snn-a-mapbar' }, [select, h('span', { class: 'snn-a-muted', text: 'Red dots are clicks.' })]));
			stage = pageStage(row.path, state.device, function (ctx, width) {
				if (pick >= 0) {
					drawTrail(ctx, width, data.trails[pick], 'rgba(34,113,177,0.9)');
					return;
				}
				var alpha = Math.max(0.12, Math.min(0.6, 3 / data.trails.length));
				data.trails.forEach(function (trail) {
					drawTrail(ctx, width, trail, 'rgba(34,113,177,' + alpha.toFixed(2) + ')');
				});
			}, { minHeight: lowest + 200, showPage: state.showPage });
			body.appendChild(stage.el);
			body.appendChild(note());
		}

		function note() {
			return h('p', { class: 'snn-a-muted snn-a-stage-note', text: 'The page is shown as it looks now. If it changed during this range, some marks may not line up with the content.' });
		}

		toolbar();
		picker.ready.then(function (list) {
			rows = list;
			if (!state.page && rows.length) {
				state.page = rows[0].id;
			}
			load();
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err));
		});
	}

	function renderHeatmaps(rp) {
		mapScreen(rp, 'heat');
	}

	// ------------------------------------------------------------- replay

	var REPLAY_SPEEDS = [1, 2, 4, 8];

	function renderReplay(rp) {
		var state = { page: 1 };
		var player = h('div', { class: 'snn-a-detail' });
		var results = h('div', {}, skeleton(6));
		var stop = null;

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(player);
		root.appendChild(results);

		teardown = function () {
			if (stop) {
				stop();
			}
		};

		function load() {
			clear(results);
			results.appendChild(skeleton(6));
			api('stats/journeys', Object.assign({}, rp, state, { per_page: PER_PAGE })).then(function (data) {
				clear(results);
				var pages = Math.max(1, Math.ceil(data.total / PER_PAGE));
				results.appendChild(h('p', { class: 'snn-a-muted', text: n(data.total) + ' sessions. Select one to replay it.' }));
				if (!data.rows.length) {
					results.appendChild(empty('No sessions in this range.'));
					return;
				}
				results.appendChild(table(
					['Started', 'Entry page', 'Source', 'Country', 'Device', numCol('Views'), numCol('Active time')],
					data.rows.map(function (s) {
						return [
							started(s.started_at),
							s.entry,
							s.referrer,
							h('span', { class: 'snn-a-country' }, [flag(s.country), countryName(s.country)]),
							DEVICE_LABELS[s.device] || 'Other',
							n(s.views),
							duration(s.duration)
						];
					}),
					'No sessions in this range.',
					function (index) { open(data.rows[index].id); }
				));
				results.appendChild(pager(state.page, pages, function (page) { state.page = page; load(); }));
			}).catch(function (err) {
				clear(results);
				results.appendChild(failed(err, load));
			});
		}

		function open(id) {
			if (stop) {
				stop();
				stop = null;
			}
			setUrlParam('session', id);
			clear(player);
			player.appendChild(skeleton(6));
			player.scrollIntoView({ behavior: 'smooth', block: 'start' });
			api('stats/journey', { id: id }).then(function (data) {
				clear(player);
				stop = replayPlayer(player, data, function () {
					stop = null;
					clear(player);
					setUrlParam('session', null);
				});
			}).catch(function (err) {
				clear(player);
				player.appendChild(failed(err));
			});
		}

		load();
		if (urlParam('session')) {
			open(Number(urlParam('session')));
		}
	}

	/**
	 * Plays one session page by page: the page at the visitor's device width, the pointer moving
	 * along its trail, a ripple on each click, and the logged actions as they happen. Returns a
	 * function that stops playback and frees the page.
	 */
	function replayPlayer(box, data, close) {
		var s = data.session;
		var steps = data.steps;
		var index = 0;
		var speed = 1;
		var playing = false;
		var time = 0;
		var length = 1000;
		var last = 0;
		var raf = 0;
		var advance = 0;
		var stage = null;
		var step = null;
		var shownClicks = 0;

		var cursor = h('div', { class: 'snn-a-cursor', 'aria-hidden': 'true' });
		var playBtn = h('button', { type: 'button', class: 'button button-primary', onclick: function () { playing ? pause() : play(); } }, 'Play');
		var scrub = h('input', { type: 'range', min: '0', max: '1000', value: '0', class: 'snn-a-scrub', 'aria-label': 'Position in this page view', oninput: function () {
			seek(Number(scrub.value) / 1000 * length);
		} });
		var clockLabel = h('span', { class: 'snn-a-time', text: '0:00 / 0:00' });
		var speedBar = h('div', { class: 'snn-a-segment', role: 'group', 'aria-label': 'Speed' });
		var chips = h('ol', { class: 'snn-a-replay-steps' });
		var log = h('ol', { class: 'snn-a-replay-log' });
		var stageBox = h('div', { class: 'snn-a-replay-stage' });
		var info = h('p', { class: 'snn-a-muted' });

		function mmss(ms) {
			var secs = Math.floor(ms / 1000);
			return Math.floor(secs / 60) + ':' + ('0' + (secs % 60)).slice(-2);
		}

		function renderSpeeds() {
			clear(speedBar);
			REPLAY_SPEEDS.forEach(function (value) {
				speedBar.appendChild(h('button', {
					type: 'button',
					class: 'snn-a-seg' + (speed === value ? ' is-active' : ''),
					'aria-pressed': speed === value ? 'true' : 'false',
					onclick: function () { speed = value; renderSpeeds(); }
				}, value + '×'));
			});
		}

		function renderChips() {
			clear(chips);
			steps.forEach(function (st, i) {
				chips.appendChild(h('li', {}, h('button', {
					type: 'button',
					class: 'snn-a-replay-chip' + (i === index ? ' is-current' : '') + (st.trail.length ? '' : ' is-empty'),
					'aria-current': i === index ? 'step' : null,
					title: st.trail.length ? st.path : st.path + ' (no cursor trail)',
					onclick: function () { show(i, playing); }
				}, [h('span', { class: 'snn-a-replay-num', text: String(i + 1) }), st.title || st.path])));
			});
		}

		/** Ms from the start of the view to an action, which is stored in whole seconds. */
		function eventOffset(event) {
			return Math.max(0, (event.at - step.at) * 1000);
		}

		function show(i, autoplay) {
			window.clearTimeout(advance);
			if (stage) {
				stage.destroy();
			}
			index = i;
			step = steps[i];
			time = 0;
			shownClicks = 0;
			var trailEnd = step.trail.length ? step.trail[step.trail.length - 1][2] : 0;
			var eventEnd = step.events.reduce(function (most, e) { return Math.max(most, eventOffset(e)); }, 0);
			length = Math.max(trailEnd, Math.min(eventEnd, 120000), 1000) + 1000;
			renderChips();

			info.textContent = (step.title || step.path) + ' · ' + step.path + ' · on the page ' + duration(step.duration)
				+ (step.trail.length ? '' : ' · no cursor trail for this view');

			clear(log);
			step.events.forEach(function (e) {
				log.appendChild(h('li', { 'data-at': String(eventOffset(e)) }, [
					h('span', { class: 'snn-a-time', text: mmss(eventOffset(e)) }),
					h('span', { text: ' ' + e.label + ': ' }),
					h('strong', { text: e.value })
				]));
			});
			if (!step.events.length) {
				log.appendChild(h('li', { class: 'snn-a-muted', text: 'No actions on this page.' }));
			}

			var lowest = step.trail.reduce(function (most, p) { return Math.max(most, p[1]); }, 0);
			clear(stageBox);
			stage = pageStage(step.path, s.device, function (ctx, width) {
				drawTrail(ctx, width, step.trail, 'rgba(34,113,177,0.55)', time);
			}, { minHeight: lowest + 400 });
			stage.sheet.appendChild(cursor);
			stageBox.appendChild(stage.el);
			frame();
			if (autoplay) {
				play();
			} else {
				pause();
			}
		}

		function positionAt(t) {
			var trail = step.trail;
			if (!trail.length) {
				return null;
			}
			if (t <= trail[0][2]) {
				return [trail[0][0], trail[0][1]];
			}
			for (var i = 1; i < trail.length; i++) {
				if (trail[i][2] >= t) {
					var a = trail[i - 1];
					var b = trail[i];
					var f = b[2] === a[2] ? 1 : (t - a[2]) / (b[2] - a[2]);
					return [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f];
				}
			}
			var end = trail[trail.length - 1];
			return [end[0], end[1]];
		}

		function ripple(p) {
			var dot = h('div', { class: 'snn-a-ripple', 'aria-hidden': 'true' });
			dot.style.left = (p[0] / 10000 * stage.width) + 'px';
			dot.style.top = p[1] + 'px';
			stage.sheet.appendChild(dot);
			window.setTimeout(function () { if (dot.parentNode) { dot.parentNode.removeChild(dot); } }, 900);
		}

		/** Draws the current moment: trail so far, pointer, new clicks, and actions already done. */
		function frame() {
			var pos = positionAt(time);
			if (pos) {
				cursor.hidden = false;
				cursor.style.left = (pos[0] / 10000 * stage.width) + 'px';
				cursor.style.top = pos[1] + 'px';
				if (playing) {
					stage.follow(pos[1]);
				}
			} else {
				cursor.hidden = true;
			}
			var clicks = step.trail.filter(function (p) { return p[3] && p[2] <= time; });
			if (playing) {
				clicks.slice(shownClicks).forEach(ripple);
			}
			shownClicks = clicks.length;
			stage.redraw();
			Array.prototype.forEach.call(log.children, function (li) {
				var at = li.getAttribute('data-at');
				li.classList.toggle('is-done', at !== null && Number(at) <= time);
			});
			scrub.value = String(Math.round(time / length * 1000));
			clockLabel.textContent = mmss(time) + ' / ' + mmss(length);
		}

		function tick(now) {
			time = Math.min(length, time + (now - last) * speed);
			last = now;
			frame();
			if (time >= length) {
				pause();
				if (index < steps.length - 1) {
					advance = window.setTimeout(function () { show(index + 1, true); }, 800);
				}
				return;
			}
			raf = window.requestAnimationFrame(tick);
		}

		function play() {
			if (time >= length) {
				time = 0;
				shownClicks = 0;
			}
			playing = true;
			playBtn.textContent = 'Pause';
			last = performance.now();
			window.cancelAnimationFrame(raf);
			raf = window.requestAnimationFrame(tick);
		}

		function pause() {
			playing = false;
			playBtn.textContent = 'Play';
			window.cancelAnimationFrame(raf);
		}

		function seek(t) {
			time = Math.max(0, Math.min(length, t));
			shownClicks = step.trail.filter(function (p) { return p[3] && p[2] <= time; }).length;
			frame();
		}

		function stopAll() {
			pause();
			window.clearTimeout(advance);
			if (stage) {
				stage.destroy();
				stage = null;
			}
		}

		var place = [s.city, countryName(s.country)].filter(Boolean).join(', ');
		var device = deviceLine(s);
		var screens = screenLine(s);
		var closeBtn = h('button', { type: 'button', class: 'button-link', onclick: function () { stopAll(); close(); } }, 'Close');

		box.appendChild(card('Replay of the visit on ' + when(s.started_at), [
			h('p', { class: 'snn-a-stat-row' }, [
				h('span', {}, [h('strong', { text: String(steps.length) }), steps.length === 1 ? ' page' : ' pages']),
				h('span', {}, [h('strong', { text: duration(s.duration) }), ' active time']),
				h('span', { class: 'snn-a-country' }, [flag(s.country), place]),
				h('span', { text: device }),
				screens ? h('span', { text: screens }) : null,
				h('span', { text: 'From ' + s.referrer })
			]),
			chips,
			h('div', { class: 'snn-a-replay-controls' }, [playBtn, scrub, clockLabel, speedBar]),
			info,
			h('div', { class: 'snn-a-replay-body' }, [stageBox, h('div', { class: 'snn-a-replay-side' }, [h('h3', { class: 'snn-a-subhead', text: 'Actions' }), log])]),
			h('p', { class: 'snn-a-muted snn-a-stage-note', text: 'The page is shown as it looks now, at the visitor’s screen width. Scrolling and typing are not recorded; the view follows the pointer.' })
		], { actions: closeBtn }));

		renderSpeeds();
		if (steps.length) {
			show(0, false);
		} else {
			box.appendChild(empty('This visit has no page views to replay.'));
		}
		return stopAll;
	}

	function renderPages(rp) {
		var state = { page: 1, orderby: 'views', order: 'desc', search: '' };
		var detail = h('div', { class: 'snn-a-detail' });
		var results = h('div', {}, skeleton(6));
		var input = h('input', { type: 'search', placeholder: 'Search by title or path', 'aria-label': 'Search pages' });
		var form = h('form', { class: 'snn-a-search', onsubmit: function (event) {
			event.preventDefault();
			state.search = input.value.trim();
			state.page = 1;
			load();
		} }, [input, h('button', { type: 'submit', class: 'button' }, 'Search')]);

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, [rangeControl(), form]));
		root.appendChild(detail);
		root.appendChild(results);

		function sortHead(key, label, numeric) {
			var active = state.orderby === key;
			return h('th', { class: numeric ? 'num' : '', 'aria-sort': active ? (state.order === 'desc' ? 'descending' : 'ascending') : null }, [
				h('button', { type: 'button', class: 'snn-a-sort', onclick: function () {
					if (state.orderby === key) {
						state.order = state.order === 'desc' ? 'asc' : 'desc';
					} else {
						state.orderby = key;
						state.order = key === 'title' ? 'asc' : 'desc';
					}
					state.page = 1;
					load();
				} }, [label, active ? (state.order === 'desc' ? ' ↓' : ' ↑') : ''])
			]);
		}

		function load() {
			clear(results);
			results.appendChild(skeleton(6));
			api('stats/pages', Object.assign({}, rp, state, { per_page: PER_PAGE })).then(function (data) {
				clear(results);
				var pages = Math.max(1, Math.ceil(data.total / PER_PAGE));
				results.appendChild(h('p', { class: 'snn-a-muted', text: n(data.total) + ' pages' + (state.search ? ' matching “' + state.search + '”' : '') }));
				if (!data.rows.length) {
					results.appendChild(empty(state.search ? 'Nothing matches that search.' : 'No page views in this range.'));
					return;
				}
				var heads = [
					sortHead('title', 'Page', false),
					sortHead('views', 'Views', true),
					sortHead('visitors', 'Visitors', true),
					sortHead('duration', 'Avg. time', true),
					sortHead('entrances', 'Entrances', true),
					sortHead('exits', 'Exits', true)
				];
				var wrap = h('div', { class: 'snn-a-scroll' }, h('table', { class: 'snn-a-table' }, [
					h('thead', {}, h('tr', {}, heads)),
					h('tbody', {}, data.rows.map(function (row) {
						return h('tr', { class: 'is-selectable', tabindex: '0', onclick: function (event) { select(row, event.currentTarget); }, onkeydown: function (event) {
							if (event.key === 'Enter') { select(row, event.currentTarget); }
						} }, [
							h('td', {}, pageCell(row)),
							h('td', { class: 'num' }, n(row.views)),
							h('td', { class: 'num' }, n(row.visitors)),
							h('td', { class: 'num' }, duration(row.avg_time)),
							h('td', { class: 'num' }, n(row.entrances)),
							h('td', { class: 'num' }, n(row.exits))
						]);
					}))
				]));
				results.appendChild(wrap);
				results.appendChild(pager(state.page, pages, function (page) { state.page = page; load(); }));
			}).catch(function (err) {
				clear(results);
				results.appendChild(failed(err, load));
			});
		}

		function select(row, tr) {
			Array.prototype.forEach.call(results.querySelectorAll('tr.is-selected'), function (el) { el.classList.remove('is-selected'); });
			tr.classList.add('is-selected');
			showPage(row);
		}

		function showPage(row) {
			clear(detail);
			var canvas = chartBox(240);
			var close = h('button', { type: 'button', class: 'button-link', onclick: function () { clear(detail); } }, 'Close');
			detail.appendChild(card(row.title || row.path, [
				h('p', { class: 'snn-a-stat-row' }, [
					h('span', {}, [h('strong', { text: n(row.views) }), ' views']),
					h('span', {}, [h('strong', { text: n(row.visitors) }), ' visitors']),
					h('span', {}, [h('strong', { text: duration(row.avg_time) }), ' avg. time'])
				]),
				canvas,
				h('p', { class: 'snn-a-linkrow' }, [
					!cfg.can || cfg.can.heatmaps ? h('a', { class: 'button', href: pageLink('snn-analytics-heatmaps') + '&pid=' + row.id }, 'Click heatmap') : null,
					!cfg.can || cfg.can.compare ? h('a', { class: 'button', href: pageLink('snn-analytics-compare') + '&ids=' + row.id }, 'Compare with…') : null
				])
			], { sub: row.path, actions: close }));
			detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
			api('stats/page-series', Object.assign({}, rp, { id: row.id })).then(function (series) {
				lineChart(canvas.querySelector('canvas'), series.map(function (d) { return dayLabel(d.date); }), [
					{ label: 'Views', color: COLOR_VIEWS, data: series.map(function (d) { return d.views; }), fill: true },
					{ label: 'Visitors', color: COLOR_VISITORS, data: series.map(function (d) { return d.visitors; }), dash: [4, 3] }
				]);
			}).catch(function (err) {
				detail.appendChild(failed(err));
			});
		}

		load();
	}

	function renderReferrers(rp) {
		var body = h('div', {}, skeleton(6));
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(body);

		api('stats/referrers', rp).then(function (data) {
			clear(body);
			var types = Object.keys(data.types);
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Channels', breakdown(
					types.map(function (t) { return CHANNEL_TYPES[t] || 'Other'; }),
					types.map(function (t) { return data.types[t]; })
				), { sub: rangeLabel(rp) }),
				card('How sources are grouped', [
					h('p', { class: 'snn-a-muted', text: 'Direct means no referrer was sent, for example a typed URL or a bookmark. Search, social, referral, ad and email come from the referring domain.' })
				])
			]));
			body.appendChild(card('Domains', table(['Source', 'Channel', numCol('Sessions'), numCol('Visitors'), numCol('Views'), numCol('Bounce rate')], data.domains.map(function (d) {
				return [
					d.domain,
					CHANNEL_TYPES[d.type] || 'Other',
					n(d.sessions),
					n(d.visitors),
					n(d.views),
					d.sessions ? pct(100 * d.bounces / d.sessions) : '–'
				];
			}), 'No referrers in this range.'), { sub: 'Sorted by sessions' }));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err, render));
		});
	}

	function renderDevices(rp) {
		var body = h('div', {}, skeleton(6));
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(body);

		api('stats/devices', rp).then(function (data) {
			clear(body);
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Device types', breakdown(
					data.devices.map(function (d) { return d.label; }),
					data.devices.map(function (d) { return d.sessions; })
				), { sub: rangeLabel(rp) }),
				card('Browsers', table(['Browser', numCol('Sessions'), numCol('Visitors')], data.browsers.map(function (b) {
					return [b.name, n(b.sessions), n(b.visitors)];
				})))
			]));
			body.appendChild(card('Operating systems', table(['OS', numCol('Sessions'), numCol('Visitors')], data.os.map(function (o) {
				return [o.name, n(o.sessions), n(o.visitors)];
			}))));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err, render));
		});
	}

	function campaignsPanel(rows, rp) {
		return h('div', {}, [
			h('p', { class: 'snn-a-lead', text: 'Add a few tags to the links you share (emails, social posts, ads) and this table shows which ones bring visitors and conversions. Start with the link builder below.' }),
			card('Link builder', urlBuilder(), {
				sub: 'Fill in the form, copy the link, and use it wherever you share your page.'
			}),
			card('Campaign results', table(['Source', 'Medium', 'Campaign', 'Term', 'Content', numCol('Sessions'), numCol('Visitors'), numCol('Conversions')], rows.map(function (r) {
				return [
					isTagged(r) ? (r.source || '–') : h('span', { class: 'snn-a-muted', text: 'Untagged visits' }),
					r.medium,
					r.campaign,
					r.term,
					r.content,
					n(r.sessions),
					n(r.visitors),
					n(r.conversions)
				];
			}), 'No visits in this range.'), { sub: rangeLabel(rp) })
		]);
	}

	function urlBuilder() {
		var fields = [
			['url', 'Destination URL', 'url', 'https://example.com/landing/', true, 'The page on your site that the link should open. Paste the full address, starting with https://'],
			['utm_source', 'Source (required)', 'text', 'newsletter', false, 'Where you will share the link, such as newsletter, facebook or google.'],
			['utm_medium', 'Medium', 'text', 'email', false, 'The kind of channel: email, social, cpc (paid ads) or banner.'],
			['utm_campaign', 'Campaign (required)', 'text', 'spring-sale', false, 'A name for this promotion. Reports group visits by this name.'],
			['utm_term', 'Term (optional)', 'text', '', false, 'Only for paid search: the keyword you bid on.'],
			['utm_content', 'Content (optional)', 'text', '', false, 'Tells apart two links in the same campaign, such as header-button and footer-link.']
		];
		var inputs = {};
		var grid = h('div', { class: 'snn-a-form-grid' });
		fields.forEach(function (f) {
			inputs[f[0]] = h('input', { type: f[2], placeholder: f[3], 'aria-label': f[1], autocomplete: 'off' });
			grid.appendChild(h('label', { class: 'snn-a-field' + (f[4] ? ' is-full' : '') }, [h('span', {}, [f[1], tip(f[5])]), inputs[f[0]]]));
		});

		var hint = h('p', { class: 'snn-a-hint', role: 'status' });
		var output = h('input', { type: 'text', readonly: true, 'aria-label': 'Tagged URL', placeholder: 'Your tagged URL appears here' });
		var copy = h('button', { type: 'button', class: 'button', disabled: true }, 'Copy');

		function clean(value) {
			return value.trim().toLowerCase().replace(/\s+/g, '-');
		}

		function build() {
			var raw = inputs.url.value.trim();
			output.value = '';
			copy.disabled = true;
			if (!raw) {
				hint.textContent = 'Enter the page you want to tag.';
				return;
			}
			var target;
			try {
				target = new URL(raw);
			} catch (e) {
				hint.textContent = 'Enter a full address that starts with https://';
				return;
			}
			if (!inputs.utm_source.value.trim() || !inputs.utm_campaign.value.trim()) {
				hint.textContent = 'Add a source and a campaign name.';
				return;
			}
			Object.keys(inputs).forEach(function (key) {
				if (key !== 'url' && inputs[key].value.trim() !== '') {
					target.searchParams.set(key, clean(inputs[key].value));
				}
			});
			output.value = target.toString();
			copy.disabled = false;
			hint.textContent = 'Values are lowercased and spaces become dashes, so campaign names stay consistent.';
		}

		copy.addEventListener('click', function () {
			var label = copy.textContent;
			var done = function () {
				copy.textContent = 'Copied';
				window.setTimeout(function () { copy.textContent = label; }, 1500);
			};
			if (navigator.clipboard) {
				navigator.clipboard.writeText(output.value).then(done, function () { output.select(); });
			} else {
				output.select();
			}
		});

		Object.keys(inputs).forEach(function (key) {
			inputs[key].addEventListener('input', build);
		});
		build();

		// Quick start: fills in where the link will be shared, so only the destination and a campaign name are left.
		var about = h('div', { class: 'snn-a-explain', 'aria-live': 'polite', hidden: true });
		var presets = h('div', { class: 'snn-a-presets' }, CAMPAIGN_PRESETS.map(function (p) {
			return h('button', { type: 'button', class: 'button', 'data-tip': 'Sets source “' + p[1] + '” and medium “' + p[2] + '”.', onclick: function () {
				inputs.utm_source.value = p[1];
				inputs.utm_medium.value = p[2];
				build();
				explain(about, {
					what: 'Tags ' + p[3] + ' with source “' + p[1] + '” and medium “' + p[2] + '”. Visits from it appear under that name in the results table.',
					how: 'Enter your destination page, give the campaign a name such as spring-sale, then copy the finished link and paste it where you share it.',
					why: 'Without a tag, these visits are mixed in with everyone else. With it you can see how many came from here and how many converted.'
				});
				about.hidden = false;
				(inputs.url.value.trim() ? inputs.utm_campaign : inputs.url).focus();
			} }, p[0]);
		}));

		return h('div', { class: 'snn-a-builder' }, [
			h('p', { class: 'snn-a-presets-label' }, ['Quick start: where will you share the link?', tip('Pick a place and the source and medium are filled in for you.')]),
			presets,
			about,
			grid,
			h('div', { class: 'snn-a-output' }, [output, copy]),
			hint
		]);
	}

	function renderJourneys(rp) {
		var state = { page: 1 };
		var results = h('div', {}, skeleton(6));
		var selected = null;

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(results);

		function load() {
			clear(results);
			results.appendChild(skeleton(6));
			api('stats/journeys', Object.assign({}, rp, state, { per_page: PER_PAGE })).then(function (data) {
				clear(results);
				selected = null;
				var pages = Math.max(1, Math.ceil(data.total / PER_PAGE));
				results.appendChild(h('p', { class: 'snn-a-muted', text: n(data.total) + ' sessions. Select a row to see every page in that visit.' }));
				if (!data.rows.length) {
					results.appendChild(empty('No sessions in this range.'));
					return;
				}
				results.appendChild(table(
					['Started', 'Visitor', 'Entry page', 'Exit page', 'Source', 'Country', 'Device', numCol('Views'), numCol('Active time')],
					data.rows.map(function (s) {
						return [
							started(s.started_at),
							h('span', { class: 'snn-a-badge ' + (s['new'] ? 'is-new' : ''), text: s['new'] ? 'New' : 'Returning' }),
							s.entry,
							s.exit,
							s.referrer,
							h('span', { class: 'snn-a-country' }, [flag(s.country), countryName(s.country)]),
							DEVICE_LABELS[s.device] || 'Other',
							n(s.views),
							duration(s.duration)
						];
					}),
					'No sessions in this range.',
					function (index, tr) {
						clearSelection();
						tr.classList.add('is-selected');
						selected = tr;
						showJourney(data.rows[index].id);
					}
				));
				results.appendChild(pager(state.page, pages, function (page) { state.page = page; load(); }));
			}).catch(function (err) {
				clear(results);
				results.appendChild(failed(err, load));
			});
		}

		function clearDetail() {
			Array.prototype.forEach.call(results.querySelectorAll('tr.snn-a-detail-row'), function (el) { el.parentNode.removeChild(el); });
		}

		function clearSelection() {
			clearDetail();
			Array.prototype.forEach.call(results.querySelectorAll('tr.is-selected'), function (el) { el.classList.remove('is-selected'); });
			selected = null;
		}

		function clock(at) {
			return new Date(at * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
		}

		/** Label and value pairs in a compact grid, for the visitor facts at the top of a visit. */
		function facts(items) {
			return h('dl', { class: 'snn-a-facts' }, items.filter(Boolean).map(function (item) {
				return h('div', {}, [h('dt', { text: item[0] }), h('dd', {}, item[1])]);
			}));
		}

		function action(event) {
			return h('li', {}, [
				h('span', { class: 'snn-a-time', text: clock(event.at) }),
				h('span', { text: event.label + ': ' }),
				h('strong', { text: event.value })
			]);
		}

		/** The visit opens as a full-width row directly under the session that was clicked. */
		function showJourney(id) {
			if (!selected) {
				return;
			}
			clearDetail();
			var cell = h('td', { class: 'snn-a-detail-cell', colspan: String(selected.cells.length) }, skeleton(5));
			selected.parentNode.insertBefore(h('tr', { class: 'snn-a-detail-row' }, cell), selected.nextSibling);
			selected.scrollIntoView({ behavior: 'smooth', block: 'start' });
			api('stats/journey', { id: id }).then(function (data) {
				clear(cell);
				var s = data.session;
				var v = data.visitor;
				var close = h('span', { class: 'snn-a-linkrow' }, [
					h('a', { class: 'button', href: pageLink('snn-analytics-replay') + '&session=' + s.id }, 'Replay visit'),
					h('button', { type: 'button', class: 'button-link', onclick: clearSelection }, 'Close')
				]);
				var place = [s.city, s.region].filter(Boolean).join(', ');
				var campaign = [s.campaign.source, s.campaign.medium, s.campaign.campaign].filter(Boolean).join(' / ');
				var device = deviceLine(s);
				var steps = data.steps.map(function (step, index) {
					return h('li', { class: 'snn-a-hist-step' }, [
						h('div', { class: 'snn-a-hist-time' }, [
							h('span', { class: 'snn-a-time', text: clock(step.at) }),
							h('span', { class: 'snn-a-hist-offset', text: '+' + duration(step.at - s.started_at) })
						]),
						h('div', { class: 'snn-a-hist-body' }, [
							h('div', { class: 'snn-a-hist-title', text: step.title || step.path }),
							h('div', { class: 'snn-a-hist-path', text: step.path }),
							h('div', { class: 'snn-a-hist-meta' }, [
								h('span', { text: 'On page ' + duration(step.duration) }),
								index === 0 ? h('span', { class: 'snn-a-badge', text: 'Entry' }) : null,
								index === data.steps.length - 1 ? h('span', { class: 'snn-a-badge', text: 'Exit' }) : null,
								step.has_trail ? h('span', { class: 'snn-a-badge', text: 'Cursor trail' }) : null
							]),
							step.events.length ? h('ul', { class: 'snn-a-hist-events' }, step.events.map(action)) : null
						])
					]);
				});
cell.appendChild(card('Visit on ' + when(s.started_at), [
					h('p', { class: 'snn-a-stat-row' }, [
						h('span', {}, [h('strong', { text: duration(s.duration) }), ' active time']),
						h('span', {}, [h('strong', { text: duration(s.length) }), ' session length']),
						h('span', {}, [h('strong', { text: String(s.views) }), ' views']),
						h('span', {}, [h('strong', { text: String(s.events) }), ' events']),
						s.bounce ? h('span', { class: 'snn-a-badge', text: 'Bounced' }) : null
					]),
					h('h3', { class: 'snn-a-subhead', text: 'Visitor' }),
						facts([
						['Visitor', (s.new ? 'New visitor' : 'Returning visitor') + ' · ' + v.sessions + (v.sessions === 1 ? ' session' : ' sessions')],
						['Came from', s.referrer + (campaign ? ' · ' + campaign : '')],
							['First seen', v.first_seen ? when(v.first_seen) : '–'],
						['Location', h('span', { class: 'snn-a-country' }, [flag(s.country), countryName(s.country) + (place ? ' · ' + place : '')])],
						['Device', device],
						s.screen ? ['Screen', s.screen[0] + ' × ' + s.screen[1] + ' px'] : null,
						s.viewport ? ['Browser window', s.viewport[0] + ' × ' + s.viewport[1] + ' px'] : null,
						s.language ? ['Language', s.language] : null,
						['Entry page', s.entry],
						['Exit page', s.exit]
					]),
					h('h3', { class: 'snn-a-subhead', text: 'History' }),
					h('ol', { class: 'snn-a-hist' }, steps),
					data.unattached.length ? h('h3', { class: 'snn-a-subhead', text: 'Actions without a page view' }) : null,
					data.unattached.length ? h('ul', { class: 'snn-a-hist-events' }, data.unattached.map(action)) : null,
					data.others.length ? h('h3', { class: 'snn-a-subhead', text: 'Other visits from this visitor' }) : null,
					data.others.length ? h('ul', { class: 'snn-a-plain' }, data.others.map(function (o) {
						return h('li', {}, [
							h('a', { href: '#', onclick: function (event) { event.preventDefault(); showJourney(o.id); } }, when(o.started_at)),
							h('span', { class: 'snn-a-muted', text: ' · ' + o.views + ' views · ' + duration(o.duration) })
						]);
					})) : null
				], { actions: close }));
			}).catch(function (err) {
				clear(cell);
				cell.appendChild(failed(err));
			});
		}

		load();
	}

	/**
	 * Conversion Tracking: goals, funnels and campaigns on one screen. A conversion is a visit
	 * that did something you care about. The tabs share one data load and a short setup list
	 * walks new users through the order to do things in.
	 */
	function renderConversions(rp) {
		var TABS = [
			['goals', 'Goals', 'The actions you want visitors to take, such as reaching a thank-you page or clicking a button.'],
			['funnels', 'Funnels', 'A series of goals in order. Shows how many visitors reach each step and where they drop off.'],
			['campaigns', 'Campaigns', 'Tag the links you share to see which emails, posts and ads bring visitors and conversions.']
		];
		var tab = new URLSearchParams(window.location.search).get('tab');
		if (!TABS.some(function (t) { return t[0] === tab; })) {
			tab = 'goals';
		}

		var data = null;
		var nav = h('div', { class: 'snn-a-segment snn-a-subtabs', role: 'group', 'aria-label': 'Conversion tracking sections' });
		var panel = h('div', {}, skeleton(6));

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(nav);
		root.appendChild(panel);

		function pick(next) {
			tab = next;
			var url = new URL(window.location.href);
			url.searchParams.set('tab', next);
			window.history.replaceState(null, '', url.toString());
			draw();
		}

		function drawNav() {
			clear(nav);
			TABS.forEach(function (t) {
				var active = tab === t[0];
				nav.appendChild(h('button', {
					type: 'button',
					class: 'snn-a-seg' + (active ? ' is-active' : ''),
					'aria-pressed': active ? 'true' : 'false',
					'data-tip': t[2],
					onclick: function () { pick(t[0]); }
				}, t[1]));
			});
		}

		function draw() {
			drawNav();
			clear(panel);
			if (tab === 'funnels') {
				panel.appendChild(funnelsPanel(data.goals, data.funnels, rp, load, function () { pick('goals'); }));
			} else if (tab === 'campaigns') {
				panel.appendChild(campaignsPanel(data.campaigns, rp));
			} else {
				panel.appendChild(goalsPanel(data.goals, load));
			}
		}

		function load() {
			Promise.all([api('goals', rp), api('funnels', rp), api('stats/campaigns', rp)]).then(function (results) {
				data = { goals: results[0], funnels: results[1], campaigns: results[2] };
				draw();
			}).catch(function (err) {
				clear(panel);
				panel.appendChild(failed(err, load));
			});
		}

		drawNav();
		load();
	}

	function goalsPanel(goals, reload) {
		return card('Goals', [
			h('p', { class: 'snn-a-lead', text: 'A goal is something you want visitors to do. Each visit counts once per goal, no matter how often it happens.' }),
			goalsTable(goals, reload),
			goalForm(reload, !goals.length)
		], { sub: goals.length ? null : 'Add your first goal below.' });
	}

	function funnelsPanel(goals, funnels, rp, reload, toGoals) {
		var lead = h('p', { class: 'snn-a-lead', text: 'A funnel follows visitors through several goals in order, for example Viewed product, Added to cart, Paid. It shows how many make it to each step.' });
		if (goals.length < 2) {
			return card('Funnels', [
				lead,
				funnelTemplates(goals, reload),
				h('p', { class: 'snn-a-hint' }, [
					'Prefer to choose your own steps? Funnels are built from goals, so you need at least two. ',
					h('button', { type: 'button', class: 'button-link', onclick: toGoals }, 'Create goals'),
					'.'
				])
			]);
		}
		return card('Funnels', [lead, funnelList(funnels, rp, reload), funnelTemplates(goals, reload), funnelForm(goals, reload, false)], {
			sub: 'Steps must happen in order, within the time window.'
		});
	}

	/**
	 * The ways a goal can be defined, in plain language. Each one maps onto the three rule types the
	 * tracker understands (page path, click selector, custom event), so no server change is needed.
	 * `fixed` kinds ship their own selector and ask the user for nothing.
	 */
	var DOWNLOAD_SELECTOR = ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'mp3', 'mp4'].map(function (ext) {
		return 'a[href$=".' + ext + '" i]';
	}).join(', ');

	var GOAL_KINDS = [
		{
			key: 'page_exact', group: 'Page visits', label: 'Someone visits one specific page', short: 'Visits a page',
			type: 1, match: 1, valueLabel: 'Page address', placeholder: '/thank-you/',
			what: 'Counts a visit when someone opens exactly the page you enter, for example /thank-you/.',
			how: 'Type the part of the address after your domain. For https://example.com/thank-you/ enter /thank-you/.',
			why: 'The simplest and most reliable goal. Pages like “thank you for your order” are only reached after someone finishes something.'
		},
		{
			key: 'page_contains', group: 'Page visits', label: 'Someone visits any page whose address contains a word', short: 'Visits a page containing',
			type: 1, match: 2, valueLabel: 'Word in the address', placeholder: '/thank',
			what: 'Counts a visit when the page address contains your text anywhere. “/thank” covers /thank-you/, /thanks/ and /thank-you-order/.',
			how: 'Enter a short piece that is the same on every page you want to count, such as /checkout or /product/.',
			why: 'Good when the finish line has several addresses, or when you are not sure of the exact address.'
		},
		{
			key: 'page_prefix', group: 'Page visits', label: 'Someone visits any page inside a section', short: 'Visits a section',
			type: 1, match: 3, valueLabel: 'Section address', placeholder: '/blog/',
			what: 'Counts a visit when the page address starts with your text, so every page inside that section counts.',
			how: 'Enter the start of the address, such as /blog/ for all posts or /shop/ for all products.',
			why: 'Shows how many visits reach a whole area of your site, for example “read at least one blog post”.'
		},
		{
			key: 'click_button', group: 'Clicks', label: 'Someone clicks a specific button or link', short: 'Clicks an element',
			type: 2, match: 1, valueLabel: 'Which element? (CSS selector)', placeholder: '.buy-button or a[href*="checkout"]',
			what: 'Counts a visit when someone clicks an element that matches your CSS selector.',
			how: 'Enter a class, an ID or a link pattern: .buy-button for a class, #signup for an ID, a[href*="pricing"] for any link to a pricing page. Not sure? Right-click the button, choose Inspect and look for its class.',
			why: 'Use it for actions that do not load a new page, like “Add to cart” or “Open chat”.'
		},
		{
			key: 'click_phone', group: 'Clicks', label: 'Someone clicks a phone number', short: 'Clicks a phone number',
			type: 2, match: 1, fixed: 'a[href^="tel:"]',
			what: 'Counts a visit when someone taps or clicks a phone number link on your site.',
			how: 'Nothing to enter. It works for every phone link that starts with tel:, which most themes and page builders create.',
			why: 'On mobile, a tap on your number is often a lead. This tells you which visits turn into calls.'
		},
		{
			key: 'click_email', group: 'Clicks', label: 'Someone clicks an email address', short: 'Clicks an email address',
			type: 2, match: 1, fixed: 'a[href^="mailto:"]',
			what: 'Counts a visit when someone clicks an email address link that opens their mail app.',
			how: 'Nothing to enter. It works for every link that starts with mailto:.',
			why: 'Shows how many visitors reach out by email without filling in a form.'
		},
		{
			key: 'click_download', group: 'Clicks', label: 'Someone downloads a file', short: 'Downloads a file',
			type: 2, match: 1, fixed: DOWNLOAD_SELECTOR,
			what: 'Counts a visit when someone clicks a link to a file: PDF, Word, Excel, PowerPoint, ZIP, CSV, MP3 or MP4.',
			how: 'Nothing to enter. Any link ending in one of those file types counts.',
			why: 'See which visits grab your brochures, price lists or guides.'
		},
		{
			key: 'click_outbound', group: 'Clicks', label: 'Someone clicks a link to another website', short: 'Clicks an outbound link',
			type: 2, match: 1, fixed: 'a[href^="http"]:not([href*="' + window.location.hostname + '"])',
			what: 'Counts a visit when someone clicks a link that leads away from your own site.',
			how: 'Nothing to enter. Links to this site (' + window.location.hostname + ') are ignored.',
			why: 'Useful when sending people to a partner, a booking system or a shop is the whole point of your page.'
		},
		{
			key: 'click_submit', group: 'Clicks', label: 'Someone submits a form', short: 'Submits a form',
			type: 2, match: 1, fixed: 'form [type="submit"], form button:not([type="button"]):not([type="reset"])',
			what: 'Counts a visit when someone clicks the submit button of any form on your site.',
			how: 'Nothing to enter. It counts the click on the button, so a form that fails validation can still count. For an exact count, use a thank-you page goal instead.',
			why: 'A quick way to measure contact, sign-up and newsletter forms when you have no thank-you page.'
		},
		{
			key: 'event', group: 'Advanced', label: 'My site sends a custom event', short: 'Custom event',
			type: 3, match: 1, valueLabel: 'Event name', placeholder: 'signup',
			what: 'Counts a visit when your own code sends an event with this exact name.',
			how: 'Add window.snnA.track("signup") to your code at the moment the action succeeds, then enter the same name here: signup.',
			why: 'The most precise option, for things the tracker cannot see by itself, such as a payment that went through.'
		}
	];

	/** Quick start buttons: [button label, kind key, value (for kinds that need one), goal name]. */
	var GOAL_PRESETS = [
		['Pages', [
			['Thank-you page', 'page_contains', '/thank', 'Thank-you page visited'],
			['Shop page', 'page_prefix', '/shop', 'Shop visited'],
			['Order received (WooCommerce)', 'page_contains', '/order-received', 'Order completed'],
			['Checkout page', 'page_contains', '/checkout', 'Checkout visited'],
			['Cart page', 'page_contains', '/cart', 'Cart visited'],
			['Contact page', 'page_contains', '/contact', 'Contact page visited'],
			['Pricing page', 'page_contains', '/pricing', 'Pricing viewed'],
			['Sign-up or register page', 'page_contains', '/regist', 'Sign-up page visited'],
			['Booking page', 'page_contains', '/book', 'Booking page visited'],
			['Any blog post', 'page_prefix', '/blog/', 'Read a blog post']
		]],
		['Clicks and actions', [
			['Phone number', 'click_phone', '', 'Phone number clicked'],
			['Email address', 'click_email', '', 'Email address clicked'],
			['File download', 'click_download', '', 'File downloaded'],
			['Link to another site', 'click_outbound', '', 'Outbound link clicked'],
			['Form submit', 'click_submit', '', 'Form submitted'],
			['Add to cart (WooCommerce)', 'click_button', '.add_to_cart_button, .single_add_to_cart_button', 'Added to cart'],
			['WhatsApp link', 'click_button', 'a[href*="wa.me"], a[href*="whatsapp.com"]', 'WhatsApp clicked'],
			['Social media link', 'click_button', 'a[href*="facebook.com"], a[href*="instagram.com"], a[href*="linkedin.com"], a[href*="twitter.com"], a[href*="youtube.com"]', 'Social link clicked']
		]]
	];

	/** Ready-made funnels. Steps name entries in GOAL_PRESETS; missing goals are created with the funnel. */
	var FUNNEL_TEMPLATES = [
		{
			label: 'Online purchase', name: 'Purchase',
			steps: ['Shop page', 'Add to cart (WooCommerce)', 'Checkout page', 'Order received (WooCommerce)'],
			what: 'Follows shoppers from browsing the shop to a finished order.',
			how: 'Works as is for WooCommerce. For another shop, change the page addresses afterwards by deleting and re-adding a goal.',
			why: 'Shows exactly where shoppers give up: before adding to the cart, at checkout, or while paying.'
		},
		{
			label: 'Contact request', name: 'Contact request',
			steps: ['Contact page', 'Thank-you page'],
			what: 'Follows visitors from the contact page to the thank-you page shown after sending the form.',
			how: 'Your form needs to send people to a page whose address contains “thank”.',
			why: 'Tells you how many people who open the contact page actually send a message.'
		},
		{
			label: 'Sign-up', name: 'Sign-up',
			steps: ['Sign-up or register page', 'Thank-you page'],
			what: 'Follows visitors from the sign-up page to the page they see once they are registered.',
			how: 'Registration should end on a page whose address contains “thank”.',
			why: 'A low rate here means the sign-up form is too long or confusing.'
		},
		{
			label: 'Blog reader to enquiry', name: 'Blog to enquiry',
			steps: ['Any blog post', 'Pricing page', 'Contact page'],
			what: 'Follows visitors from reading a blog post to the pricing page and then the contact page.',
			how: 'Works if your posts live under /blog/. Otherwise create your own goals first.',
			why: 'Shows whether your content brings in people who go on to become customers.'
		}
	];

	function presetSpec(label) {
		var found = null;
		GOAL_PRESETS.forEach(function (group) {
			group[1].forEach(function (p) {
				if (p[0] === label) { found = { label: p[0], kind: p[1], value: p[2], name: p[3] }; }
			});
		});
		return found;
	}

	/** The saved goal that already does what a preset does, if any. */
	function findGoalFor(goals, spec) {
		var k = kindByKey(spec.kind);
		return goals.filter(function (g) {
			return kindOf(g).key === spec.kind && (k.fixed || g.value === spec.value);
		})[0] || null;
	}

	function funnelTemplates(goals, onSaved) {
		var detail = h('div');
		var buttons = h('div', { class: 'snn-a-presets' }, FUNNEL_TEMPLATES.map(function (t) {
			return h('button', { type: 'button', class: 'button', 'data-tip': t.what, onclick: function () { choose(t); } }, t.label);
		}));

		function choose(t) {
			var error = formMessage();
			var go = h('button', { type: 'button', class: 'button button-primary' }, 'Create this funnel');
			var specs = t.steps.map(presetSpec);
			var box = h('div', { class: 'snn-a-explain' });
			explain(box, t);
			box.appendChild(h('p', {}, h('strong', { text: 'Steps, in order:' })));
			box.appendChild(h('ol', {}, specs.map(function (spec) {
				var have = findGoalFor(goals, spec);
				return h('li', {}, [spec.name, h('span', { class: 'snn-a-muted', text: have ? ' (goal already exists)' : ' (goal will be created)' })]);
			})));
			go.addEventListener('click', function () {
				go.disabled = true;
				error.textContent = '';
				var ids = [];
				var chain = specs.reduce(function (p, spec) {
					return p.then(function () {
						var have = findGoalFor(goals, spec);
						if (have) {
							ids.push(have.id);
							return null;
						}
						var k = kindByKey(spec.kind);
						return send('goals', { name: spec.name, type: k.type, match: k.match, value: k.fixed || spec.value }).then(function (res) { ids.push(res.id); });
					});
				}, Promise.resolve());
				chain.then(function () {
					return send('funnels', { name: t.name, window_days: 7, steps: ids });
				}).then(onSaved).catch(function (err) {
					go.disabled = false;
					error.textContent = err.message;
				});
			});
			clear(detail);
			detail.appendChild(box);
			detail.appendChild(error);
			detail.appendChild(h('p', {}, go));
		}

		return h('div', {}, [
			h('p', { class: 'snn-a-presets-label' }, ['Quick start: pick a funnel', tip('Each button builds a typical funnel for you, creating any goals it needs. You get a preview first.')]),
			buttons,
			detail
		]);
	}

	var CAMPAIGN_PRESETS = [
		['Newsletter', 'newsletter', 'email', 'a link inside an email newsletter'],
		['Email signature', 'email-signature', 'email', 'a link in your email signature'],
		['Facebook post', 'facebook', 'social', 'a link in an unpaid Facebook post'],
		['Instagram bio or story', 'instagram', 'social', 'the link in your Instagram bio or a story'],
		['LinkedIn post', 'linkedin', 'social', 'a link in a LinkedIn post or profile'],
		['X (Twitter) post', 'twitter', 'social', 'a link in a post on X'],
		['YouTube description', 'youtube', 'video', 'a link in a YouTube video description'],
		['Google Ads', 'google', 'cpc', 'the destination link of a Google Ads advertisement'],
		['Facebook or Instagram ads', 'facebook', 'paid-social', 'the link of a paid ad on Facebook or Instagram'],
		['QR code on print', 'qr-code', 'print', 'a link behind a QR code on a flyer, poster or business card'],
		['Partner website', 'partner', 'referral', 'a link you give to a partner or sponsor']
	];

	function kindByKey(key) {
		return GOAL_KINDS.filter(function (k) { return k.key === key; })[0];
	}

	/** Works out which kind a saved goal was created with. */
	function kindOf(g) {
		if (g.type === 1) {
			return kindByKey({ 1: 'page_exact', 2: 'page_contains', 3: 'page_prefix' }[g.match] || 'page_exact');
		}
		if (g.type === 3) {
			return kindByKey('event');
		}
		return GOAL_KINDS.filter(function (k) { return k.fixed && k.fixed === g.value; })[0] || kindByKey('click_button');
	}

	/** One plain sentence about what a saved goal watches for. */
	function describeGoal(g) {
		var kind = kindOf(g);
		if (kind.key === 'page_exact') { return 'Visits ' + g.value; }
		if (kind.key === 'page_contains') { return 'Visits a page containing ' + g.value; }
		if (kind.key === 'page_prefix') { return 'Visits a page starting with ' + g.value; }
		if (kind.key === 'event') { return 'Custom event “' + g.value + '”'; }
		if (kind.key === 'click_button') { return 'Clicks ' + g.value; }
		return kind.short;
	}

	function goalsTable(goals, reload) {
		if (!goals.length) {
			return null;
		}
		return table(['Goal', 'Type', 'Rule', numCol('Conversions'), ''], goals.map(function (g) {
			var del = h('button', { type: 'button', class: 'button-link-delete', onclick: function () {
				if (window.confirm('Delete this goal and its conversions? This cannot be undone.')) {
					send('goals/delete', { id: g.id }).then(reload).catch(function (err) { window.alert(err.message); });
				}
			} }, 'Delete');
			return [g.name || 'Untitled goal', kindOf(g).short, describeGoal(g), n(g.conversions), del];
		}));
	}

	function formMessage() {
		return h('p', { class: 'snn-a-hint is-error', role: 'alert' });
	}

	/** Three short paragraphs under a choice: what it does, how to set it up, why you would use it. */
	function explain(box, info) {
		clear(box);
		[['What it counts', info.what], ['How to set it up', info.how], ['Why use it', info.why]].forEach(function (row) {
			if (row[1]) {
				box.appendChild(h('p', {}, [h('strong', { text: row[0] + ': ' }), row[1]]));
			}
		});
		if (info.technical) {
			box.appendChild(h('p', { class: 'snn-a-muted' }, ['Behind the scenes it watches for ', h('code', { text: info.technical }), '.']));
		}
	}

	function goalForm(onSaved, open) {
		var name = h('input', { type: 'text', placeholder: 'For example: Signed up' });
		var value = h('input', { type: 'text', placeholder: '/thank-you/', class: 'regular-text' });
		var kind = h('select', {}, ['Page visits', 'Clicks', 'Advanced'].map(function (group) {
			return h('optgroup', { label: group }, GOAL_KINDS.filter(function (k) { return k.group === group; }).map(function (k) {
				return h('option', { value: k.key }, k.label);
			}));
		}));
		var error = formMessage();
		var valueLabel = h('span');
		var valueField = h('label', { class: 'snn-a-field is-full' }, [h('span', {}, [valueLabel, tip('What the goal looks for: a page address, an element on the page, or an event name.')]), value]);
		var explanation = h('div', { class: 'snn-a-explain', 'aria-live': 'polite' });

		function current() {
			return kindByKey(kind.value);
		}

		function refresh() {
			var k = current();
			valueField.hidden = !!k.fixed;
			if (!k.fixed) {
				valueLabel.textContent = k.valueLabel;
				value.placeholder = k.placeholder;
			}
			explain(explanation, { what: k.what, how: k.how, why: k.why, technical: k.fixed || null });
		}

		kind.addEventListener('change', function () {
			value.value = '';
			refresh();
		});

		var form = h('details', { class: 'snn-a-add', open: open ? true : null });

		function usePreset(p) {
			kind.value = p[1];
			refresh();
			value.value = p[2];
			name.value = p[3];
			error.textContent = '';
			form.open = true;
			valueField.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}

		var presetBox = h('div', {}, GOAL_PRESETS.map(function (group) {
			return h('div', {}, [
				h('p', { class: 'snn-a-presets-sub', text: group[0] }),
				h('div', { class: 'snn-a-presets' }, group[1].map(function (p) {
					var k = kindByKey(p[1]);
					return h('button', { type: 'button', class: 'button', 'data-tip': k.what, onclick: function () { usePreset(p); } }, p[0]);
				}))
			]);
		}));

		[
			h('summary', {}, 'Add a goal'),
			h('p', { class: 'snn-a-presets-label' }, ['Quick start: pick one, then press Save', tip('Each button fills in the form below with a common goal. You can change anything before you save.')]),
			presetBox,
			h('div', { class: 'snn-a-form-grid' }, [
				h('label', { class: 'snn-a-field is-full' }, [h('span', {}, ['What counts as a conversion?', tip('Pick the kind of action. Visiting a page is the simplest and works for most sites.')]), kind]),
				h('div', { class: 'snn-a-field is-full' }, explanation),
				valueField,
				h('label', { class: 'snn-a-field is-full' }, [h('span', {}, ['Goal name (optional)', tip('A label for your reports. If you leave it empty the value above is used.')]), name])
			]),
			error,
			h('p', {}, h('button', { type: 'button', class: 'button button-primary', onclick: function () {
				error.textContent = '';
				var k = current();
				var goalValue = k.fixed || value.value.trim();
				if (!goalValue) {
					error.textContent = 'Enter a value for this goal.';
					return;
				}
				send('goals', {
					name: name.value.trim() || k.short,
					type: k.type,
					match: k.match,
					value: goalValue
				}).then(onSaved).catch(function (err) { error.textContent = err.message; });
			} }, 'Save goal'))
		].forEach(function (node) { form.appendChild(node); });

		refresh();

		return form;
	}

	function funnelList(funnels, rp, reload) {
		if (!funnels.length) {
			return null;
		}
		return h('div', { class: 'snn-a-funnels' }, funnels.map(function (f) {
			var holder = h('div', { class: 'snn-a-funnel' }, skeleton(3));
			api('funnels/report', Object.assign({}, rp, { id: f.id })).then(function (report) {
				clear(holder);
				var max = report.steps.length ? Math.max(1, report.steps[0].sessions) : 1;
				holder.appendChild(h('p', { class: 'snn-a-funnel-title' }, [
					h('strong', { text: f.name }),
					h('span', { class: 'snn-a-muted', text: ' · ' + f.window_days + ' day window' })
				]));
				report.steps.forEach(function (step, index) {
					holder.appendChild(h('div', { class: 'snn-a-step' }, [
						h('div', { class: 'snn-a-step-head' }, [
							h('span', { text: (index + 1) + '. ' + step.name }),
							h('span', { class: 'snn-a-muted', text: n(step.sessions) + ' · ' + pct(step.rate_from_start) + ' of start' + (index ? ' · ' + n(step.drop_from_prev) + ' dropped' : '') })
						]),
						h('div', { class: 'snn-a-bar-track' }, h('div', { class: 'snn-a-bar', style: 'width:' + Math.max(2, Math.round(100 * step.sessions / max)) + '%' }))
					]));
				});
			}).catch(function (err) {
				clear(holder);
				holder.appendChild(failed(err));
			});
			var del = h('button', { type: 'button', class: 'button-link-delete', onclick: function () {
				if (window.confirm('Delete this funnel?')) {
					send('funnels/delete', { id: f.id }).then(reload).catch(function (err) { window.alert(err.message); });
				}
			} }, 'Delete funnel');
			return h('div', { class: 'snn-a-funnel-wrap' }, [holder, h('p', { class: 'snn-a-funnel-actions' }, del)]);
		}));
	}

	function funnelForm(goals, onSaved, open) {
		var name = h('input', { type: 'text', placeholder: 'Funnel name, for example Purchase' });
		var days = h('input', { type: 'number', min: 1, max: 365, value: 7, 'aria-label': 'Window in days' });
		var error = formMessage();
		var steps = [];
		var stepBox = h('div', { class: 'snn-a-steps-form' });

		function addStep() {
			var select = h('select', {}, [h('option', { value: '' }, 'Choose a goal…')].concat(goals.map(function (g) {
				return h('option', { value: String(g.id) }, g.name || 'Untitled goal');
			})));
			steps.push(select);
			// Start with the goals in the order they were created, so a first funnel needs no picking.
			if (goals[steps.length - 1]) {
				select.value = String(goals[steps.length - 1].id);
			}
			var note = h('span', { class: 'snn-a-select-note' });
			function describe() {
				var picked = goals.filter(function (g) { return String(g.id) === select.value; })[0];
				note.textContent = picked
					? 'Counts visits that match: ' + describeGoal(picked) + '. The visitor must have done the earlier steps first.'
					: 'Choose the goal a visitor reaches at this step.';
			}
			select.addEventListener('change', describe);
			describe();
			stepBox.appendChild(h('label', { class: 'snn-a-field' }, [h('span', {}, ['Step ' + steps.length, steps.length === 1 ? tip('The first goal visitors reach. Each later step has to come after the one before it.') : null]), select, note]));
		}

		function removeStep() {
			if (steps.length > 2) {
				steps.pop();
				stepBox.removeChild(stepBox.lastChild);
			}
		}

		addStep();
		addStep();

		return h('details', { class: 'snn-a-add', open: open ? true : null }, [
			h('summary', {}, 'Add a funnel'),
			h('div', { class: 'snn-a-form-grid' }, [
				h('label', { class: 'snn-a-field' }, [h('span', {}, ['Name', tip('Any name that helps you recognise the funnel, such as Purchase or Sign-up.')]), name]),
				h('label', { class: 'snn-a-field' }, [h('span', {}, ['Window (days)', tip('How long a visitor has to get from one step to the next. 7 days is a good start.')]), days])
			]),
			stepBox,
			h('p', { class: 'snn-a-actions' }, [
				h('button', { type: 'button', class: 'button', 'data-tip': 'Add another goal to the end of the funnel (up to 8).', onclick: addStep }, 'Add step'),
				h('button', { type: 'button', class: 'button', 'data-tip': 'Remove the last step. A funnel keeps at least two.', onclick: removeStep }, 'Remove step')
			]),
			error,
			h('p', {}, h('button', { type: 'button', class: 'button button-primary', onclick: function () {
				error.textContent = '';
				var chosen = steps.map(function (s) { return Number(s.value); }).filter(Boolean);
				if (!name.value.trim()) {
					error.textContent = 'Give the funnel a name.';
					return;
				}
				if (chosen.length < 2) {
					error.textContent = 'Choose at least two goals.';
					return;
				}
				send('funnels', {
					name: name.value.trim(),
					window_days: Number(days.value),
					steps: chosen
				}).then(onSaved).catch(function (err) { error.textContent = err.message; });
			} }, 'Save funnel'))
		]);
	}

	// ------------------------------------------------------------ import

	var STAGE_NAMES = {
		countries: 'countries', cities: 'cities', resources: 'pages', referrers: 'referrers',
		campaigns: 'campaigns', device_types: 'device types', device_oss: 'operating systems',
		device_browsers: 'browsers', visitors: 'visitors', sessions: 'sessions', views: 'views',
		clicks: 'clicks', totals: 'totals', rollup: 'daily totals'
	};

	function renderImport() {
		var box = h('div', {}, skeleton(4));
		root.appendChild(box);
		var running = false;

		function paint(data) {
			clear(box);
			var d = data.detect;
			var s = data.status || {};

			if (!d.available) {
				box.appendChild(card('Independent Analytics', [
					empty('No Independent Analytics data was found on this site. Install and use that plugin first, then come back here.')
				]));
				return;
			}

			var busy = s.state === 'running';
			var done = s.state === 'done';
			var hasError = s.state === 'error';
			var stateLabel = busy ? 'Importing' : done ? 'Finished' : hasError ? 'Stopped' : 'Not started';

			box.appendChild(card('Independent Analytics data', [
				h('div', { class: 'snn-a-stat-row' }, [
					h('span', {}, [h('strong', { text: n(d.sessions) }), ' sessions']),
					h('span', {}, [h('strong', { text: n(d.views) }), ' views']),
					h('span', {}, [h('strong', { text: n(d.clicks) }), ' clicks'])
				]),
				h('p', { class: 'snn-a-muted', text: 'Counts are estimates. The importer only reads the old tables. It never changes or deletes them, and each step handles 5,000 rows so it works on shared hosting.' }),
				h('div', { class: 'snn-a-import-state' }, [
					h('span', { class: 'snn-a-badge ' + (done ? 'is-new' : hasError ? 'is-error' : ''), text: stateLabel }),
					busy && s.stage ? h('span', { class: 'snn-a-muted', text: ' Working on ' + (STAGE_NAMES[s.stage] || s.stage) + '…' }) : null
				]),
				busy ? h('progress', { class: 'snn-a-progress', 'aria-label': 'Import in progress' }) : null,
				s.message ? notice(s.message, hasError ? 'error' : 'info') : null,
				h('div', { class: 'snn-a-actions' }, [
					!busy && !done ? h('button', { type: 'button', class: 'button button-primary', disabled: cfg.canManage ? null : true, onclick: function () {
						send('import/start').then(function (st) { paint({ detect: d, status: st }); runLoop(); }).catch(function (err) { box.appendChild(notice(err.message, 'error')); });
					} }, hasError ? 'Resume import' : 'Start import') : null,
					busy ? h('button', { type: 'button', class: 'button', onclick: function () {
						send('import/cancel').then(function (st) { running = false; paint({ detect: d, status: st }); }).catch(function (err) { box.appendChild(notice(err.message, 'error')); });
					} }, 'Cancel') : null
				]),
				done ? h('p', { class: 'snn-a-muted', text: 'Already imported. Use “Delete all data” under Settings to import again.' }) : null
			]));

			if (busy && !running) {
				runLoop();
			}
		}

		// Each request handles one chunk, which keeps every request short.
		function runLoop() {
			if (running) {
				return;
			}
			running = true;
			(function next() {
				if (!running) {
					return;
				}
				send('import/step').then(function (st) {
					if (st.state === 'running') {
						api('import').then(function (data) {
							data.status = st;
							paint(data);
						});
						window.setTimeout(next, 50);
					} else {
						running = false;
						load();
					}
				}).catch(function (err) {
					running = false;
					load();
					box.appendChild(notice(err.message, 'error'));
				});
			}());
		}

		function load() {
			api('import').then(paint).catch(function (err) {
				clear(box);
				box.appendChild(failed(err, load));
			});
		}

		load();
	}

	// ----------------------------------------------------- settings tools

	function renderTools() {
		var geoBox = h('div', {}, skeleton(3));
		var totalsNote = h('div', {});
		var poll = null;

		if (cfg.canManage) {
			tools.appendChild(databaseCard());
		}

		tools.appendChild(card('City database', geoBox, { sub: 'DB-IP Lite, free and licensed CC BY 4.0. Adds city data to the Geographic and Journeys screens.' }));

		tools.appendChild(card('Daily totals', [
			h('p', { class: 'snn-a-muted', text: 'Rebuilds every daily total from the raw visits. Run this after importing data or changing the timezone.' }),
			totalsNote,
			h('div', { class: 'snn-a-actions' }, h('button', { type: 'button', class: 'button', disabled: cfg.canManage ? null : true, onclick: function () {
				clear(totalsNote);
				send('admin/rebuild').then(function (r) {
					totalsNote.appendChild(notice('Rebuilt ' + n(r.days) + ' days.', 'success'));
				}).catch(function (err) {
					totalsNote.appendChild(notice(err.message, 'error'));
				});
			} }, 'Rebuild daily totals'))
		], { sub: 'Maintenance' }));

		var confirmBox = h('input', { type: 'text', placeholder: 'Type DELETE', 'aria-label': 'Type DELETE to confirm', autocomplete: 'off' });
		var deleteNote = h('div', {});
		var deleteBtn = h('button', { type: 'button', class: 'button button-link-delete', disabled: true, onclick: function () {
			send('admin/delete-data', { confirm: confirmBox.value }).then(function () {
				deleteNote.appendChild(notice('All statistics were deleted. Reloading…', 'success'));
				window.setTimeout(function () { window.location.reload(); }, 1200);
			}).catch(function (err) {
				deleteNote.appendChild(notice(err.message, 'error'));
			});
		} }, 'Delete all data');
		confirmBox.addEventListener('input', function () {
			deleteBtn.disabled = confirmBox.value !== 'DELETE';
		});

		tools.appendChild(card('Delete all data', [
			h('p', { class: 'snn-a-danger-text', text: 'Deletes every visit, session, page, referrer, campaign and daily total. Goals, funnels and settings are kept.' }),
			h('div', { class: 'snn-a-actions' }, [confirmBox, deleteBtn]),
			deleteNote
		], { sub: 'This cannot be undone.' }));

		function paintGeo(state) {
			clear(geoBox);
			var busy = state.state === 'queued' || state.state === 'downloading';
			var installedText = state.installed
				? 'Installed, ' + Math.round(state.size / 1048576) + ' MB. New sessions get a city.'
				: 'Not installed. Countries still work from CDN headers, but cities need this file.';
			geoBox.appendChild(h('p', { text: installedText }));
			var detail = 'Source: ' + (state.source || 'download.db-ip.com') + ' (DB-IP Lite City, updated monthly).';
			if (state.installed && state.edition) {
				detail += ' Installed edition: ' + state.edition;
				if (state.installed_at) {
					detail += ', downloaded ' + new Date(state.installed_at * 1000).toLocaleDateString();
				}
				detail += '.';
			}
			geoBox.appendChild(h('p', { class: 'snn-a-muted', text: detail }));
			if (busy) {
				geoBox.appendChild(h('progress', { class: 'snn-a-progress', 'aria-label': 'Download in progress' }));
			}
			if (state.message) {
				geoBox.appendChild(notice(state.message, state.state === 'error' ? 'error' : 'info'));
			}
			geoBox.appendChild(h('div', { class: 'snn-a-actions' }, [
				h('button', { type: 'button', class: 'button button-primary', disabled: busy || !cfg.canManage ? true : null, onclick: function () {
					send('geo/download').then(function (s) { paintGeo(s); startPolling(); }).catch(function (err) { geoBox.appendChild(notice(err.message, 'error')); });
				} }, state.installed ? 'Update database' : 'Download database'),
				state.installed ? h('button', { type: 'button', class: 'button', disabled: busy ? true : null, onclick: function () {
					if (window.confirm('Remove the city database? Cities will stop being recorded.')) {
						send('geo/remove').then(paintGeo).catch(function (err) { geoBox.appendChild(notice(err.message, 'error')); });
					}
				} }, 'Remove') : null
			]));
		}

		function refresh() {
			return api('geo').then(function (state) {
				paintGeo(state);
				return state;
			}).catch(function (err) {
				clear(geoBox);
				geoBox.appendChild(failed(err, refresh));
			});
		}

		function startPolling() {
			if (poll) {
				return;
			}
			poll = window.setInterval(function () {
				refresh().then(function (state) {
					if (state && state.state !== 'queued' && state.state !== 'downloading') {
						window.clearInterval(poll);
						poll = null;
					}
				});
			}, 3000);
		}

		refresh().then(function (state) {
			if (state && (state.state === 'queued' || state.state === 'downloading')) {
				startPolling();
			}
		});
	}

	// ------------------------------------------------------------ compare

	var SLOT_COLORS = ['#2271b1', '#00a32a', '#dba617', '#8c5cd6'];
	var SLOT_LETTERS = ['A', 'B', 'C', 'D'];

	/**
	 * Pick two to four pages and see them side by side. For every measure the best page is marked,
	 * so it is clear which page does better. Pages are picked from the most viewed in the range.
	 */
	function renderCompare(rp) {
		var slots = (urlParam('ids') || '').split(',').map(Number).filter(Boolean).slice(0, 4);
		var chartMetric = 'views';
		var pickers = h('div', { class: 'snn-a-slots' });
		var results = h('div', {});
		var pageRows = [];

		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(h('p', { class: 'snn-a-caption', text: 'Pick two to four pages. The best value in each row is highlighted, and the arrows show the change against the previous period.' }));
		root.appendChild(pickers);
		root.appendChild(results);

		function save() {
			setUrlParam('ids', slots.join(','));
		}

		function drawPickers() {
			clear(pickers);
			slots.forEach(function (id, i) {
				var select = h('select', { 'aria-label': 'Page ' + SLOT_LETTERS[i], onchange: function () {
					slots[i] = Number(select.value);
					save();
					drawPickers();
					load();
				} }, pageRows.map(function (row) {
					return h('option', {
						value: String(row.id),
						text: (row.title || row.path) + ' · ' + row.path,
						selected: row.id === id ? true : null
					});
				}));
				pickers.appendChild(h('div', { class: 'snn-a-slot', style: 'border-color:' + SLOT_COLORS[i] }, [
					h('span', { class: 'snn-a-slot-badge', style: 'background:' + SLOT_COLORS[i], text: SLOT_LETTERS[i] }),
					select,
					slots.length > 2 ? h('button', { type: 'button', class: 'button-link', 'aria-label': 'Remove page ' + SLOT_LETTERS[i], onclick: function () {
						slots.splice(i, 1);
						save();
						drawPickers();
						load();
					} }, 'Remove') : null
				]));
			});
			if (slots.length < 4 && pageRows.length > slots.length) {
				pickers.appendChild(h('button', { type: 'button', class: 'button', onclick: function () {
					var next = pageRows.filter(function (row) { return slots.indexOf(row.id) === -1; })[0];
					slots.push(next.id);
					save();
					drawPickers();
					load();
				} }, '+ Add a page'));
			}
		}

		var measures = [
			{ key: 'views', label: 'Views', dir: 1, value: function (c) { return c.views; }, text: n },
			{ key: 'visitors', label: 'Visitors', dir: 1, value: function (c) { return c.visitors; }, text: n },
			{ key: 'avg_time', label: 'Avg. time on page', dir: 1, value: function (c) { return c.avg_time; }, text: duration },
			{ key: 'per_visitor', label: 'Views per visitor', dir: 1, value: function (c) { return c.visitors ? c.views / c.visitors : 0; }, text: function (v) { return (Math.round(v * 100) / 100).toString(); } },
			{ key: 'entrances', label: 'Entrances (landing page)', dir: 1, value: function (c) { return c.entrances; }, text: n },
			{ key: 'clicks', label: 'Clicks recorded', dir: 1, value: function (c) { return c.clicks || 0; }, text: n },
			{ key: 'exit_rate', label: 'Exit rate', dir: -1, value: function (c) { return c.views ? c.exits / c.views * 100 : 0; }, text: pct, points: true },
			{ key: 'exits', label: 'Exits', dir: 0, value: function (c) { return c.exits; }, text: n }
		];

		function paint(data) {
			clear(results);
			if (data.length < 2) {
				results.appendChild(empty('Choose at least two pages that have views in this range.'));
				return;
			}

			var wins = data.map(function () { return 0; });
			var contested = 0;

			var rows = measures.map(function (m) {
				var values = data.map(function (p) { return m.value(p.current); });
				var best = null;
				if (m.dir !== 0) {
					var top = m.dir > 0 ? Math.max.apply(null, values) : Math.min.apply(null, values);
					var holders = values.filter(function (v) { return v === top; }).length;
					// A tie, or a row where every page is 0, has no winner.
					if (holders === 1 && (m.dir < 0 || top > 0)) {
						best = values.indexOf(top);
						wins[best] += 1;
					}
					contested += 1;
				}
				return h('tr', {}, [h('th', { scope: 'row', text: m.label })].concat(data.map(function (p, i) {
					var prev = m.value(p.previous);
					var d = change(values[i], prev, { invert: m.dir < 0, points: !!m.points });
					return h('td', { class: 'num' + (best === i ? ' is-best' : '') }, [
						h('strong', { text: m.text(values[i]) }),
						best === i ? h('span', { class: 'snn-a-best', text: 'Best' }) : null,
						h('span', { class: 'snn-a-kpi-delta ' + d.cls, text: d.text })
					]);
				})));
			});

			var leader = wins.indexOf(Math.max.apply(null, wins));
			var tied = wins.filter(function (w) { return w === wins[leader]; }).length > 1;
			var verdict = tied || !wins[leader]
				? 'No page is clearly ahead in this period.'
				: SLOT_LETTERS[leader] + ' (' + (data[leader].title || data[leader].path) + ') leads on ' + wins[leader] + ' of ' + contested + ' measures.';

			results.appendChild(card('Side by side', [
				h('p', { class: 'snn-a-verdict', text: verdict }),
				h('div', { class: 'snn-a-scroll' }, h('table', { class: 'snn-a-table snn-a-compare' }, [
					h('thead', {}, h('tr', {}, [h('th', { scope: 'col', text: 'Measure' })].concat(data.map(function (p, i) {
						return h('th', { scope: 'col', class: 'num' }, [
							h('span', { class: 'snn-a-slot-badge', style: 'background:' + SLOT_COLORS[i], text: SLOT_LETTERS[i] }),
							h('span', { class: 'snn-a-compare-name', text: p.title || p.path }),
							h('span', { class: 'snn-a-muted', text: p.path })
						]);
					})))),
					h('tbody', {}, rows)
				]))
			], { sub: rangeLabel(rp) }));

			var canvas = chartBox(280);
			var seg = h('div', { class: 'snn-a-segment', role: 'group', 'aria-label': 'Chart measure' }, [['views', 'Views'], ['visitors', 'Visitors']].map(function (o) {
				return h('button', {
					type: 'button',
					class: 'snn-a-seg' + (chartMetric === o[0] ? ' is-active' : ''),
					'aria-pressed': chartMetric === o[0] ? 'true' : 'false',
					onclick: function () { chartMetric = o[0]; paint(data); }
				}, o[1]);
			}));
			results.appendChild(card('Over time', canvas, { sub: 'Per day', actions: seg }));
			charts.splice(0).forEach(function (chart) { chart.destroy(); });
			lineChart(canvas.querySelector('canvas'), data[0].series.map(function (d) { return dayLabel(d.date); }), data.map(function (p, i) {
				return { label: SLOT_LETTERS[i] + ' · ' + (p.title || p.path), color: SLOT_COLORS[i], data: p.series.map(function (d) { return d[chartMetric]; }) };
			}));
		}

		function load() {
			if (slots.length < 2) {
				clear(results);
				results.appendChild(empty('Choose at least two pages that have views in this range.'));
				return;
			}
			clear(results);
			results.appendChild(skeleton(6));
			api('stats/compare', Object.assign({}, rp, { ids: slots.join(',') })).then(paint).catch(function (err) {
				clear(results);
				results.appendChild(failed(err, load));
			});
		}

		api('stats/pages', Object.assign({}, rp, { orderby: 'views', order: 'desc', per_page: 100 })).then(function (data) {
			pageRows = data.rows;
			var known = pageRows.map(function (row) { return row.id; });
			slots = slots.filter(function (id, i) { return known.indexOf(id) !== -1 && slots.indexOf(id) === i; });
			for (var i = 0; slots.length < 2 && i < pageRows.length; i++) {
				if (slots.indexOf(pageRows[i].id) === -1) {
					slots.push(pageRows[i].id);
				}
			}
			if (pageRows.length < 2) {
				results.appendChild(empty('Comparing needs at least two pages with views in this range.'));
				return;
			}
			save();
			drawPickers();
			load();
		}).catch(function (err) {
			results.appendChild(failed(err));
		});
	}

	// ----------------------------------------------------------- database

	function bytesText(bytes) {
		if (bytes === null || bytes === undefined) {
			return 'n/a';
		}
		if (bytes < 1024 * 1024) {
			return (Math.round(bytes / 102.4) / 10) + ' KB';
		}
		if (bytes < 1024 * 1024 * 1024) {
			return (Math.round(bytes / 104857.6) / 10) + ' MB';
		}
		return (Math.round(bytes / 107374182.4) / 10) + ' GB';
	}

	/** Size and row count of every table, and how the total has grown day by day. */
	function databaseCard() {
		var body = h('div', {}, skeleton(5));

		function paint(data) {
			clear(body);
			var history = data.history || [];
			var sized = data.total_bytes !== null;
			var measure = sized ? 'bytes' : 'rows';
			var first = history[0];
			var last = history[history.length - 1];
			var span = history.length > 1
				? Math.max(1, Math.round((Date.parse(last.date) - Date.parse(first.date)) / 86400000))
				: 0;
			var perDay = span ? (last[measure] - first[measure]) / span : null;

			var facts = [
				['Total size', sized ? bytesText(data.total_bytes) : 'Not reported by this database'],
				['Total rows', n(data.total_rows)],
				['Oldest visit', data.oldest_view ? new Date(data.oldest_view * 1000).toLocaleDateString() : 'None yet'],
				['Growth per day', perDay === null ? 'Needs two days of history' : (sized ? bytesText(Math.max(0, perDay)) : n(Math.max(0, Math.round(perDay))) + ' rows')]
			];
			if (perDay !== null && sized && perDay > 0 && data.total_bytes) {
				facts.push(['In 12 months', '≈ ' + bytesText(data.total_bytes + perDay * 365)]);
			}

			body.appendChild(h('dl', { class: 'snn-a-facts' }, facts.map(function (f) {
				return h('div', {}, [h('dt', { text: f[0] }), h('dd', { text: f[1] })]);
			})));

			if (history.length > 1) {
				var canvas = chartBox(180);
				body.appendChild(h('p', { class: 'snn-a-caption', text: sized ? 'Database size per day' : 'Rows per day' }));
				body.appendChild(canvas);
				lineChart(canvas.querySelector('canvas'), history.map(function (d) { return dayLabel(d.date); }), [
					{ label: sized ? 'MB' : 'Rows', color: ACCENT, fill: true, data: history.map(function (d) { return sized ? Math.round(d.bytes / 10485.76) / 100 : d.rows; }) }
				]);
			} else {
				body.appendChild(h('p', { class: 'snn-a-hint', text: 'The size is recorded once a day. The growth chart appears after the second day.' }));
			}

			var groups = {};
			data.tables.forEach(function (t) { (groups[t.group] = groups[t.group] || []).push(t); });
			var largest = Math.max.apply(null, data.tables.map(function (t) { return t[measure] || 0; }).concat([1]));

			Object.keys(groups).forEach(function (group) {
				var list = groups[group].slice().sort(function (a, b) { return (b[measure] || 0) - (a[measure] || 0); });
				var sum = list.reduce(function (s, t) { return s + (t.bytes || 0); }, 0);
				body.appendChild(h('h3', { class: 'snn-a-subhead' }, [group, sized ? h('span', { class: 'snn-a-muted', text: ' · ' + bytesText(sum) }) : null]));
				body.appendChild(h('div', { class: 'snn-a-scroll' }, h('table', { class: 'snn-a-table snn-a-dbtable' }, [
					h('thead', {}, h('tr', {}, [
						h('th', { scope: 'col', text: 'Table' }),
						h('th', { scope: 'col', class: 'num', text: 'Rows' }),
						h('th', { scope: 'col', class: 'num', text: 'Data' }),
						h('th', { scope: 'col', class: 'num', text: 'Indexes' }),
						h('th', { scope: 'col', class: 'num', text: 'Total' })
					])),
					h('tbody', {}, list.map(function (t) {
						return h('tr', {}, [
							h('td', {}, [
								h('span', { class: 'snn-a-dbbar', style: 'width:' + Math.round((t[measure] || 0) / largest * 100) + '%' }),
								h('code', { text: t.name })
							]),
							h('td', { class: 'num', text: (t.approx ? '≈ ' : '') + n(t.rows) }),
							h('td', { class: 'num', text: bytesText(t.data) }),
							h('td', { class: 'num', text: bytesText(t.index) }),
							h('td', { class: 'num', text: bytesText(t.bytes) })
						]);
					}))
				])));
			});
		}

		function load() {
			api('admin/db-stats').then(paint).catch(function (err) {
				clear(body);
				body.appendChild(failed(err, load));
			});
		}

		load();

		return card('Database', body, { sub: 'How much space your statistics use and how fast they grow. Row counts marked ≈ are estimates.' });
	}

	// --------------------------------------------------------------- start

	var views = {
		dashboard: renderDashboard,
		realtime: renderRealtime,
		pages: renderPages,
		compare: renderCompare,
		heatmaps: renderHeatmaps,
		replay: renderReplay,
		referrers: renderReferrers,
		geo: renderGeo,
		devices: renderDevices,
		journeys: renderJourneys,
		goals: renderConversions,
		import: renderImport,
		settings: function () { if (tools) { renderTools(); } }
	};

	function render() {
		if (teardown) {
			teardown();
			teardown = null;
		}
		charts.splice(0).forEach(function (chart) { chart.destroy(); });
		if (!root) {
			return;
		}

		var view = root.getAttribute('data-view');
		clear(root);

		if (views[view]) {
			views[view](currentRange());
		}
	}

	if (root && root.getAttribute('data-view') === 'settings') {
		clear(root);
		if (tools) {
			renderTools();
		}
	} else if (root) {
		render();
	}
}());
