# SNN Analytics — Plan

A small, fast, self-hosted analytics plugin for WordPress, built for cheap shared hosting.
It replaces Independent Analytics (IAWP) and can import IAWP's existing data.

Status: **Approved. Step 1 (core: DB, tracker, `t.php`, REST fallback) is implemented at the repo root.** Not yet run in a live WordPress (see "Verification status").

**Decisions from the owner:**
- Minimum **PHP 8.0**. MySQL 5.7 / MariaDB 10.3+ is fine.
- Visitor salt: **never rotate** is preferred. Default is a **1-year cap** (`salt_rotation = yearly`). Other values are `never`, `monthly` and `daily`.
- Charts: **Chart.js** (downloaded locally, not CDN). Other JS libraries are allowed if they are downloaded locally.
- City-level location **is wanted**, with free data. The default source is **DB-IP Lite City** (CC BY 4.0). It is downloaded from Settings and read by a built-in MMDB reader. The city DB is still optional, and country always works without it.

---

## 1. Goals

1. **Low cost per hit.** One pageview should cost about 1 small PHP boot plus 1–3 indexed queries. The tracker JS stays under 2 KB gzipped.
2. **Fast dashboard on big data.** Charts read from pre-aggregated daily tables, not raw rows.
3. **Security first.** Treat every byte from the browser as hostile. Validate it, cap it, cast it, use prepared statements, escape output.
4. **Privacy friendly.** No cookies and no raw IPs stored. Visitors are identified by a salted hash.
5. **Simple.** Plain PHP with no Composer dependencies. Plain JS with no build step. One local chart library.
6. **Keep old data.** Import from IAWP tables in batches that can resume.

### Not in scope (from IAWP)
WooCommerce/EDD/SureCart order tracking, form plugin integrations, email reports, MainWP, Freemius, custom report builder, favicons fetching, admin-bar stats. These can be added later.

---

## 2. Pages / Features

| Admin page | Contents |
|---|---|
| **SNN Analytics** (dashboard) | KPI row (visitors, views, sessions, avg duration, bounce rate, views/session) with the change vs the previous period. Main chart. Draggable cards: Realtime, Top Pages, Referrers, Countries (+ map), Devices / Browsers / OS, Campaigns, Goals/Conversions. Each card has a **"See more →"** button that opens the detail page with the same date range. Range picker: **Today, 7d, 30d, 90d, 12m, Custom**. |
| **Realtime** | Visitors active in the last 5 min, views per minute for the last 30 min, live pages, live referrers and countries. Polls every 10 s, and stops polling when the tab is hidden. |
| **Pages** | Table with sort, search and pagination: views, visitors, avg time, bounce, entrances, exits. Click a row to see that page's chart. |
| **Referrers** | Grouped by type (Direct / Search / Social / Referral / Ad / Email) and by domain. |
| **Geographic** | Country table and a simple SVG world map. City table if the geo DB is installed. |
| **Devices** | Device type, browser, OS. |
| **Campaigns** | UTM source / medium / campaign / term / content, plus a **Campaign URL builder**. |
| **Goals & Funnels** | Define goals (page visited, click on a link/selector, outbound click, mailto/tel, custom JS event `snnA.track('signup')`). Define funnels as ordered steps, then view conversion and drop-off per step. |
| **Journeys** | List of sessions: visitor, entry/exit page, pages count, duration, referrer, country, device. Click to see the **timeline** of every page and event in order, with time spent on each, plus the visitor's other sessions. |
| **Settings** | Track logged-in users (on/off, per role), excluded IPs/roles, data retention (months), geo DB download, salt rotation, IAWP import, delete all data, uninstall cleanup option. |

Drag-to-reorder cards uses the native HTML5 Drag & Drop API. Card order and hidden cards are saved **per admin user** in `user_meta` (`snn_a_dashboard_layout`).

---

## 3. Tracking architecture (the hot path)

```
Browser ──sendBeacon(JSON ≤ 2KB)──▶  /wp-content/plugins/snn-analytics/t.php
                                     (SHORTINIT WordPress: only $wpdb, no plugins/theme)
                                     validate → bot filter → rate limit → hash → write
```

**Decision: use a lightweight endpoint that loads WordPress with `SHORTINIT`, instead of the REST API or admin-ajax.**
- REST and admin-ajax load the whole WordPress stack (all plugins and the theme) on every hit, which takes 50–300 ms on poor hosting.
- `SHORTINIT` loads only the DB layer, about 5–15 ms. IAWP's standalone click endpoint works the same way.
- **Fallback:** if `t.php` is blocked by the host (some block direct PHP in plugins), the tracker switches to a REST route `snn-a/v1/t`. The switch happens automatically and is remembered in a 1-day option.

**Tracker JS (`assets/t.js`, vanilla, deferred, about 1.5 KB):**
- Sends a `pageview` on load. Sends `ping` heartbeats on `visibilitychange`/`pagehide` for accurate time-on-page (no interval timers in the background).
- Uses delegated click tracking for outbound links, downloads, mailto/tel and goal selectors.
- Exposes `window.snnA.track(name)` for custom events and conversions.
- Respects `navigator.doNotTrack` and `globalPrivacyControl` (optional setting).

**Page identity is signed by the server, which stops fake-page spam:**
PHP prints a small inline config on each page: `{r: resource_id/type, s: HMAC(resource, secret)}`. The endpoint rejects any hit whose signature fails. A bot therefore cannot fill the DB with invented URLs or titles. This still works with full-page caching, because the token is per page, not per user.

**Visitor ID (no cookies):**
`visitor_hash = first 8 bytes of sha256(salt + ip + user_agent + site_host)`, stored as `BINARY(8)`. The IP is used only in memory, never stored.
The salt rotates on a schedule you choose: never / yearly / monthly / daily. If you choose daily, returning visitors are not linked across days, which is the most private option. The default is **never**, so journeys stay linked across days.

**Sessions:** a session continues if the same visitor has a hit within 30 minutes. Lookup uses the index `(visitor_id, last_seen)`.

**Bot / abuse protection on the endpoint:**
- Accept only `POST`, `Content-Type` text/plain or JSON, and body size ≤ 4 KB.
- Strict schema: every field is type-checked and length-capped. Unknown fields are dropped. Integers go through `(int)` with a range check, and strings are trimmed with `mb_substr`.
- A known-bot UA regex and an empty-UA rejection, plus "headless" hints.
- A per-visitor-hash rate limit: at most N hits per minute, using APCu when it exists, otherwise a rolling counter on the session row (no extra table and no option writes).
- Referrer and UTM values are parsed with `wp_parse_url`, and only host and path are kept. Query strings are dropped except UTM keys. Values are lowercased and capped.
- The response is always an empty `204`, so it never leaks information.

---

## 4. Database design

### Principles
- **Integers everywhere.** Timestamps are `INT UNSIGNED` unix seconds (UTC), which covers dates up to the year 2106. They are 4 bytes against 5–8 for DATETIME, compare fast, and avoid timezone bugs. Days are `MEDIUMINT UNSIGNED` = days since 1970-01-01, which is small and range-friendly. The site timezone is applied when bucketing, at query time.
- **Normalized dimensions.** Each string (URL, referrer domain, browser, UTM value…) is stored **once** in a lookup table. Fact tables hold only small ints. The smallest suitable type is used: `TINYINT` for device types, `SMALLINT` for countries/browsers/OS, `INT` for pages/referrers, `BIGINT` only for raw facts.
- **Dedup by hash.** A lookup table with long strings gets a `hash BINARY(8)` column and a `UNIQUE` index, so inserts use `INSERT … ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)` (1 query, no race). A long VARCHAR is never indexed directly.
- **InnoDB, utf8mb4**, no foreign key constraints. FKs are enforced in code because constraints are slow and break on shared hosts. Columns are named `*_id` so the schema stays readable.
- **Two layers:** raw facts (sessions/views/events) for journeys and realtime, and **daily rollups** for every chart and table. The dashboard never scans raw rows except for "today", which is small.
- Table prefix: `{$wpdb->prefix}snn_a_`.

### Lookup tables (dimensions)

```sql
snn_a_pages        id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE,
                   type TINYINT UNSIGNED,          -- 1 singular,2 home,3 term,4 author,5 date,6 search,7 404,8 post_type_archive
                   object_id BIGINT UNSIGNED NULL, -- post_id / term_id / author_id
                   path VARCHAR(512), title VARCHAR(255), post_type VARCHAR(32)
                   INDEX(type, object_id)
snn_a_referrers    id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE, domain VARCHAR(253),
                   type TINYINT UNSIGNED           -- 1 direct,2 search,3 social,4 referral,5 ad,6 email
snn_a_campaigns    id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE,
                   source_id, medium_id, campaign_id, term_id, content_id  INT UNSIGNED (→ snn_a_utm_values)
snn_a_utm_values   id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE, value VARCHAR(255)
snn_a_countries    id SMALLINT UNSIGNED PK, code CHAR(2) UNIQUE, name VARCHAR(64), continent CHAR(2)  -- pre-seeded ISO list
snn_a_cities       id INT UNSIGNED PK AI, country_id SMALLINT, hash BINARY(8) UNIQUE, region VARCHAR(64), name VARCHAR(64)
snn_a_ua           id SMALLINT UNSIGNED PK AI, kind TINYINT (1 browser,2 os), name VARCHAR(32), UNIQUE(kind,name)
```
Device type is a plain `TINYINT` enum (1 desktop, 2 mobile, 3 tablet) with no table.

### Fact tables

```sql
snn_a_visitors     id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE,
                   first_seen INT UNSIGNED, last_seen INT UNSIGNED,
                   sessions SMALLINT UNSIGNED, wp_user_id BIGINT UNSIGNED NULL

snn_a_sessions     id BIGINT UNSIGNED PK AI,
                   visitor_id INT UNSIGNED, started_at INT UNSIGNED, last_seen INT UNSIGNED,
                   entry_page_id INT UNSIGNED, exit_page_id INT UNSIGNED,
                   referrer_id INT UNSIGNED, campaign_id INT UNSIGNED,
                   country_id SMALLINT UNSIGNED, city_id INT UNSIGNED,
                   device TINYINT UNSIGNED, browser_id SMALLINT UNSIGNED, os_id SMALLINT UNSIGNED,
                   views SMALLINT UNSIGNED, events SMALLINT UNSIGNED,
                   duration MEDIUMINT UNSIGNED,          -- seconds
                   is_new TINYINT(1), is_bounce TINYINT(1)  -- maintained on write
                   INDEX(started_at), INDEX(last_seen), INDEX(visitor_id, last_seen)

snn_a_views        id BIGINT UNSIGNED PK AI,
                   session_id BIGINT UNSIGNED, page_id INT UNSIGNED,
                   created_at INT UNSIGNED, duration MEDIUMINT UNSIGNED, -- filled by next view / ping
                   seq SMALLINT UNSIGNED                                -- order inside session
                   INDEX(session_id, seq), INDEX(created_at), INDEX(page_id, created_at)

snn_a_events       id BIGINT UNSIGNED PK AI,
                   session_id BIGINT UNSIGNED, view_id BIGINT UNSIGNED, created_at INT UNSIGNED,
                   type TINYINT UNSIGNED,     -- 1 outbound,2 download,3 mailto,4 tel,5 custom,6 goal-click
                   target_id INT UNSIGNED     -- → snn_a_event_targets (url / custom name)
                   INDEX(session_id), INDEX(created_at), INDEX(target_id, created_at)
snn_a_event_targets id INT UNSIGNED PK AI, hash BINARY(8) UNIQUE, value VARCHAR(512)
```

### Goals & funnels

```sql
snn_a_goals        id SMALLINT UNSIGNED PK AI, name VARCHAR(100),
                   type TINYINT (1 page,2 event_target,3 custom_event), match_type TINYINT (1 exact,2 contains,3 prefix),
                   value VARCHAR(255), is_active TINYINT(1), created_at INT UNSIGNED
snn_a_conversions  goal_id SMALLINT UNSIGNED, session_id BIGINT UNSIGNED, created_at INT UNSIGNED,
                   PRIMARY KEY(goal_id, session_id), INDEX(created_at)  -- one per session per goal
snn_a_funnels      id SMALLINT UNSIGNED PK AI, name VARCHAR(100), window_sec INT UNSIGNED
snn_a_funnel_steps funnel_id SMALLINT, position TINYINT, goal_id SMALLINT, PRIMARY KEY(funnel_id, position)
```
Goals are matched **at write time**: active goals are cached in an option and checked in memory. A conversion is one tiny insert. Funnel reports are computed from `snn_a_conversions` (ordered `created_at` per session) and cached in a transient.

### Daily rollups (what the dashboard reads)

```sql
snn_a_d_site     day MEDIUMINT UNSIGNED PK, visitors INT, sessions INT, views INT,
                 bounces INT, duration_sum INT UNSIGNED, new_visitors INT
snn_a_d_pages    day, page_id      → views, visitors, entrances, exits, duration_sum   PK(day,page_id)
snn_a_d_refs     day, referrer_id  → sessions, visitors, views, bounces               PK(day,referrer_id)
snn_a_d_geo      day, country_id, city_id → sessions, visitors, views                 PK(day,country_id,city_id)
snn_a_d_devices  day, device, browser_id, os_id → sessions, visitors                 PK(day,device,browser_id,os_id)
snn_a_d_camps    day, campaign_id  → sessions, visitors, views, conversions           PK(day,campaign_id)
snn_a_d_goals    day, goal_id      → conversions                                      PK(day,goal_id)
```
- **Rollup job:** WP-Cron runs every 15 min. It recomputes **today and yesterday** using `INSERT … SELECT … ON DUPLICATE KEY UPDATE` (idempotent and safe to re-run). Older days are final.
- A 90-day chart reads at most 90 rows from `snn_a_d_site`. It stays fast with 10M+ raw views.
- **Days are bucketed in the site timezone** (`wp_timezone()`), stored as day-number.
- **Unique visitors over a range** cannot be summed from daily rows. Range uniques are computed with `COUNT(DISTINCT visitor_id)` on `sessions` using the `started_at` index, and cached in a transient for 10 min.
- **Retention:** a daily cron deletes raw rows older than X months in chunks of 5k (`DELETE … LIMIT`). Rollups are kept forever because they are tiny.

### Why this is better than IAWP's schema
- IAWP uses `BIGINT(20)` for every key, `DATETIME` timestamps and `VARCHAR(2048)` UTM columns, and has no rollups (it aggregates raw views on every report load). This design is about 3–5× smaller per row, and dashboard queries are O(days) instead of O(views).

---

## 5. Geo & device detection
- **Country, priority order:** a CDN header (`CF-IPCountry`, `X-Country-Code`, Sucuri, Cloudways…). This is free and instant. If none is present and a geo DB is installed, the plugin uses a **bundled tiny pure-PHP MMDB reader** (no Composer) on the free **DB-IP Lite** country/city `.mmdb`, downloaded from Settings into `wp-content/uploads/snn-analytics/` and protected with `.htaccess` and an index.php. Otherwise the country is "Unknown".
- **Device/browser/OS:** a small regex classifier (about 40 rules) in PHP, run once per new session, not per view. No giant UA library.

---

## 6. Admin UI tech
- Vanilla JS modules with no build step, loaded **only on SNN Analytics admin screens**.
- **Chart.js 4 (bundled locally, not CDN)** for line/bar/doughnut. It is only loaded in the admin, so the frontend never pays for it. The world map is an inline lightweight SVG.
- Data comes from REST routes `snn-a/v1/stats/*` with `permission_callback` → `current_user_can('snn_a_view')` and a REST nonce. A custom capability is granted to administrators on activation, so it can be granted to other roles too.
- Responses are cached in transients keyed by (endpoint, range, filters) for 5 min for past ranges and 30 s when the range includes today.
- All output is escaped (`esc_html`, `textContent` in JS, never `innerHTML` with data).

---

## 7. Security checklist
- [ ] `ABSPATH`/direct-access guard in every PHP file except `t.php`, which has its own strict bootstrap.
- [ ] Every SQL statement uses `$wpdb->prepare()`. Table names come from a whitelist of constants. ORDER BY/columns come only from whitelists.
- [ ] Every REST and AJAX route checks the capability and nonce. Settings use `register_setting` with a sanitize callback for each field.
- [ ] Tracker input: strict schema, casts, length caps, HMAC page signature, bot filter, rate limit, body size limit.
- [ ] No raw IP stored. The salt and HMAC secret are generated with `random_bytes` and stored in options (`autoload=no`).
- [ ] Uploads directory for the geo DB is protected. Downloads are verified (size and MMDB magic bytes).
- [ ] Import, delete-data and settings actions require `manage_options`, a nonce and a confirmation.
- [ ] Output escaping in PHP and safe DOM APIs in JS.
- [ ] `uninstall.php` drops tables only if the "delete data on uninstall" option is on.

---

## 8. IAWP import (migration)
- Detect `{prefix}independent_analytics_*` tables and show counts in Settings → Import.
- Import runs in **resumable chunks** (5k rows per AJAX request, with progress stored in an option), so it never times out on shared hosting.
- Order of import:
  1. Dimensions: resources → `pages` (map type, singular_id/term_id/author_id; title/url from cached_*), referrers + referrer_types → `referrers`, campaigns + utm_* → `campaigns`/`utm_values`, countries/cities, device_types/oss/browsers.
  2. `visitors`: the IAWP hash is re-hashed to BINARY(8). The old IDs stay internally consistent, but there is no link to new hits, which is unavoidable because the salts differ.
  3. `sessions` → `sessions` (DATETIME → unix, recompute duration, is_bounce).
  4. `views` → `views` (seq from order per session, duration from `next_viewed_at`).
  5. `clicks` + `click_targets`/`links` → `events`.
  6. Rebuild all daily rollups for the imported range.
- ID mapping uses temporary map tables `snn_a_tmp_map_*` (old_id INT PK → new_id), which are dropped at the end.
- Import is read-only on IAWP tables. The old data is never deleted; you delete IAWP yourself afterwards.
- Re-running is safe because each stage records its last processed old ID.

---

## 9. File structure

```
(repo root = plugin folder)
  snn-analytics.php          bootstrap, constants, autoloader
  t.php                      SHORTINIT tracking endpoint
  uninstall.php
  includes/
    class-db.php             schema, dbDelta, versioned migrations
    class-tracker.php        payload validation, session/view/event writes, goal matching
    class-frontend.php       enqueue t.js + signed page token
    class-ua.php             device/browser/OS parser
    class-geo.php            header detection + MMDB reader
    class-rollup.php         cron aggregation + retention
    class-stats.php          query layer for reports (whitelisted)
    class-rest.php           admin REST routes
    class-admin.php          menus, pages, settings
    class-import-iawp.php    chunked importer
  admin/views/*.php          page templates
  assets/t.js                tracker (frontend)
  assets/admin/*.js|css      dashboard, cards, drag-drop, realtime
  assets/vendor/chart.umd.min.js
```

---

## 10. Build order (milestones)
1. **Core:** bootstrap, DB schema, tracker JS + `t.php` + REST fallback, validation, sessions/views. ← verify hits land correctly
2. **Rollups + Dashboard:** KPIs, main chart, range picker, cards, drag-drop layout, "See more".
3. **Detail pages:** Pages, Referrers, Geo, Devices, Campaigns (+ URL builder).
4. **Realtime.**
5. **Journeys** (session list + timeline).
6. **Events, Goals, Funnels.**
7. **IAWP importer.**
8. **Settings, retention, uninstall, security pass.**

---

## 11. Open questions for you
1. **Visitor salt default:** "never rotate" (best journeys) or "daily" (most private, GDPR-friendlier)? The plan uses **never**.
2. **Chart library:** Chart.js (about 200 KB, admin only, quick to build) or uPlot (about 45 KB, faster, fewer chart types)? The plan uses **Chart.js**.
3. **City-level geo:** wanted, or is country enough? A city DB is about 60–120 MB on disk. The plan treats it as **optional**.
4. **Minimum PHP version:** the plan targets **PHP 7.4+** and MySQL 5.7 / MariaDB 10.3+.

*(All four answered. See "Decisions from the owner" at the top.)*

---

## 12. Step 1 notes: what was built and where it differs from the plan

**Built (repo root):** `snn-analytics.php` (bootstrap, activation), `t.php` (SHORTINIT endpoint), `assets/t.js` (tracker), `includes/` with `class-store`, `class-settings`, `class-db` (schema + upsert helpers), `class-validator`, `class-tracker`, `class-bot`, `class-ua`, `class-geo` (CDN headers only), `class-frontend` (enqueue + signed page config), `class-rest` (fallback route).

**Deviations from the plan, and why:**
- **Queries per hit:** about 6–9 small indexed statements, not 1–3. Each hit reads two options (settings and secrets, memoized per request), upserts the visitor, looks up the session, upserts the page, and inserts a view, plus the session update. Still single-digit milliseconds. Cut later if needed.
- **Options are JSON, read with `$wpdb` directly** instead of `get_option()`, so they work under SHORTINIT without the serializer or object cache.
- **Binary hashes:** PHP passes hex, SQL converts with `UNHEX()`. This keeps arbitrary bytes out of the SQL text.
- **Page identity is signed and is the only page input.** The server prints `{k, p, t, y, s}`. The browser echoes it back, and `s` is an HMAC over `k, p, t, y`. Keys for pages without an object (`search`, `404`, `date`, `blog`, `other`) are fixed, so no row can grow from user input.
- **`views.view_key`** (8 random bytes per page load, generated in the browser) identifies a view for pings and events. The plan did not have this column.
- **`sessions.is_bounce`** is maintained on write, not computed in rollups.
- **`countries`** stores only the ISO code for now. Names come with the Geographic page.
- **Direct traffic:** `sessions.referrer_id` is `NULL`. There is no "direct" row in `referrers`.
- **Fallback flag** for a blocked `t.php` is stored in `localStorage` for one day, not in a server option. Only a refusal (403/404/405/5xx) triggers the switch. A network error does not.
- **Per-visitor rate limit** uses APCu when available. Without APCu, only the 2000-views-per-session cap applies.
- **Admin users are not tracked** by default (`exclude_roles = administrator`). The UI for this is in Settings (step 8).
- **IP:** `CF-Connecting-IP` is trusted when present. Otherwise `REMOTE_ADDR` is used. Behind any other proxy, all visitors share one hash until a trusted-proxy setting exists.

**Verification status:**
- PHP syntax checked for all files with `php-parser`. JavaScript checked with `node --check`.
- Pure-PHP logic (validation, signatures, UA, bot filter, geo headers, tracker code paths) run in a PHP 8.3 WebAssembly runtime with a stub `$wpdb`. 45 checks pass. This found and fixed one bug: a WordPress-only constant used under SHORTINIT.
- **Not yet verified:** the generated SQL against a real MySQL/MariaDB server, `dbDelta` table creation, SHORTINIT loading in a live site, and the browser tracker in a real page. These need a WordPress test site.
