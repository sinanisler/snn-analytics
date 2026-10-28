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

	var CHANNEL_TYPES = { 1: 'Direct', 2: 'Search', 3: 'Social', 4: 'Referral', 5: 'Ad', 6: 'Email' };
	var PRESETS = [
		['today', 'Today'],
		['7d', '7 days'],
		['30d', '30 days'],
		['90d', '90 days'],
		['12m', '12 months'],
		['custom', 'Custom']
	];

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

	function change(current, previous) {
		if (!previous) {
			return current ? { text: 'new', cls: 'up' } : { text: '–', cls: '' };
		}
		var diff = Math.round((current - previous) / previous * 1000) / 10;
		var arrow = diff > 0 ? '↗ +' : diff < 0 ? '↘ −' : '→ ';
		return { text: arrow + Math.abs(diff) + '%', cls: diff > 0 ? 'up' : diff < 0 ? 'down' : '' };
	}

	function ago(ts) {
		var s = Math.max(0, Math.round(Date.now() / 1000 - ts));
		if (s < 60) { return s + 's ago'; }
		if (s < 3600) { return Math.round(s / 60) + 'm ago'; }
		if (s < 86400) { return Math.round(s / 3600) + 'h ago'; }
		return Math.round(s / 86400) + 'd ago';
	}

	function when(ts) {
		return new Date(ts * 1000).toLocaleString();
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

	function pageLink(slug, extra) {
		var params = currentRange();
		Object.keys(extra || {}).forEach(function (key) {
			params[key] = extra[key];
		});
		return cfg.pageUrl + slug + '&' + new URLSearchParams(params).toString();
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
		var from = h('input', { type: 'date', value: current.from, 'aria-label': 'From' });
		var to = h('input', { type: 'date', value: current.to, 'aria-label': 'To' });
		var customBox = h('span', { class: 'snn-a-custom', style: current.range === 'custom' ? '' : 'display:none' }, [
			from, ' – ', to,
			h('button', { type: 'button', class: 'button', onclick: function () {
				if (from.value && to.value) {
					setRange({ range: 'custom', from: from.value, to: to.value });
				}
			} }, 'Apply')
		]);

		return h('div', { class: 'snn-a-range' }, [
			PRESETS.map(function (preset) {
				return h('button', {
					type: 'button',
					class: 'button' + (current.range === preset[0] ? ' button-primary' : ''),
					onclick: function () {
						if (preset[0] === 'custom') {
							customBox.style.display = '';
							return;
						}
						setRange({ range: preset[0] });
					}
				}, preset[1]);
			}),
			customBox
		]);
	}

	// -------------------------------------------------------- components

	function card(title, body, link, subtitle) {
		return h('section', { class: 'snn-a-card' }, [
			h('header', { class: 'snn-a-card-head' }, [
				h('div', {}, [
					h('h2', { text: title }),
					subtitle ? h('span', { class: 'snn-a-card-sub', text: subtitle }) : null
				]),
				link ? h('a', { href: link }, 'See more →') : null
			]),
			h('div', { class: 'snn-a-card-body' }, body)
		]);
	}

	function table(headers, rows, emptyText) {
		if (!rows.length) {
			return h('p', { class: 'snn-a-empty' }, emptyText || 'No data for this range yet.');
		}
		return h('table', { class: 'widefat striped snn-a-table' }, [
			h('thead', {}, h('tr', {}, headers.map(function (head) {
				return h('th', { text: head });
			}))),
			h('tbody', {}, rows.map(function (cells) {
				return h('tr', {}, cells.map(function (cell) {
					if (cell && cell.nodeType) {
						return h('td', {}, cell);
					}
					return h('td', { text: cell === null || cell === undefined ? '' : String(cell) });
				}));
			}))
		]);
	}

	function kpi(label, value, delta) {
		return h('div', { class: 'snn-a-kpi' }, [
			h('div', { class: 'snn-a-kpi-label' }, h('span', { text: label })),
			h('div', { class: 'snn-a-kpi-value', text: value }),
			delta ? h('div', { class: 'snn-a-kpi-delta ' + delta.cls }, [
				h('span', { text: delta.text }),
				h('span', { class: 'snn-a-kpi-vs', text: ' vs previous period' })
			]) : null
		]);
	}

	function lineChart(canvas, labels, datasets) {
		var chart = new window.Chart(canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: datasets.map(function (set) {
					return {
						label: set.label,
						data: set.data,
						borderColor: set.color,
						backgroundColor: set.color,
						tension: 0.25,
						pointRadius: 0,
						fill: false
					};
				})
			},
			options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true } } }
		});
		charts.push(chart);
		return chart;
	}

	function doughnut(canvas, labels, data) {
		var chart = new window.Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{ data: data, backgroundColor: ['#2271b1', '#00a32a', '#dba617', '#d63638', '#8c8f94', '#72aee6', '#9b51e0'] }]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
		});
		charts.push(chart);
		return chart;
	}

	function chartBox(height) {
		return h('div', { class: 'snn-a-chart', style: 'height:' + (height || 260) + 'px' }, [
			h('canvas', {})
		]);
	}

	function loading() {
		return h('p', { class: 'snn-a-loading', text: 'Loading…' });
	}

	function failed(err) {
		return h('div', { class: 'notice notice-error inline' }, h('p', { text: 'Could not load this data: ' + err.message }));
	}

	// ------------------------------------------------------------- views

	var SVG_NS = 'http://www.w3.org/2000/svg';
	var worldPromise = null;

	function loadWorld() {
		if (!worldPromise) {
			worldPromise = fetch(cfg.world, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
		}
		return worldPromise;
	}

	function rangeLabel(rp) {
		var labels = { today: 'Today', '7d': 'Last 7 days', '30d': 'Last 30 days', '90d': 'Last 90 days', '12m': 'Last 12 months' };
		return rp.range === 'custom' ? (rp.from || '') + ' – ' + (rp.to || '') : (labels[rp.range] || 'Last 30 days');
	}

	function empty() {
		return h('p', { class: 'snn-a-empty', text: 'No data for this range yet.' });
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
			path.setAttribute('fill', count ? 'rgba(34,113,177,' + (0.25 + 0.75 * strength).toFixed(2) + ')' : '#e5e7eb');
			path.setAttribute('stroke', '#ffffff');
			path.setAttribute('stroke-width', '0.5');
			title.textContent = countryName(code) + ': ' + n(count) + ' sessions';
			path.appendChild(title);
			svg.appendChild(path);
		});

		return svg;
	}

	function renderDashboard(rp) {
		var grid = h('div', { class: 'snn-a-grid' });
		var metrics = h('div', { class: 'snn-a-kpis' }, loading());
		var customize = h('div', { class: 'snn-a-customize', style: 'display:none' });
		var overview = api('stats/overview', rp);
		var period = rangeLabel(rp);

		root.appendChild(rangeControl());
		root.appendChild(metrics);
		root.appendChild(h('p', {}, [
			h('button', { type: 'button', class: 'button', onclick: function () {
				customize.style.display = customize.style.display === 'none' ? '' : 'none';
			} }, 'Customize cards')
		]));
		root.appendChild(customize);
		root.appendChild(grid);

		overview.then(function (data) {
			var c = data.current;
			var p = data.previous;
			clear(metrics);
			[
				['Visitors', n(c.visitors), c.visitors, p.visitors],
				['New visitors', n(c.new_visitors), c.new_visitors, p.new_visitors],
				['Sessions', n(c.sessions), c.sessions, p.sessions],
				['Views', n(c.views), c.views, p.views],
				['Bounce rate', pct(c.bounce_rate), c.bounce_rate, p.bounce_rate],
				['Avg. time', duration(c.avg_duration), c.avg_duration, p.avg_duration],
				['Views / session', String(c.views_per_session), c.views_per_session, p.views_per_session],
				['Events', n(c.events), c.events, p.events]
			].forEach(function (m) {
				metrics.appendChild(kpi(m[0], m[1], change(m[2], m[3])));
			});
		}).catch(function (err) {
			clear(metrics);
			metrics.appendChild(failed(err));
		});

		function mountCard(id, title, subtitle, link, load, wide) {
			var body = h('div', {}, loading());
			var box = card(title, [body], link, subtitle);
			box.dataset.card = id;
			if (wide) {
				box.classList.add('wide');
			}
			grid.appendChild(box);
			load(body).catch(function (err) {
				clear(body);
				body.appendChild(failed(err));
			});
		}

		mountCard('chart', 'Site traffic', period, null, function (body) {
			return overview.then(function (data) {
				clear(body);
				var box = chartBox(300);
				body.appendChild(box);
				lineChart(box.querySelector('canvas'), data.series.map(function (d) { return d.date; }), [
					{ label: 'Views', color: '#f59e0b', data: data.series.map(function (d) { return d.views; }) },
					{ label: 'Visitors', color: '#6d4fc2', data: data.series.map(function (d) { return d.visitors; }) }
				]);
			});
		}, true);

		mountCard('pages', 'Top pages', period, pageLink('snn-analytics-pages'), function (body) {
			return api('stats/pages', Object.assign({}, rp, { orderby: 'views', per_page: 10 })).then(function (data) {
				clear(body);
				body.appendChild(table(['Page', 'Views', 'Visitors'], data.rows.map(function (r) {
					return [r.title || r.path, n(r.views), n(r.visitors)];
				})));
			});
		});

		mountCard('geo', 'Geographic traffic', period, pageLink('snn-analytics-geo'), function (body) {
			return Promise.all([api('stats/geo', rp), loadWorld()]).then(function (results) {
				clear(body);
				body.appendChild(worldMap(results[0].countries, results[1]));
			});
		});

		mountCard('newreturning', 'New vs. returning sessions', period, null, function (body) {
			return overview.then(function (data) {
				clear(body);
				var c = data.current;
				if (!c.sessions) {
					body.appendChild(empty());
					return;
				}
				var box = chartBox(260);
				body.appendChild(box);
				doughnut(box.querySelector('canvas'), ['New sessions', 'Returning sessions'], [c.new_visitors, Math.max(0, c.sessions - c.new_visitors)]);
			});
		});

		mountCard('devices', 'Devices', period, pageLink('snn-analytics-devices'), function (body) {
			return api('stats/devices', rp).then(function (data) {
				clear(body);
				if (!data.devices.length) {
					body.appendChild(empty());
					return;
				}
				var box = chartBox(260);
				body.appendChild(box);
				doughnut(box.querySelector('canvas'), data.devices.map(function (d) { return d.label; }), data.devices.map(function (d) { return d.sessions; }));
			});
		});

		mountCard('sources', 'Traffic sources', period, pageLink('snn-analytics-referrers'), function (body) {
			return api('stats/referrers', rp).then(function (data) {
				clear(body);
				var types = Object.keys(data.types);
				if (!types.length) {
					body.appendChild(empty());
					return;
				}
				var box = chartBox(260);
				body.appendChild(box);
				doughnut(box.querySelector('canvas'), types.map(function (t) { return CHANNEL_TYPES[t] || 'Other'; }), types.map(function (t) { return data.types[t]; }));
			});
		});

		mountCard('recent', 'Recent views', 'Most recent 10', pageLink('snn-analytics-journeys'), function (body) {
			return api('stats/recent', { limit: 10 }).then(function (rows) {
				clear(body);
				body.appendChild(table(['Time', 'Page', 'Country', 'Device', 'Browser'], rows.map(function (r) {
					return [
						new Date(r.time * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
						r.title,
						countryName(r.country),
						r.device,
						r.browser || r.os
					];
				}), 'No views yet.'));
			});
		});

		mountCard('realtime', 'Realtime', 'Last 5 minutes', pageLink('snn-analytics-realtime'), function (body) {
			return api('stats/realtime').then(function (data) {
				clear(body);
				body.appendChild(h('p', {}, [h('strong', { text: n(data.active) }), ' visitors right now']));
				body.appendChild(table(['Live page', 'Visitors'], data.pages.map(function (p) {
					return [p.title || p.path, n(p.visitors)];
				}), 'Nobody is on the site right now.'));
			});
		});

		mountCard('campaigns', 'Campaigns', period, pageLink('snn-analytics-campaigns'), function (body) {
			return api('stats/campaigns', rp).then(function (data) {
				clear(body);
				body.appendChild(table(['Campaign', 'Sessions'], data.slice(0, 5).map(function (c) {
					return [[c.source, c.medium, c.campaign].filter(Boolean).join(' / ') || 'Campaign', n(c.sessions)];
				})));
			});
		});

		mountCard('goals', 'Goals', period, pageLink('snn-analytics-goals'), function (body) {
			return api('goals', rp).then(function (data) {
				clear(body);
				body.appendChild(table(['Goal', 'Conversions'], data.map(function (g) {
					return [g.name, n(g.conversions)];
				}), 'No goals yet.'));
			});
		});

		// Order and visibility, from the user's saved layout. Cards missing from it go to the end.
		function placeCards() {
			api('prefs/dashboard').then(function (layout) {
				var order = layout.order || [];
				var hidden = layout.hidden || [];
				var rank = function (id) {
					var i = order.indexOf(id);
					return i < 0 ? 999 : i;
				};
				var cards = Array.prototype.slice.call(grid.children);

				cards.sort(function (a, b) {
					return rank(a.dataset.card) - rank(b.dataset.card);
				});
				cards.forEach(function (c) {
					grid.appendChild(c);
					c.style.display = hidden.indexOf(c.dataset.card) >= 0 ? 'none' : '';
				});
				buildCustomize(layout);
			}).catch(function () {});
		}

		function saveLayout() {
			var all = Array.prototype.slice.call(grid.children);
			send('prefs/dashboard', {
				order: all.map(function (c) { return c.dataset.card; }),
				hidden: all.filter(function (c) { return c.style.display === 'none'; }).map(function (c) { return c.dataset.card; })
			}).catch(function () {});
		}

		function buildCustomize(layout) {
			clear(customize);
			var hidden = layout.hidden || [];
			(cfg.cards || []).forEach(function (id) {
				var box = h('input', { type: 'checkbox', value: id });
				box.checked = hidden.indexOf(id) < 0;
				box.addEventListener('change', function () {
					var target = grid.querySelector('[data-card="' + id + '"]');
					if (target) {
						target.style.display = box.checked ? '' : 'none';
					}
					saveLayout();
				});
				customize.appendChild(h('label', { class: 'snn-a-check' }, [box, ' ' + id]));
			});
		}

		// Drag cards to reorder. The new order is saved when the drop finishes.
		var dragging = null;
		grid.addEventListener('dragstart', function (event) {
			dragging = event.target.closest('.snn-a-card');
			if (dragging) {
				event.dataTransfer.effectAllowed = 'move';
				dragging.classList.add('is-dragging');
			}
		});
		grid.addEventListener('dragover', function (event) {
			event.preventDefault();
			var over = event.target.closest('.snn-a-card');
			if (dragging && over && over !== dragging) {
				var rect = over.getBoundingClientRect();
				var after = event.clientY > rect.top + rect.height / 2;
				grid.insertBefore(dragging, after ? over.nextSibling : over);
			}
		});
		grid.addEventListener('dragend', function () {
			if (dragging) {
				dragging.classList.remove('is-dragging');
			}
			dragging = null;
			saveLayout();
		});
		grid.addEventListener('mousedown', function (event) {
			var box = event.target.closest('.snn-a-card');
			if (box) {
				box.setAttribute('draggable', event.target.closest('a, button, input, select') ? 'false' : 'true');
			}
		});

		placeCards();
	}

	function renderGeo(rp) {
		var body = h('div', {}, loading());
		root.appendChild(rangeControl());
		root.appendChild(body);

		Promise.all([api('stats/geo', rp), loadWorld()]).then(function (results) {
			var data = results[0];
			clear(body);
			body.appendChild(card('Sessions by country', [worldMap(data.countries, results[1])]));
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Countries', [table(['Country', 'Sessions', 'Visitors', 'Views'], data.countries.map(function (c) {
					return [countryName(c.code), n(c.sessions), n(c.visitors), n(c.views)];
				}))]),
				card('Cities', [table(['City', 'Region', 'Country', 'Sessions', 'Visitors'], data.cities.map(function (c) {
					return [c.name, c.region, countryName(c.code), n(c.sessions), n(c.visitors)];
				}), 'City data appears after the city database is installed in Settings.')])
			]));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err));
		});
	}

	function renderRealtime() {
		var body = h('div', {}, loading());
		root.appendChild(h('p', { class: 'description' }, 'Refreshes every 10 seconds while this tab is open.'));
		root.appendChild(body);

		var timer = null;
		var canvas = null;
		var chart = null;

		function tick() {
			api('stats/realtime').then(function (data) {
				clear(body);
				body.appendChild(h('p', { class: 'snn-a-big' }, [h('strong', { text: n(data.active) }), ' visitors active in the last 5 minutes']));
				canvas = chartBox(200);
				body.appendChild(card('Views per minute (last 30 min)', [canvas]));
				if (chart) {
					chart.destroy();
				}
				chart = lineChart(canvas.querySelector('canvas'), data.per_minute.map(function (m) {
					return new Date(m.minute * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
				}), [{ label: 'Views', color: '#2271b1', data: data.per_minute.map(function (m) { return m.views; }) }]);
				body.appendChild(h('div', { class: 'snn-a-split' }, [
					card('Live pages', [table(['Page', 'Visitors'], data.pages.map(function (p) { return [p.title || p.path, n(p.visitors)]; }))]),
					card('Live referrers', [table(['Source', 'Visitors'], data.referrers.map(function (r) { return [r.domain, n(r.visitors)]; }))]),
					card('Live countries', [table(['Country', 'Visitors'], data.countries.map(function (c) { return [countryName(c.code), n(c.visitors)]; }))])
				]));
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err));
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

	function renderPages(rp) {
		var state = { page: 1, orderby: 'views', order: 'desc', search: '' };
		var body = h('div', {}, loading());
		var search = h('input', { type: 'search', placeholder: 'Search title or path', 'aria-label': 'Search pages' });
		var detail = h('div', { class: 'snn-a-detail' });

		root.appendChild(rangeControl());
		root.appendChild(h('p', {}, [
			search,
			' ',
			h('button', { type: 'button', class: 'button', onclick: function () {
				state.search = search.value.trim();
				state.page = 1;
				load();
			} }, 'Search')
		]));
		root.appendChild(body);
		root.appendChild(detail);

		var heads = [['title', 'Page'], ['views', 'Views'], ['visitors', 'Visitors'], ['duration', 'Avg. time'], ['entrances', 'Entrances'], ['exits', 'Exits']];

		function load() {
			api('stats/pages', Object.assign({}, rp, state, { per_page: 25 })).then(function (data) {
				clear(body);
				var pages = Math.max(1, Math.ceil(data.total / 25));
				body.appendChild(h('p', {}, 'Showing ' + n(data.rows.length) + ' of ' + n(data.total) + ' pages.'));
				body.appendChild(h('table', { class: 'widefat striped snn-a-table' }, [
					h('thead', {}, h('tr', {}, heads.map(function (head) {
						var active = state.orderby === head[0];
						return h('th', {}, h('a', { href: '#', onclick: function (event) {
							event.preventDefault();
							state.order = active && state.order === 'desc' ? 'asc' : 'desc';
							state.orderby = head[0];
							load();
						} }, head[1] + (active ? (state.order === 'desc' ? ' ▼' : ' ▲') : '')));
					}))),
					h('tbody', {}, data.rows.map(function (row) {
						return h('tr', { class: 'snn-a-clickable', onclick: function () { showPage(row); } }, [
							h('td', {}, [h('strong', { text: row.title || '(untitled)' }), h('br'), h('code', { text: row.path })]),
							h('td', { text: n(row.views) }),
							h('td', { text: n(row.visitors) }),
							h('td', { text: duration(row.avg_time) }),
							h('td', { text: n(row.entrances) }),
							h('td', { text: n(row.exits) })
						]);
					}))
				]));
				body.appendChild(h('p', {}, [
					h('button', { type: 'button', class: 'button', disabled: state.page <= 1 ? true : null, onclick: function () { state.page--; load(); } }, '‹ Previous'),
					' Page ' + state.page + ' of ' + pages + ' ',
					h('button', { type: 'button', class: 'button', disabled: state.page >= pages ? true : null, onclick: function () { state.page++; load(); } }, 'Next ›')
				]));
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err));
			});
		}

		function showPage(row) {
			clear(detail);
			var canvas = chartBox(240);
			detail.appendChild(card(row.title || row.path, [
				h('p', {}, [h('code', { text: row.path }), ' · ' + n(row.views) + ' views · ' + n(row.visitors) + ' visitors']),
				canvas
			]));
			api('stats/page-series', Object.assign({}, rp, { id: row.id })).then(function (series) {
				lineChart(canvas.querySelector('canvas'), series.map(function (d) { return d.date; }), [
					{ label: 'Views', color: '#2271b1', data: series.map(function (d) { return d.views; }) },
					{ label: 'Visitors', color: '#00a32a', data: series.map(function (d) { return d.visitors; }) }
				]);
			}).catch(function (err) {
				detail.appendChild(failed(err));
			});
		}

		load();
	}

	function renderReferrers(rp) {
		var body = h('div', {}, loading());
		root.appendChild(rangeControl());
		root.appendChild(body);

		api('stats/referrers', rp).then(function (data) {
			clear(body);
			var canvas = chartBox(260);
			var types = Object.keys(data.types);
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Sources by type', [types.length ? canvas : h('p', { class: 'snn-a-empty', text: 'No data for this range yet.' })]),
				card('Summary', [h('p', { text: 'Direct traffic has no referrer. Search, social and email come from the referrer domain.' })])
			]));
			if (types.length) {
				doughnut(canvas.querySelector('canvas'), types.map(function (t) { return CHANNEL_TYPES[t] || 'Other'; }), types.map(function (t) { return data.types[t]; }));
			}
			body.appendChild(card('Domains', [table(['Source', 'Type', 'Sessions', 'Visitors', 'Views', 'Bounce'], data.domains.map(function (d) {
				return [
					d.domain,
					CHANNEL_TYPES[d.type] || 'Other',
					n(d.sessions),
					n(d.visitors),
					n(d.views),
					d.sessions ? pct(100 * d.bounces / d.sessions) : '–'
				];
			}))]));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err));
		});
	}


	function renderDevices(rp) {
		var body = h('div', {}, loading());
		root.appendChild(rangeControl());
		root.appendChild(body);

		api('stats/devices', rp).then(function (data) {
			clear(body);
			var canvas = chartBox(240);
			body.appendChild(h('div', { class: 'snn-a-split' }, [
				card('Device types', [data.devices.length ? canvas : h('p', { class: 'snn-a-empty', text: 'No data for this range yet.' })]),
				card('Browsers', [table(['Browser', 'Sessions', 'Visitors'], data.browsers.map(function (b) { return [b.name, n(b.sessions), n(b.visitors)]; }))])
			]));
			if (data.devices.length) {
				doughnut(canvas.querySelector('canvas'), data.devices.map(function (d) { return d.label; }), data.devices.map(function (d) { return d.sessions; }));
			}
			body.appendChild(card('Operating systems', [table(['OS', 'Sessions', 'Visitors'], data.os.map(function (o) { return [o.name, n(o.sessions), n(o.visitors)]; }))]));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err));
		});
	}

	function renderCampaigns(rp) {
		var body = h('div', {}, loading());
		root.appendChild(rangeControl());
		root.appendChild(body);
		root.appendChild(card('Campaign URL builder', [urlBuilder()]));

		api('stats/campaigns', rp).then(function (rows) {
			clear(body);
			body.appendChild(card('Campaigns', [table(['Source', 'Medium', 'Campaign', 'Term', 'Content', 'Sessions', 'Visitors', 'Conversions'], rows.map(function (r) {
				return [r.source, r.medium, r.campaign, r.term, r.content, n(r.sessions), n(r.visitors), n(r.conversions)];
			}), 'No UTM campaigns in this range. Tag your links with the builder below.')]));
		}).catch(function (err) {
			clear(body);
			body.appendChild(failed(err));
		});
	}

	function urlBuilder() {
		var fields = {
			url: h('input', { type: 'url', placeholder: 'https://example.com/landing/', class: 'regular-text' }),
			utm_source: h('input', { type: 'text', placeholder: 'newsletter' }),
			utm_medium: h('input', { type: 'text', placeholder: 'email' }),
			utm_campaign: h('input', { type: 'text', placeholder: 'spring-sale' }),
			utm_term: h('input', { type: 'text' }),
			utm_content: h('input', { type: 'text' })
		};
		var output = h('input', { type: 'text', readonly: true, class: 'large-text' });

		function build() {
			var raw = fields.url.value.trim();
			if (!raw) {
				output.value = '';
				return;
			}
			try {
				var target = new URL(raw);
				Object.keys(fields).forEach(function (key) {
					if (key !== 'url' && fields[key].value.trim() !== '') {
						target.searchParams.set(key, fields[key].value.trim().toLowerCase().replace(/\s+/g, '-'));
					}
				});
				output.value = target.toString();
			} catch (e) {
				output.value = 'Enter a full URL starting with https://';
			}
		}

		Object.keys(fields).forEach(function (key) {
			fields[key].addEventListener('input', build);
		});

		return h('div', { class: 'snn-a-builder' }, [
			h('p', {}, [h('label', {}, ['Destination URL ', fields.url])]),
			h('p', {}, [
				h('label', {}, ['Source ', fields.utm_source]), ' ',
				h('label', {}, ['Medium ', fields.utm_medium]), ' ',
				h('label', {}, ['Campaign ', fields.utm_campaign])
			]),
			h('p', {}, [h('label', {}, ['Term ', fields.utm_term]), ' ', h('label', {}, ['Content ', fields.utm_content])]),
			h('p', {}, [output, ' ', h('button', { type: 'button', class: 'button', onclick: function () {
				if (output.value && navigator.clipboard) {
					navigator.clipboard.writeText(output.value);
				}
			} }, 'Copy')]),
			h('p', { class: 'description', text: 'Values are lowercased, and spaces become dashes, so campaign names stay consistent.' })
		]);
	}

	function renderJourneys(rp) {
		var state = { page: 1 };
		var body = h('div', {}, loading());
		var detail = h('div', { class: 'snn-a-detail' });

		root.appendChild(rangeControl());
		root.appendChild(body);
		root.appendChild(detail);

		function load() {
			api('stats/journeys', Object.assign({}, rp, state, { per_page: 25 })).then(function (data) {
				clear(body);
				var pages = Math.max(1, Math.ceil(data.total / 25));
				body.appendChild(h('p', {}, n(data.total) + ' sessions. Click one to see its timeline.'));
				body.appendChild(table(['Started', 'Visitor', 'Entry', 'Exit', 'Referrer', 'Country', 'Views', 'Time', ''], data.rows.map(function (s) {
					var open = h('button', { type: 'button', class: 'button', onclick: function () { showJourney(s.id); } }, 'Timeline');
					return [when(s.started_at), '#' + s.visitor + (s['new'] ? ' (new)' : ''), s.entry, s.exit, s.referrer, countryName(s.country), n(s.views), duration(s.duration), open];
				})));
				body.appendChild(h('p', {}, [
					h('button', { type: 'button', class: 'button', disabled: state.page <= 1 ? true : null, onclick: function () { state.page--; load(); } }, '‹ Previous'),
					' Page ' + state.page + ' of ' + pages + ' ',
					h('button', { type: 'button', class: 'button', disabled: state.page >= pages ? true : null, onclick: function () { state.page++; load(); } }, 'Next ›')
				]));
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err));
			});
		}

		function showJourney(id) {
			clear(detail);
			detail.appendChild(loading());
			api('stats/journey', { id: id }).then(function (data) {
				clear(detail);
				var s = data.session;
				detail.appendChild(card('Session #' + s.id + ' · visitor #' + s.visitor, [
					h('p', {}, [
						when(s.started_at), ' · ', duration(s.duration), ' · ' + s.views + ' views · ' + s.events + ' events',
						s.bounce ? ' · bounce' : ''
					]),
					h('ol', { class: 'snn-a-timeline' }, data.timeline.map(function (item) {
						if (item.type === 'view') {
							return h('li', {}, [
								h('span', { class: 'snn-a-time', text: new Date(item.at * 1000).toLocaleTimeString() }),
								' Page: ', h('strong', { text: item.title || item.path }),
								' ', h('code', { text: item.path }),
								' · ' + duration(item.duration) + ' on page'
							]);
						}
						return h('li', {}, [
							h('span', { class: 'snn-a-time', text: new Date(item.at * 1000).toLocaleTimeString() }),
							' ' + item.label + ': ', h('strong', { text: item.value })
						]);
					})),
					data.others.length ? h('p', {}, 'Other sessions from this visitor:') : null,
					data.others.length ? h('ul', {}, data.others.map(function (o) {
						return h('li', {}, [
							h('a', { href: '#', onclick: function (event) { event.preventDefault(); showJourney(o.id); } }, when(o.started_at)),
							' · ' + o.views + ' views · ' + duration(o.duration)
						]);
					})) : null
				]));
			}).catch(function (err) {
				clear(detail);
				detail.appendChild(failed(err));
			});
		}

		load();
	}

	function renderGoals(rp) {
		var body = h('div', {}, loading());
		root.appendChild(rangeControl());
		root.appendChild(body);

		function load() {
			Promise.all([api('goals', rp), api('funnels', rp)]).then(function (results) {
				clear(body);
				body.appendChild(card('Goals', [goalsTable(results[0]), goalForm(load)]));
				body.appendChild(card('Funnels', [funnelList(results[1], results[0], rp), funnelForm(results[0], load)]));
			}).catch(function (err) {
				clear(body);
				body.appendChild(failed(err));
			});
		}

		load();
	}

	function goalsTable(goals) {
		var types = { 1: 'Page', 2: 'Click', 3: 'Custom event' };
		var matches = { 1: 'equals', 2: 'contains', 3: 'starts with' };
		if (!goals.length) {
			return h('p', { class: 'snn-a-empty', text: 'No goals yet. Add one below.' });
		}
		return table(['Goal', 'Type', 'Rule', 'Conversions', ''], goals.map(function (g) {
			var del = h('button', { type: 'button', class: 'button-link-delete', onclick: function () {
				if (window.confirm('Delete this goal and its conversions?')) {
					send('goals/delete', { id: g.id }).then(function () { renderGoalsAgain(); }).catch(alertError);
				}
			} }, 'Delete');
			return [g.name, types[g.type] || '', (g.type === 2 ? 'selector ' : matches[g.match] + ' ') + g.value, n(g.conversions), del];
		}));
	}

	/** Re-renders the current screen after a save or delete, so nothing is shown twice. */
	function renderGoalsAgain() {
		render();
	}

	function goalForm() {
		var name = h('input', { type: 'text', placeholder: 'Name (optional)' });
		var type = h('select', {}, [
			h('option', { value: '1' }, 'Page visited'),
			h('option', { value: '2' }, 'Click (CSS selector)'),
			h('option', { value: '3' }, 'Custom event (snnA.track)')
		]);
		var match = h('select', {}, [
			h('option', { value: '1' }, 'equals'),
			h('option', { value: '2' }, 'contains'),
			h('option', { value: '3' }, 'starts with')
		]);
		var value = h('input', { type: 'text', placeholder: '/thank-you/ or .buy-button or signup', class: 'regular-text' });

		return h('details', { class: 'snn-a-add' }, [
			h('summary', {}, 'Add a goal'),
			h('p', {}, [name, ' ', type, ' ', match, ' ', value]),
			h('p', { class: 'description', text: 'Page goals match the page path. Click goals use a CSS selector, for example .buy-button or a[href*="checkout"]. Custom events are sent with window.snnA.track("name").' }),
			h('p', {}, h('button', { type: 'button', class: 'button button-primary', onclick: function () {
				send('goals', {
					name: name.value,
					type: Number(type.value),
					match: Number(match.value),
					value: value.value
				}).then(function () { renderGoalsAgain(); }).catch(alertError);
			} }, 'Save goal'))
		]);
	}

	function funnelList(funnels, goals, rp) {
		if (!funnels.length) {
			return h('p', { class: 'snn-a-empty', text: 'No funnels yet. A funnel shows how many sessions reach each goal, in order.' });
		}
		return h('div', {}, funnels.map(function (f) {
			var holder = h('div', { class: 'snn-a-funnel' }, loading());
			api('funnels/report', Object.assign({}, rp, { id: f.id })).then(function (report) {
				clear(holder);
				var max = report.steps.length ? Math.max(1, report.steps[0].sessions) : 1;
				holder.appendChild(h('p', {}, [h('strong', { text: f.name }), ' · ' + f.window_days + ' day window']));
				report.steps.forEach(function (step, index) {
					holder.appendChild(h('div', { class: 'snn-a-step' }, [
						h('div', { class: 'snn-a-step-label', text: (index + 1) + '. ' + step.name }),
						h('div', { class: 'snn-a-bar', style: 'width:' + Math.round(100 * step.sessions / max) + '%', text: n(step.sessions) + ' sessions' }),
						h('div', { class: 'description', text: pct(step.rate_from_start) + ' of start' + (index ? ' · dropped ' + n(step.drop_from_prev) : '') })
					]));
				});
			}).catch(function (err) {
				clear(holder);
				holder.appendChild(failed(err));
			});
			var del = h('button', { type: 'button', class: 'button-link-delete', onclick: function () {
				if (window.confirm('Delete this funnel?')) {
					send('funnels/delete', { id: f.id }).then(function () { renderGoalsAgain(); }).catch(alertError);
				}
			} }, 'Delete funnel');
			return h('div', { class: 'snn-a-funnel-wrap' }, [holder, h('p', {}, del)]);
		}));
	}

	function funnelForm(goals) {
		var name = h('input', { type: 'text', placeholder: 'Funnel name' });
		var days = h('input', { type: 'number', min: 1, max: 365, value: 7, style: 'width:80px' });
		var steps = [];
		var stepBox = h('div', {});

		function addStep() {
			var select = h('select', {}, [h('option', { value: '' }, 'Choose a goal…')].concat(goals.map(function (g) {
				return h('option', { value: String(g.id) }, g.name);
			})));
			steps.push(select);
			stepBox.appendChild(h('p', {}, ['Step ' + steps.length + ': ', select]));
		}

		addStep();
		addStep();

		return h('details', { class: 'snn-a-add' }, [
			h('summary', {}, 'Add a funnel'),
			h('p', {}, [name, ' Window (days) ', days]),
			stepBox,
			h('p', {}, [
				h('button', { type: 'button', class: 'button', onclick: addStep }, 'Add step'), ' ',
				h('button', { type: 'button', class: 'button button-primary', onclick: function () {
					send('funnels', {
						name: name.value,
						window_days: Number(days.value),
						steps: steps.map(function (s) { return Number(s.value); }).filter(Boolean)
					}).then(function () { renderGoalsAgain(); }).catch(alertError);
				} }, 'Save funnel')
			])
		]);
	}

	function alertError(err) {
		window.alert(err.message);
	}

	// --------------------------------------------------- settings tools

	// ------------------------------------------------------------ import

	var STAGE_NAMES = {
		countries: 'countries', cities: 'cities', resources: 'pages', referrers: 'referrers',
		campaigns: 'campaigns', device_types: 'device types', device_oss: 'operating systems',
		device_browsers: 'browsers', visitors: 'visitors', sessions: 'sessions', views: 'views',
		clicks: 'clicks', totals: 'totals', rollup: 'daily totals'
	};

	function renderImport() {
		var box = h('div', {}, loading());
		root.appendChild(box);
		var running = false;

		function paint(data) {
			clear(box);
			var d = data.detect;
			var s = data.status || {};

			if (!d.available) {
				box.appendChild(card('Independent Analytics', [
					h('p', { text: 'No Independent Analytics data was found on this site. Install and use that plugin first, then come back here.' })
				]));
				return;
			}

			var busy = s.state === 'running';
			var done = s.state === 'done';
			var hasError = s.state === 'error';
			var label = h('p', {}, [
				h('strong', { text: 'Status: ' }),
				s.state === 'running' ? 'Importing' : s.state === 'done' ? 'Finished' : s.state === 'error' ? 'Stopped' : 'Not started',
				s.stage && busy ? ' · ' + (STAGE_NAMES[s.stage] || s.stage) : ''
			]);

			box.appendChild(card('Independent Analytics data', [
				h('p', { text: 'Found about ' + n(d.sessions) + ' sessions, ' + n(d.views) + ' views and ' + n(d.clicks) + ' clicks. The numbers are estimates.' }),
				h('p', { class: 'description', text: 'The importer only reads the old tables. They are never changed or deleted. Each step handles 5,000 rows, so the import works on shared hosting.' }),
				label,
				s.message ? h('p', { class: hasError ? 'snn-a-error' : 'description', text: s.message }) : null,
				h('p', {}, [
					!busy && !done ? h('button', { type: 'button', class: 'button button-primary', disabled: cfg.canManage ? null : true, onclick: function () {
						send('import/start').then(function (st) { paint({ detect: d, status: st }); runLoop(); }).catch(alertError);
					} }, hasError ? 'Resume import' : 'Start import') : null,
					busy ? h('button', { type: 'button', class: 'button', onclick: function () {
						send('import/cancel').then(function (st) { running = false; paint({ detect: d, status: st }); }).catch(alertError);
					} }, 'Cancel') : null,
					done ? h('span', { class: 'description', text: 'Already imported. Use “Delete all data” in Settings to import again.' }) : null
				])
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
					alertError(err);
				});
			}());
		}

		function load() {
			api('import').then(paint).catch(function (err) {
				clear(box);
				box.appendChild(failed(err));
			});
		}

		load();
	}

	function renderTools() {
		var box = h('div', { class: 'snn-a-tools' }, loading());
		tools.appendChild(box);

		var status = null;
		var poll = null;

		function paint(state) {
			status = state;
			clear(box);
			var busy = state.state === 'queued' || state.state === 'downloading';
			var geoText = state.installed
				? 'Installed (' + Math.round(state.size / 1048576) + ' MB). Cities are recorded for new sessions.'
				: 'Not installed. Countries still work from CDN headers, but cities need this file.';

			box.appendChild(card('City database (DB-IP Lite, free)', [
				h('p', { text: geoText }),
				state.message ? h('p', { class: state.state === 'error' ? 'snn-a-error' : 'description', text: state.message }) : null,
				h('p', {}, [
					h('button', { type: 'button', class: 'button button-primary', disabled: busy || !cfg.canManage ? true : null, onclick: function () {
						send('geo/download').then(function (s) { paint(s); startPolling(); }).catch(alertError);
					} }, state.installed ? 'Update database' : 'Download database'), ' ',
					state.installed ? h('button', { type: 'button', class: 'button', disabled: busy ? true : null, onclick: function () {
						if (window.confirm('Remove the city database?')) {
							send('geo/remove').then(paint).catch(alertError);
						}
					} }, 'Remove') : null
				]),
				h('p', { class: 'description', text: 'Downloads from download.db-ip.com in the background. It is licensed CC BY 4.0, and attribution is shown here: IP geolocation by DB-IP.' })
			]));

			box.appendChild(card('Daily totals', [
				h('p', { text: 'Rebuilds every daily total from the raw visits. Run this after importing data or changing the timezone.' }),
				h('p', {}, h('button', { type: 'button', class: 'button', onclick: function () {
					send('admin/rebuild').then(function (r) {
						window.alert('Rebuilt ' + n(r.days) + ' days.');
					}).catch(alertError);
				} }, 'Rebuild daily totals'))
			]));

			var confirmBox = h('input', { type: 'text', placeholder: 'Type DELETE' });
			box.appendChild(card('Delete all data', [
				h('p', { class: 'snn-a-error', text: 'Deletes every visit, session, page, referrer, campaign and daily total. Goals, funnels and settings are kept.' }),
				h('p', {}, [confirmBox, ' ', h('button', { type: 'button', class: 'button button-link-delete', onclick: function () {
					send('admin/delete-data', { confirm: confirmBox.value }).then(function () {
						window.alert('All statistics were deleted.');
						window.location.reload();
					}).catch(alertError);
				} }, 'Delete all data')])
			]));
		}

		function refresh() {
			return api('geo').then(function (state) {
				paint(state);
				return state;
			}).catch(function (err) {
				clear(box);
				box.appendChild(failed(err));
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

		if (view === 'settings') {
			if (tools) {
				renderTools();
			}
			return;
		}
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
