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
				return h('th', { class: numeric ? 'num' : '', scope: 'col', text: numeric ? head[0] : head });
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
					h('h2', { text: title }),
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
			h('div', { class: 'snn-a-kpi-label', text: label }),
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

		mountCard('campaigns', 'Campaigns', period, pageLink('snn-analytics-campaigns'), function (content) {
			return api('stats/campaigns', rp).then(function (rows) {
				clear(content);
				content.appendChild(table(['Campaign', numCol('Sessions')], rows.filter(isTagged).slice(0, 5).map(function (c) {
					return [campaignName(c), n(c.sessions)];
				}), 'No tagged campaign visits in this range. Build tagged links on the Campaigns screen.'));
			});
		});

		mountCard('goals', 'Goals', period, pageLink('snn-analytics-goals'), function (content) {
			return api('goals', rp).then(function (data) {
				clear(content);
				content.appendChild(table(['Goal', numCol('Conversions')], data.map(function (g) {
					return [g.name, n(g.conversions)];
				}), 'No goals yet. Add one on the Goals & Funnels screen.'));
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
	 * The heatmap and the trails share one grid: 50 columns across the page width, and 8 px rows
	 * for every 40 px of page height. Positions are therefore a share of the width and a distance
	 * from the top, not an exact copy of the page.
	 */
	var MAP_COLS = 50;
	var MAP_CELL = 12;
	var MAP_ROW = 8;
	// 2000 rows cover 80,000 px of page height, the same limit the tracker uses for click rows.
	var MAP_ROWS = 2000;

	function heatmapView(data) {
		if (!data.total) {
			return empty('No clicks on this page in this range yet.');
		}
		var lowest = data.cells.reduce(function (most, c) { return Math.max(most, c[1]); }, 0);
		var rows = Math.min(MAP_ROWS, lowest + 1);
		var canvas = h('canvas', { class: 'snn-a-mapcanvas', width: MAP_COLS * MAP_CELL, height: rows * MAP_ROW, role: 'img', 'aria-label': 'Click heatmap for this page' });
		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#f6f7f7';
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		data.cells.forEach(function (cell) {
			if (cell[1] >= rows) {
				return;
			}
			var x = cell[0] * MAP_CELL + MAP_CELL / 2;
			var y = cell[1] * MAP_ROW + MAP_ROW / 2;
			var radius = MAP_CELL * 2;
			var strength = cell[2] / data.max;
			var gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
			gradient.addColorStop(0, 'rgba(214,54,56,' + (0.25 + 0.6 * strength).toFixed(2) + ')');
			gradient.addColorStop(1, 'rgba(214,54,56,0)');
			ctx.fillStyle = gradient;
			ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
		});
		return h('div', {}, [h('div', { class: 'snn-a-mapscroll' }, canvas), h('p', { class: 'snn-a-muted', text: n(data.total) + ' clicks in this range' })]);
	}

	function trailView(data) {
		if (!data.trails.length) {
			return empty('No cursor trails in this range. They are recorded only when cursor trails are turned on in Settings.');
		}
		var lowest = data.trails.reduce(function (most, trail) {
			return trail.reduce(function (inner, p) { return Math.max(inner, p[1]); }, most);
		}, 0);
		var rows = Math.min(MAP_ROWS, Math.floor(lowest / 40) + 1);
		var canvas = h('canvas', { class: 'snn-a-mapcanvas', width: MAP_COLS * MAP_CELL, height: rows * MAP_ROW, role: 'img', 'aria-label': 'Cursor trails for this page' });
		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#f6f7f7';
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		ctx.lineWidth = 1;
		ctx.strokeStyle = 'rgba(34,113,177,0.3)';
		data.trails.forEach(function (trail) {
			if (!trail.length) {
				return;
			}
			ctx.beginPath();
			trail.forEach(function (p, i) {
				var x = p[0] / 10000 * canvas.width;
				var y = p[1] / 40 * MAP_ROW;
				if (i === 0) {
					ctx.moveTo(x, y);
				} else {
					ctx.lineTo(x, y);
				}
			});
			ctx.stroke();
		});
		var shown = data.total > data.trails.length ? ' Showing the latest ' + data.trails.length + '.' : '';
		return h('div', {}, [h('div', { class: 'snn-a-mapscroll' }, canvas), h('p', { class: 'snn-a-muted', text: n(data.total) + ' trails recorded in this range.' + shown })]);
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

		function showMaps(row) {
			var device = 0;
			var bar = h('div', { class: 'snn-a-toolbar' });
			var heatBox = h('div', {}, skeleton(3));
			var trailBox = h('div', {}, skeleton(3));

			function load() {
				clear(bar);
				bar.appendChild(deviceSegment(device, function (value) { device = value; load(); }));
				clear(heatBox);
				heatBox.appendChild(skeleton(3));
				clear(trailBox);
				trailBox.appendChild(skeleton(3));

				var query = Object.assign({}, rp, { id: row.id, device: device });
				api('stats/heatmap', query).then(function (data) {
					clear(heatBox);
					heatBox.appendChild(heatmapView(data));
				}).catch(function (err) {
					clear(heatBox);
					heatBox.appendChild(failed(err, load));
				});
				api('stats/trails', query).then(function (data) {
					clear(trailBox);
					trailBox.appendChild(trailView(data));
				}).catch(function (err) {
					clear(trailBox);
					trailBox.appendChild(failed(err, load));
				});
			}

			detail.appendChild(bar);
			detail.appendChild(card('Click heatmap', heatBox, {
				sub: 'Where visitors clicked on this page. Darker means more clicks.'
			}));
			detail.appendChild(card('Cursor trails', trailBox, {
				sub: 'How the pointer moved on each view of this page, up to 60 seconds per view.'
			}));
			load();
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
				canvas
			], { sub: row.path, actions: close }));
			detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
			showMaps(row);
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

	function renderCampaigns(rp) {
		var body = h('div', {}, skeleton(6));
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(body);

		api('stats/campaigns', rp).then(function (rows) {
			clear(body);
			body.appendChild(card('Campaigns', table(['Source', 'Medium', 'Campaign', 'Term', 'Content', numCol('Sessions'), numCol('Visitors'), numCol('Conversions')], rows.map(function (r) {
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
			}), 'No visits in this range.'), { sub: rangeLabel(rp) }));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err, render));
		});

		root.appendChild(card('Campaign URL builder', urlBuilder(), {
			sub: 'Tag a link so its visits are counted as a campaign. Source and campaign are required.'
		}));
	}

	function urlBuilder() {
		var fields = [
			['url', 'Destination URL', 'url', 'https://example.com/landing/', true],
			['utm_source', 'Source', 'text', 'newsletter', false],
			['utm_medium', 'Medium', 'text', 'email', false],
			['utm_campaign', 'Campaign', 'text', 'spring-sale', false],
			['utm_term', 'Term (optional)', 'text', '', false],
			['utm_content', 'Content (optional)', 'text', '', false]
		];
		var inputs = {};
		var grid = h('div', { class: 'snn-a-form-grid' });
		fields.forEach(function (f) {
			inputs[f[0]] = h('input', { type: f[2], placeholder: f[3], 'aria-label': f[1], autocomplete: 'off' });
			grid.appendChild(h('label', { class: 'snn-a-field' + (f[4] ? ' is-full' : '') }, [h('span', { text: f[1] }), inputs[f[0]]]));
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

		return h('div', { class: 'snn-a-builder' }, [
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
			return h('dl', { class: 'snn-a-facts' }, items.map(function (item) {
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
				var close = h('button', { type: 'button', class: 'button-link', onclick: clearSelection }, 'Close');
				var place = [s.city, s.region].filter(Boolean).join(', ');
				var campaign = [s.campaign.source, s.campaign.medium, s.campaign.campaign].filter(Boolean).join(' / ');
				var device = [DEVICE_LABELS[s.device] || 'Other', s.browser, s.os].filter(Boolean).join(' · ');
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

	function renderGoals(rp) {
		var body = h('div', {}, skeleton(6));
		root.appendChild(h('div', { class: 'snn-a-toolbar' }, rangeControl()));
		root.appendChild(body);

		function load() {
			Promise.all([api('goals', rp), api('funnels', rp)]).then(function (results) {
				clear(body);
				body.appendChild(card('Goals', [goalsTable(results[0], load), goalForm(load)], {
					sub: 'A goal counts once per visit. Add a rule, then review its conversions here.'
				}));
				body.appendChild(card('Funnels', [funnelList(results[1], rp, load), funnelForm(results[0], load)], {
					sub: 'Steps must happen in order, within the time window.'
				}));
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err, load));
			});
		}

		load();
	}

	function goalsTable(goals, reload) {
		var types = { 1: 'Page visited', 2: 'Click', 3: 'Custom event' };
		var matches = { 1: 'equals', 2: 'contains', 3: 'starts with' };
		if (!goals.length) {
			return empty('No goals yet. Add one below.');
		}
		return table(['Goal', 'Type', 'Rule', numCol('Conversions'), ''], goals.map(function (g) {
			var rule = g.type === 1 ? (matches[g.match] || '') + ' ' + g.value : g.value;
			var del = h('button', { type: 'button', class: 'button-link-delete', onclick: function () {
				if (window.confirm('Delete this goal and its conversions? This cannot be undone.')) {
					send('goals/delete', { id: g.id }).then(reload).catch(function (err) { window.alert(err.message); });
				}
			} }, 'Delete');
			return [g.name || 'Untitled goal', types[g.type] || '', rule, n(g.conversions), del];
		}));
	}

	function formMessage() {
		return h('p', { class: 'snn-a-hint is-error', role: 'alert' });
	}

	function goalForm(onSaved) {
		var name = h('input', { type: 'text', placeholder: 'Name, for example Signed up' });
		var value = h('input', { type: 'text', placeholder: '/thank-you/', class: 'regular-text' });
		var type = h('select', {}, [
			h('option', { value: '1' }, 'Page visited'),
			h('option', { value: '2' }, 'Click (CSS selector)'),
			h('option', { value: '3' }, 'Custom event')
		]);
		var match = h('select', {}, [
			h('option', { value: '1' }, 'equals'),
			h('option', { value: '2' }, 'contains'),
			h('option', { value: '3' }, 'starts with')
		]);
		var error = formMessage();
		var hints = {
			1: { placeholder: '/thank-you/', text: 'Matches the page path. Use “contains” to match a group of pages.' },
			2: { placeholder: '.buy-button or a[href*="checkout"]', text: 'A CSS selector for the element a visitor clicks.' },
			3: { placeholder: 'signup', text: 'Sent from your site with window.snnA.track("signup").' }
		};
		var hint = h('p', { class: 'snn-a-hint' }, hints[1].text);

		type.addEventListener('change', function () {
			var current = hints[type.value];
			value.placeholder = current.placeholder;
			hint.textContent = current.text;
			match.disabled = type.value !== '1';
		});

		return h('details', { class: 'snn-a-add' }, [
			h('summary', {}, 'Add a goal'),
			h('div', { class: 'snn-a-form-grid' }, [
				h('label', { class: 'snn-a-field' }, [h('span', { text: 'Name' }), name]),
				h('label', { class: 'snn-a-field' }, [h('span', { text: 'Type' }), type]),
				h('label', { class: 'snn-a-field' }, [h('span', { text: 'Match' }), match]),
				h('label', { class: 'snn-a-field is-full' }, [h('span', { text: 'Value' }), value])
			]),
			hint,
			error,
			h('p', {}, h('button', { type: 'button', class: 'button button-primary', onclick: function () {
				error.textContent = '';
				if (!value.value.trim()) {
					error.textContent = 'Enter a value for this goal.';
					return;
				}
				send('goals', {
					name: name.value.trim(),
					type: Number(type.value),
					match: Number(match.value),
					value: value.value.trim()
				}).then(onSaved).catch(function (err) { error.textContent = err.message; });
			} }, 'Save goal'))
		]);
	}

	function funnelList(funnels, rp, reload) {
		if (!funnels.length) {
			return empty('No funnels yet. A funnel shows how many visits reach each step, in order.');
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

	function funnelForm(goals, onSaved) {
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
			stepBox.appendChild(h('label', { class: 'snn-a-field' }, [h('span', { text: 'Step ' + steps.length }), select]));
		}

		function removeStep() {
			if (steps.length > 2) {
				steps.pop();
				stepBox.removeChild(stepBox.lastChild);
			}
		}

		if (!goals.length) {
			return empty('Create at least two goals before you build a funnel.');
		}

		addStep();
		addStep();

		return h('details', { class: 'snn-a-add' }, [
			h('summary', {}, 'Add a funnel'),
			h('div', { class: 'snn-a-form-grid' }, [
				h('label', { class: 'snn-a-field' }, [h('span', { text: 'Name' }), name]),
				h('label', { class: 'snn-a-field' }, [h('span', { text: 'Window (days)' }), days])
			]),
			stepBox,
			h('p', { class: 'snn-a-actions' }, [
				h('button', { type: 'button', class: 'button', onclick: addStep }, 'Add step'),
				h('button', { type: 'button', class: 'button', onclick: removeStep }, 'Remove step')
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

	// --------------------------------------------------------------- start

	var views = {
		dashboard: renderDashboard,
		realtime: renderRealtime,
		pages: renderPages,
		referrers: renderReferrers,
		geo: renderGeo,
		devices: renderDevices,
		campaigns: renderCampaigns,
		journeys: renderJourneys,
		goals: renderGoals,
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
