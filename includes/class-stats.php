<?php
/**
 * Read queries for the dashboard and the detail pages.
 *
 * Reads come from the daily summaries (d_*), except:
 *  - unique visitors over a range, which count distinct visitors in raw sessions
 *    (cached 10 minutes in a transient), because daily uniques cannot be added up;
 *  - realtime and journeys, which are inherently raw.
 * Values for pages, campaigns and devices are sums of daily figures. Visitors in
 * those tables are therefore daily uniques added together, and the UI says so.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Stats {

	const PAGE_ORDER = [ 'views', 'visitors', 'duration', 'entrances', 'exits', 'title' ];

	// ---------------------------------------------------------------- overview

	public static function overview( array $r ): array {
		global $wpdb;

		$series = $wpdb->get_results( $wpdb->prepare(
			'SELECT day, sessions, visitors, views, bounces, duration_sum FROM ' . DB::table( 'd_site' ) . ' WHERE day BETWEEN %d AND %d ORDER BY day',
			$r['from'],
			$r['to']
		), ARRAY_A );

		return [
			'current'  => self::site_totals( $r['from'], $r['to'] ),
			'previous' => self::site_totals( $r['prev_from'], $r['prev_to'] ),
			'series'   => self::fill_series( $series, $r['from'], $r['to'] ),
		];
	}

	public static function site_totals( int $from, int $to ): array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT COALESCE(SUM(sessions), 0) AS sessions, COALESCE(SUM(views), 0) AS views,
			        COALESCE(SUM(bounces), 0) AS bounces, COALESCE(SUM(duration_sum), 0) AS duration_sum,
			        COALESCE(SUM(new_visitors), 0) AS new_visitors, COALESCE(SUM(events), 0) AS events
			 FROM ' . DB::table( 'd_site' ) . ' WHERE day BETWEEN %d AND %d',
			$from,
			$to
		), ARRAY_A );

		$t = array_map( 'intval', (array) $row );

		$t['visitors']          = self::unique_visitors( $from, $to );
		$t['bounce_rate']       = $t['sessions'] > 0 ? round( 100 * $t['bounces'] / $t['sessions'], 1 ) : 0;
		$t['avg_duration']      = $t['sessions'] > 0 ? (int) round( $t['duration_sum'] / $t['sessions'] ) : 0;
		$t['views_per_session'] = $t['sessions'] > 0 ? round( $t['views'] / $t['sessions'], 2 ) : 0;

		return $t;
	}

	/** Distinct visitors whose sessions started in the range. Cached, because it reads raw sessions. */
	public static function unique_visitors( int $from, int $to ): int {
		global $wpdb;

		// The key changes whenever the daily totals are rebuilt, so a count can never outlive the data it was taken from.
		$key    = 'snn_a_uv_' . md5( $from . '-' . $to . '-' . (int) get_option( Rollup::MARK, 0 ) );
		$cached = get_transient( $key );
		if ( $cached !== false ) {
			return (int) $cached;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(DISTINCT visitor_id) FROM ' . DB::table( 'sessions' ) . ' WHERE started_at >= %d AND started_at < %d',
			Ranges::day_start( $from ),
			Ranges::day_start( $to + 1 )
		) );

		set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/** One entry per day, with zeros where a day has no row. */
	public static function fill_series( array $rows, int $from, int $to ): array {
		$by_day = [];
		foreach ( $rows as $row ) {
			$by_day[ (int) $row['day'] ] = array_map( 'intval', $row );
		}

		$out = [];
		for ( $day = $from; $day <= $to; $day++ ) {
			$d     = $by_day[ $day ] ?? [];
			$out[] = [
				'day'          => $day,
				'date'         => gmdate( 'Y-m-d', $day * 86400 ),
				'sessions'     => $d['sessions'] ?? 0,
				'visitors'     => $d['visitors'] ?? 0,
				'views'        => $d['views'] ?? 0,
				'bounces'      => $d['bounces'] ?? 0,
				'duration_sum' => $d['duration_sum'] ?? 0,
			];
		}

		return $out;
	}

	// ------------------------------------------------------------------ pages

	/**
	 * Titles were saved with HTML entities (for example &#8211; for an en dash), so decode them for display.
	 */
	public static function clean_title( string $title ): string {
		return html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/** Event type labels, shared by the timeline and the interactions report. */
	const EVENT_LABELS = [
		1  => 'Outbound link',
		2  => 'Download',
		3  => 'Email link',
		4  => 'Phone link',
		5  => 'Custom event',
		6  => 'Goal click',
		7  => 'Internal link',
		8  => 'Button click',
		9  => 'Form submit',
		10 => 'Text selection',
		11 => 'Scroll depth',
	];

	/**
	 * Interaction counts by type, plus the most common targets, for the Journeys screen.
	 */
	public static function interactions( array $r ): array {
		global $wpdb;

		$events  = DB::table( 'events' );
		$targets = DB::table( 'event_targets' );
		$from    = Ranges::day_start( $r['from'] );
		$to      = Ranges::day_start( $r['to'] + 1 ) - 1;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.type, t.value, COUNT(*) AS total
			 FROM {$events} e
			 INNER JOIN {$targets} t ON t.id = e.target_id
			 WHERE e.created_at BETWEEN %d AND %d
			 GROUP BY e.type, t.value
			 ORDER BY total DESC
			 LIMIT 500",
			$from,
			$to
		), ARRAY_A );

		$types   = [];
		$top     = [];
		foreach ( $rows as $row ) {
			$type  = (int) $row['type'];
			$total = (int) $row['total'];
			$label = self::EVENT_LABELS[ $type ] ?? 'Event';

			$types[ $type ] = [
				'type'  => $type,
				'label' => $label,
				'total' => ( $types[ $type ]['total'] ?? 0 ) + $total,
			];
			$top[] = [
				'type'  => $type,
				'label' => $label,
				'value' => self::clean_title( (string) $row['value'] ),
				'total' => $total,
			];
		}

		usort( $types, static fn( $a, $b ) => $b['total'] <=> $a['total'] );

		return [
			'types'   => array_values( $types ),
			'targets' => array_slice( $top, 0, 25 ),
		];
	}

	public static function pages( array $r, array $args ): array {
		global $wpdb;

		$daily   = DB::table( 'd_pages' );
		$pages   = DB::table( 'pages' );
		$orderby = in_array( $args['orderby'], self::PAGE_ORDER, true ) ? $args['orderby'] : 'views';
		$dir     = strtolower( $args['order'] ) === 'asc' ? 'ASC' : 'DESC';
		$column  = [
			'views'     => 'views',
			'visitors'  => 'visitors',
			'duration'  => 'duration_sum',
			'entrances' => 'entrances',
			'exits'     => 'exits',
			'title'     => 'p.title',
		][ $orderby ];

		$where  = 'd.day BETWEEN %d AND %d';
		$params = [ $r['from'], $r['to'] ];
		if ( $args['search'] !== '' ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND (p.title LIKE %s OR p.path LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$per_page = min( 100, max( 1, $args['per_page'] ) );
		$offset   = max( 0, ( $args['page'] - 1 ) * $per_page );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT d.page_id) FROM {$daily} d JOIN {$pages} p ON p.id = d.page_id WHERE {$where}",
			...$params
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.title, p.path, p.post_type,
			        SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.duration_sum) AS duration_sum,
			        SUM(d.entrances) AS entrances, SUM(d.exits) AS exits
			 FROM {$daily} d JOIN {$pages} p ON p.id = d.page_id
			 WHERE {$where}
			 GROUP BY p.id, p.title, p.path, p.post_type
			 ORDER BY {$column} {$dir}, p.id ASC
			 LIMIT %d OFFSET %d",
			...array_merge( $params, [ $per_page, $offset ] )
		), ARRAY_A );

		return [
			'total' => $total,
			'rows'  => array_map( [ self::class, 'page_row' ], $rows ),
		];
	}

	private static function page_row( array $row ): array {
		$views = (int) $row['views'];

		return [
			'id'          => (int) $row['id'],
			'title'       => self::clean_title( (string) $row['title'] ),
			'path'        => (string) $row['path'],
			'post_type'   => (string) $row['post_type'],
			'views'       => $views,
			'visitors'    => (int) $row['visitors'],
			'avg_time'    => $views > 0 ? (int) round( (int) $row['duration_sum'] / $views ) : 0,
			'entrances'   => (int) $row['entrances'],
			'exits'       => (int) $row['exits'],
		];
	}

	/**
	 * Click positions for one page, summed over the range. $device 0 means every device.
	 * Cells are ordered by clicks, so the first one is the strongest.
	 */
	public static function heatmap( int $page_id, array $r, int $device ): array {
		global $wpdb;

		$heat  = DB::table( 'heat' );
		$where = 'page_id = %d AND day BETWEEN %d AND %d AND (%d = 0 OR device = %d)';
		$args  = [ $page_id, $r['from'], $r['to'], $device, $device ];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT x, y, SUM(clicks) AS clicks FROM {$heat} WHERE {$where} GROUP BY x, y ORDER BY clicks DESC LIMIT 5000",
			...$args
		), ARRAY_A );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(clicks), 0) FROM {$heat} WHERE {$where}",
			...$args
		) );

		$cells = array_map( static fn( $row ) => [ (int) $row['x'], (int) $row['y'], (int) $row['clicks'] ], $rows );

		return [
			'cells' => $cells,
			'max'   => $cells === [] ? 0 : $cells[0][2],
			'total' => $total,
		];
	}

	/**
	 * Recent cursor trails for one page. Each trail is a list of points: [x per 10000 of the page
	 * width, y in px from the top, t in ms since the view started].
	 */
	public static function trails( int $page_id, array $r, int $device ): array {
		global $wpdb;

		$table = DB::table( 'trails' );
		$from  = Ranges::day_start( $r['from'] );
		$to    = Ranges::day_start( $r['to'] + 1 );
		$where = 'page_id = %d AND created_at >= %d AND created_at < %d AND (%d = 0 OR device = %d)';
		$args  = [ $page_id, $from, $to, $device, $device ];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT HEX(samples) AS samples FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 50",
			...$args
		), ARRAY_A );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$args ) );

		return [
			'total'  => $total,
			'trails' => array_map( [ self::class, 'decode_samples' ], array_column( $rows, 'samples' ) ),
		];
	}

	/**
	 * Turns the hex of 6-byte samples back into [x, y, t, click] points. The tracker marks a click
	 * by setting the top bit of x, which is free because x never goes above 10000. A sample with x
	 * 0x7fff starts a later chunk: its t is the chunk's start in seconds, and the chunk's times count from there.
	 */
	private static function decode_samples( string $hex ): array {
		$points = [];
		$base   = 0;
		foreach ( str_split( $hex, 12 ) as $sample ) {
			$x = (int) hexdec( substr( $sample, 0, 4 ) );
			$t = (int) hexdec( substr( $sample, 8, 4 ) );
			if ( $x === 0x7fff ) {
				$base = $t * 1000;
				continue;
			}
			$points[] = [ $x & 0x7fff, (int) hexdec( substr( $sample, 4, 4 ) ), $base + $t, $x >= 0x8000 ? 1 : 0 ];
		}

		return $points;
	}

	public static function page_series( int $page_id, array $r ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT day, 0 AS sessions, visitors, views, 0 AS bounces, duration_sum FROM ' . DB::table( 'd_pages' ) . ' WHERE page_id = %d AND day BETWEEN %d AND %d ORDER BY day',
			$page_id,
			$r['from'],
			$r['to']
		), ARRAY_A );

		return self::fill_series( $rows, $r['from'], $r['to'] );
	}

	/**
	 * Side-by-side numbers for 2 to 4 pages: totals for the range and for the period before it,
	 * click counts and a daily series. Pages come back in the order they were asked for.
	 */
	public static function compare( array $ids, array $r ): array {
		global $wpdb;

		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) ), 0, 4 );
		if ( $ids === [] ) {
			return [];
		}

		$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$daily = DB::table( 'd_pages' );

		$info = [];
		foreach ( $wpdb->get_results( $wpdb->prepare(
			'SELECT id, title, path, post_type FROM ' . DB::table( 'pages' ) . " WHERE id IN ({$marks})",
			...$ids
		), ARRAY_A ) as $row ) {
			$info[ (int) $row['id'] ] = $row;
		}

		$totals = static function ( int $from, int $to ) use ( $wpdb, $daily, $marks, $ids ): array {
			$out = [];
			foreach ( $wpdb->get_results( $wpdb->prepare(
				"SELECT page_id, SUM(views) AS views, SUM(visitors) AS visitors, SUM(duration_sum) AS duration_sum,
				        SUM(entrances) AS entrances, SUM(exits) AS exits
				 FROM {$daily} WHERE page_id IN ({$marks}) AND day BETWEEN %d AND %d GROUP BY page_id",
				...array_merge( $ids, [ $from, $to ] )
			), ARRAY_A ) as $row ) {
				$views = (int) $row['views'];

				$out[ (int) $row['page_id'] ] = [
					'views'     => $views,
					'visitors'  => (int) $row['visitors'],
					'avg_time'  => $views > 0 ? (int) round( (int) $row['duration_sum'] / $views ) : 0,
					'entrances' => (int) $row['entrances'],
					'exits'     => (int) $row['exits'],
				];
			}

			return $out;
		};

		$empty    = [ 'views' => 0, 'visitors' => 0, 'avg_time' => 0, 'entrances' => 0, 'exits' => 0 ];
		$current  = $totals( $r['from'], $r['to'] );
		$previous = $totals( $r['prev_from'], $r['prev_to'] );

		$clicks = [];
		foreach ( $wpdb->get_results( $wpdb->prepare(
			'SELECT page_id, SUM(clicks) AS clicks FROM ' . DB::table( 'heat' ) . " WHERE page_id IN ({$marks}) AND day BETWEEN %d AND %d GROUP BY page_id",
			...array_merge( $ids, [ $r['from'], $r['to'] ] )
		), ARRAY_A ) as $row ) {
			$clicks[ (int) $row['page_id'] ] = (int) $row['clicks'];
		}

		$by_page = [];
		foreach ( $wpdb->get_results( $wpdb->prepare(
			"SELECT page_id, day, 0 AS sessions, visitors, views, 0 AS bounces, duration_sum
			 FROM {$daily} WHERE page_id IN ({$marks}) AND day BETWEEN %d AND %d ORDER BY day",
			...array_merge( $ids, [ $r['from'], $r['to'] ] )
		), ARRAY_A ) as $row ) {
			$by_page[ (int) $row['page_id'] ][] = $row;
		}

		$out = [];
		foreach ( $ids as $id ) {
			if ( ! isset( $info[ $id ] ) ) {
				continue;
			}

			$out[] = [
				'id'        => $id,
				'title'     => self::clean_title( (string) $info[ $id ]['title'] ),
				'path'      => (string) $info[ $id ]['path'],
				'post_type' => (string) $info[ $id ]['post_type'],
				'current'   => ( $current[ $id ] ?? $empty ) + [ 'clicks' => $clicks[ $id ] ?? 0 ],
				'previous'  => $previous[ $id ] ?? $empty,
				'series'    => self::fill_series( $by_page[ $id ] ?? [], $r['from'], $r['to'] ),
			];
		}

		return $out;
	}

	// -------------------------------------------------------------- referrers

	public static function referrers( array $r ): array {
		global $wpdb;

		$daily = DB::table( 'd_refs' );
		$refs  = DB::table( 'referrers' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.referrer_id, r.domain, COALESCE(r.type, 1) AS type,
			        SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors, SUM(d.views) AS views, SUM(d.bounces) AS bounces
			 FROM {$daily} d LEFT JOIN {$refs} r ON r.id = d.referrer_id
			 WHERE d.day BETWEEN %d AND %d
			 GROUP BY d.referrer_id, r.domain, COALESCE(r.type, 1)
			 ORDER BY sessions DESC",
			$r['from'],
			$r['to']
		), ARRAY_A );

		$domains = [];
		$types   = [];
		foreach ( $rows as $row ) {
			$type = (int) $row['type'];
			$item = [
				'id'       => (int) $row['referrer_id'],
				'domain'   => (string) ( $row['domain'] ?? '' ) !== '' ? (string) $row['domain'] : 'Direct',
				'type'     => $type,
				'sessions' => (int) $row['sessions'],
				'visitors' => (int) $row['visitors'],
				'views'    => (int) $row['views'],
				'bounces'  => (int) $row['bounces'],
			];
			$domains[] = $item;

			$types[ $type ] = ( $types[ $type ] ?? 0 ) + $item['sessions'];
		}

		return [
			'types'   => $types,
			'domains' => array_slice( $domains, 0, 200 ),
		];
	}

	// -------------------------------------------------------------- geography

	public static function geo( array $r ): array {
		global $wpdb;

		$daily     = DB::table( 'd_geo' );
		$countries = DB::table( 'countries' );
		$cities    = DB::table( 'cities' );

		$by_country = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.code, SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors, SUM(d.views) AS views
			 FROM {$daily} d LEFT JOIN {$countries} c ON c.id = d.country_id
			 WHERE d.day BETWEEN %d AND %d
			 GROUP BY d.country_id, c.code ORDER BY sessions DESC",
			$r['from'],
			$r['to']
		), ARRAY_A );

		$by_city = $wpdb->get_results( $wpdb->prepare(
			"SELECT ci.name, ci.region, c.code, SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors, SUM(d.views) AS views
			 FROM {$daily} d JOIN {$cities} ci ON ci.id = d.city_id LEFT JOIN {$countries} c ON c.id = d.country_id
			 WHERE d.day BETWEEN %d AND %d AND d.city_id > 0
			 GROUP BY d.city_id, ci.name, ci.region, c.code ORDER BY sessions DESC LIMIT 200",
			$r['from'],
			$r['to']
		), ARRAY_A );

		return [
			'countries' => array_map( static fn( $row ) => [
				'code'     => $row['code'] ?? null,
				'sessions' => (int) $row['sessions'],
				'visitors' => (int) $row['visitors'],
				'views'    => (int) $row['views'],
			], $by_country ),
			'cities'    => array_map( static fn( $row ) => [
				'name'     => (string) $row['name'],
				'region'   => (string) $row['region'],
				'code'     => (string) $row['code'],
				'sessions' => (int) $row['sessions'],
				'visitors' => (int) $row['visitors'],
				'views'    => (int) $row['views'],
			], $by_city ),
		];
	}

	// ---------------------------------------------------------------- devices

	public static function devices( array $r ): array {
		global $wpdb;

		$daily = DB::table( 'd_devices' );
		$ua    = DB::table( 'ua' );

		$by_device = $wpdb->get_results( $wpdb->prepare(
			"SELECT device, SUM(sessions) AS sessions, SUM(visitors) AS visitors
			 FROM {$daily} WHERE day BETWEEN %d AND %d GROUP BY device ORDER BY sessions DESC",
			$r['from'],
			$r['to']
		), ARRAY_A );

		$by_browser = $wpdb->get_results( $wpdb->prepare(
			"SELECT COALESCE(u.name, 'Unknown') AS name, SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors
			 FROM {$daily} d LEFT JOIN {$ua} u ON u.id = d.browser_id AND u.kind = 1
			 WHERE d.day BETWEEN %d AND %d GROUP BY name ORDER BY sessions DESC",
			$r['from'],
			$r['to']
		), ARRAY_A );

		$by_os = $wpdb->get_results( $wpdb->prepare(
			"SELECT COALESCE(u.name, 'Unknown') AS name, SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors
			 FROM {$daily} d LEFT JOIN {$ua} u ON u.id = d.os_id AND u.kind = 2
			 WHERE d.day BETWEEN %d AND %d GROUP BY name ORDER BY sessions DESC",
			$r['from'],
			$r['to']
		), ARRAY_A );

		$labels = [ 1 => 'Desktop', 2 => 'Mobile', 3 => 'Tablet' ];

		return [
			'devices'  => array_map( static fn( $row ) => [
				'device'   => (int) $row['device'],
				'label'    => $labels[ (int) $row['device'] ] ?? 'Unknown',
				'sessions' => (int) $row['sessions'],
				'visitors' => (int) $row['visitors'],
			], $by_device ),
			'browsers' => array_map( [ self::class, 'named_row' ], $by_browser ),
			'os'       => array_map( [ self::class, 'named_row' ], $by_os ),
		];
	}

	private static function named_row( array $row ): array {
		return [
			'name'     => (string) $row['name'],
			'sessions' => (int) $row['sessions'],
			'visitors' => (int) $row['visitors'],
		];
	}

	// -------------------------------------------------------------- campaigns

	public static function campaigns( array $r ): array {
		global $wpdb;

		$daily = DB::table( 'd_camps' );
		$camp  = DB::table( 'campaigns' );
		$utm   = DB::table( 'utm_values' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.campaign_id,
			        s.value AS source, m.value AS medium, c.value AS campaign, t.value AS term, k.value AS content,
			        SUM(d.sessions) AS sessions, SUM(d.visitors) AS visitors, SUM(d.views) AS views, SUM(d.conversions) AS conversions
			 FROM {$daily} d
			 LEFT JOIN {$camp} cp ON cp.id = d.campaign_id
			 LEFT JOIN {$utm} s ON s.id = cp.source_id
			 LEFT JOIN {$utm} m ON m.id = cp.medium_id
			 LEFT JOIN {$utm} c ON c.id = cp.campaign_id
			 LEFT JOIN {$utm} t ON t.id = cp.term_id
			 LEFT JOIN {$utm} k ON k.id = cp.content_id
			 WHERE d.day BETWEEN %d AND %d
			 GROUP BY d.campaign_id, s.value, m.value, c.value, t.value, k.value
			 ORDER BY sessions DESC LIMIT 200",
			$r['from'],
			$r['to']
		), ARRAY_A );

		return array_map( static fn( $row ) => [
			'id'          => (int) $row['campaign_id'],
			'source'      => $row['source'] ?? '',
			'medium'      => $row['medium'] ?? '',
			'campaign'    => $row['campaign'] ?? '',
			'term'        => $row['term'] ?? '',
			'content'     => $row['content'] ?? '',
			'sessions'    => (int) $row['sessions'],
			'visitors'    => (int) $row['visitors'],
			'views'       => (int) $row['views'],
			'conversions' => (int) $row['conversions'],
		], $rows );
	}

	// --------------------------------------------------------------- goals

	/** Conversions per goal in the range, from the daily goal summary. */
	public static function goal_totals( array $r ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT goal_id, SUM(conversions) AS conversions FROM ' . DB::table( 'd_goals' ) . ' WHERE day BETWEEN %d AND %d GROUP BY goal_id',
			$r['from'],
			$r['to']
		), ARRAY_A );

		$out = [];
		foreach ( $rows as $row ) {
			$out[ (int) $row['goal_id'] ] = (int) $row['conversions'];
		}

		return $out;
	}

	// ------------------------------------------------------------- realtime

	/** Visitors seen in the last 30 minutes. */
	public static function active_now(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(DISTINCT visitor_id) FROM ' . DB::table( 'sessions' ) . ' WHERE last_seen >= %d',
			time() - 1800
		) );
	}

	public static function realtime(): array {
		global $wpdb;

		$now      = time();
		$sessions = DB::table( 'sessions' );
		$views    = DB::table( 'views' );
		$pages    = DB::table( 'pages' );
		$refs     = DB::table( 'referrers' );
		$countries = DB::table( 'countries' );

		$active = self::active_now();

		$per_minute = $wpdb->get_results( $wpdb->prepare(
			"SELECT FLOOR(created_at / 60) AS minute, COUNT(*) AS views
			 FROM {$views} WHERE created_at >= %d GROUP BY minute ORDER BY minute",
			$now - 1800
		), ARRAY_A );

		$live_pages = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.title, p.path, COUNT(DISTINCT v.session_id) AS visitors
			 FROM {$views} v JOIN {$pages} p ON p.id = v.page_id
			 WHERE v.created_at >= %d GROUP BY p.id, p.title, p.path ORDER BY visitors DESC LIMIT 10",
			$now - 1800
		), ARRAY_A );

		$live_refs = $wpdb->get_results( $wpdb->prepare(
			"SELECT COALESCE(r.domain, 'Direct') AS domain, COUNT(DISTINCT s.visitor_id) AS visitors
			 FROM {$sessions} s LEFT JOIN {$refs} r ON r.id = s.referrer_id
			 WHERE s.last_seen >= %d GROUP BY domain ORDER BY visitors DESC LIMIT 10",
			$now - 1800
		), ARRAY_A );

		$live_countries = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.code, COUNT(DISTINCT s.visitor_id) AS visitors
			 FROM {$sessions} s JOIN {$countries} c ON c.id = s.country_id
			 WHERE s.last_seen >= %d GROUP BY c.code ORDER BY visitors DESC LIMIT 10",
			$now - 1800
		), ARRAY_A );

		return [
			'active'     => $active,
			'per_minute' => array_map( static fn( $row ) => [
				'minute' => (int) $row['minute'] * 60,
				'views'  => (int) $row['views'],
			], $per_minute ),
			'pages'      => array_map( static fn( $row ) => [
				'title'    => self::clean_title( (string) $row['title'] ),
				'path'     => (string) $row['path'],
				'visitors' => (int) $row['visitors'],
			], $live_pages ),
			'referrers'  => array_map( static fn( $row ) => [
				'domain'   => (string) $row['domain'],
				'visitors' => (int) $row['visitors'],
			], $live_refs ),
			'countries'  => array_map( static fn( $row ) => [
				'code'     => (string) $row['code'],
				'visitors' => (int) $row['visitors'],
			], $live_countries ),
		];
	}

	// ---------------------------------------------------------- recent views

	/** The latest page views, newest first, with who viewed them (country, device, browser). */
	public static function recent( int $limit ): array {
		global $wpdb;

		$views    = DB::table( 'views' );
		$sessions = DB::table( 'sessions' );
		$pages    = DB::table( 'pages' );
		$countries = DB::table( 'countries' );
		$ua       = DB::table( 'ua' );
		$limit    = min( 50, max( 1, $limit ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT v.created_at, p.title, p.path, c.code AS country, s.device, b.name AS browser, o.name AS os
			 FROM {$views} v
			 JOIN {$sessions} s ON s.id = v.session_id
			 JOIN {$pages} p ON p.id = v.page_id
			 LEFT JOIN {$countries} c ON c.id = s.country_id
			 LEFT JOIN {$ua} b ON b.id = s.browser_id
			 LEFT JOIN {$ua} o ON o.id = s.os_id
			 ORDER BY v.created_at DESC, v.id DESC LIMIT %d",
			$limit
		), ARRAY_A );

		$labels = [ 1 => 'Desktop', 2 => 'Mobile', 3 => 'Tablet' ];

		return array_map( static fn( $row ) => [
			'time'    => (int) $row['created_at'],
			'title'   => self::clean_title( (string) ( $row['title'] !== '' ? $row['title'] : $row['path'] ) ),
			'country' => (string) ( $row['country'] ?? '' ),
			'device'  => $labels[ (int) $row['device'] ] ?? 'Unknown',
			'browser' => (string) ( $row['browser'] ?? '' ),
			'os'      => (string) ( $row['os'] ?? '' ),
		], $rows );
	}

	// ------------------------------------------------------------- journeys

	public static function journeys( array $r, int $page, int $per_page ): array {
		global $wpdb;

		$sessions  = DB::table( 'sessions' );
		$pages     = DB::table( 'pages' );
		$views     = DB::table( 'views' );
		$refs      = DB::table( 'referrers' );
		$countries = DB::table( 'countries' );
		$from      = Ranges::day_start( $r['from'] );
		$to        = Ranges::day_start( $r['to'] + 1 );

		$per_page = min( 100, max( 1, $per_page ) );
		$offset   = max( 0, ( $page - 1 ) * $per_page );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$sessions} WHERE started_at >= %d AND started_at < %d",
			$from,
			$to
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.id, s.visitor_id, s.started_at, s.last_seen, s.views, s.events, s.duration, (SELECT COALESCE(SUM(v.duration), 0) FROM {$views} v WHERE v.session_id = s.id) AS active, s.is_bounce, s.is_new, s.device,
			        entry.title AS entry_title, entry.path AS entry_path,
			        xp.title AS exit_title, xp.path AS exit_path,
			        r.domain AS referrer, c.code AS country
			 FROM {$sessions} s
			 LEFT JOIN {$pages} entry ON entry.id = s.entry_page_id
			 LEFT JOIN {$pages} xp ON xp.id = s.exit_page_id
			 LEFT JOIN {$refs} r ON r.id = s.referrer_id
			 LEFT JOIN {$countries} c ON c.id = s.country_id
			 WHERE s.started_at >= %d AND s.started_at < %d
			 ORDER BY s.started_at DESC LIMIT %d OFFSET %d",
			$from,
			$to,
			$per_page,
			$offset
		), ARRAY_A );

		return [
			'total' => $total,
			'rows'  => array_map( static fn( $row ) => [
				'id'          => (int) $row['id'],
				'visitor'     => (int) $row['visitor_id'],
				'started_at'  => (int) $row['started_at'],
				'duration'    => (int) $row['active'],
				'length'      => (int) $row['duration'],
				'views'       => (int) $row['views'],
				'events'      => (int) $row['events'],
				'bounce'      => (bool) $row['is_bounce'],
				'new'         => (bool) $row['is_new'],
				'device'      => (int) $row['device'],
				'entry'       => self::clean_title( (string) ( $row['entry_title'] ?: $row['entry_path'] ) ),
				'exit'        => self::clean_title( (string) ( $row['exit_title'] ?: $row['exit_path'] ) ),
				'referrer'    => (string) ( $row['referrer'] ?? '' ) !== '' ? (string) $row['referrer'] : 'Direct',
				'country'     => $row['country'] ?? '',
			], $rows ),
		];
	}

	/**
	 * One session in full: who the visitor is, where the visit came from, and every page view in order
	 * with the clicks, form submits and other actions that happened on that page. Plus the visitor's other sessions.
	 */
	public static function journey( int $session_id ): ?array {
		global $wpdb;

		$sessions  = DB::table( 'sessions' );
		$views     = DB::table( 'views' );
		$events    = DB::table( 'events' );
		$pages     = DB::table( 'pages' );
		$targets   = DB::table( 'event_targets' );
		$visitors  = DB::table( 'visitors' );
		$countries = DB::table( 'countries' );
		$cities    = DB::table( 'cities' );
		$ua        = DB::table( 'ua' );
		$refs      = DB::table( 'referrers' );
		$camps     = DB::table( 'campaigns' );
		$utm       = DB::table( 'utm_values' );
		$trails    = DB::table( 'trails' );

		$session = $wpdb->get_row( $wpdb->prepare(
			"SELECT s.*, c.code AS country, ci.name AS city, ci.region AS region,
			        b.name AS browser, o.name AS os, r.domain AS referrer,
			        us.value AS utm_source, um.value AS utm_medium, uc.value AS utm_campaign,
			        vis.first_seen AS visitor_first_seen, vis.sessions AS visitor_sessions,
			        entry.title AS entry_title, entry.path AS entry_path,
			        xp.title AS exit_title, xp.path AS exit_path
			 FROM {$sessions} s
			 LEFT JOIN {$countries} c ON c.id = s.country_id
			 LEFT JOIN {$cities} ci ON ci.id = s.city_id
			 LEFT JOIN {$ua} b ON b.id = s.browser_id
			 LEFT JOIN {$ua} o ON o.id = s.os_id
			 LEFT JOIN {$refs} r ON r.id = s.referrer_id
			 LEFT JOIN {$camps} cp ON cp.id = s.campaign_id
			 LEFT JOIN {$utm} us ON us.id = cp.source_id
			 LEFT JOIN {$utm} um ON um.id = cp.medium_id
			 LEFT JOIN {$utm} uc ON uc.id = cp.campaign_id
			 LEFT JOIN {$visitors} vis ON vis.id = s.visitor_id
			 LEFT JOIN {$pages} entry ON entry.id = s.entry_page_id
			 LEFT JOIN {$pages} xp ON xp.id = s.exit_page_id
			 WHERE s.id = %d",
			$session_id
		), ARRAY_A );
		if ( ! $session ) {
			return null;
		}

		$view_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT v.id, v.created_at, v.duration, v.seq, p.title, p.path, (t.view_id IS NOT NULL) AS has_trail, HEX(t.samples) AS samples
			 FROM {$views} v
			 JOIN {$pages} p ON p.id = v.page_id
			 LEFT JOIN {$trails} t ON t.view_id = v.id
			 WHERE v.session_id = %d ORDER BY v.created_at, v.id",
			$session_id
		), ARRAY_A );

		$event_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.created_at, e.type, e.view_id, t.value
			 FROM {$events} e JOIN {$targets} t ON t.id = e.target_id
			 WHERE e.session_id = %d ORDER BY e.created_at, e.id",
			$session_id
		), ARRAY_A );

		$active = (int) array_sum( array_column( $view_rows, 'duration' ) );
		$steps  = [];
		foreach ( $view_rows as $v ) {
			$steps[ (int) $v['id'] ] = [
				'id'        => (int) $v['id'],
				'seq'       => (int) $v['seq'],
				'at'        => (int) $v['created_at'],
				'title'     => self::clean_title( (string) $v['title'] ),
				'path'      => (string) $v['path'],
				'duration'  => (int) $v['duration'],
				'has_trail' => (bool) $v['has_trail'],
				'trail'     => $v['samples'] ? self::decode_samples( (string) $v['samples'] ) : [],
				'events'    => [],
			];
		}

		// An action goes under the page view it happened on. Anything without a page view is kept separately.
		$labels     = self::EVENT_LABELS;
		$unattached = [];
		foreach ( $event_rows as $e ) {
			$event = [
				'at'    => (int) $e['created_at'],
				'label' => $labels[ (int) $e['type'] ] ?? 'Event',
				'value' => (string) $e['value'],
			];
			if ( isset( $steps[ (int) $e['view_id'] ] ) ) {
				$steps[ (int) $e['view_id'] ]['events'][] = $event;
			} else {
				$unattached[] = $event;
			}
		}

		$others = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, started_at, views, duration, is_bounce FROM {$sessions}
			 WHERE visitor_id = %d AND id <> %d ORDER BY started_at DESC LIMIT 10",
			(int) $session['visitor_id'],
			$session_id
		), ARRAY_A );

		return [
			'session'    => [
				'id'         => (int) $session['id'],
				'visitor'    => (int) $session['visitor_id'],
				'started_at' => (int) $session['started_at'],
				'last_seen'  => (int) $session['last_seen'],
				'duration'   => $active,
				'length'     => (int) $session['duration'],
				'views'      => (int) $session['views'],
				'events'     => (int) $session['events'],
				'bounce'     => (bool) $session['is_bounce'],
				'new'        => (bool) $session['is_new'],
				'device'     => (int) $session['device'],
				'country'    => (string) ( $session['country'] ?? '' ),
				'city'       => (string) ( $session['city'] ?? '' ),
				'region'     => (string) ( $session['region'] ?? '' ),
				'browser'    => (string) ( $session['browser'] ?? '' ),
				'os'         => (string) ( $session['os'] ?? '' ),
				'browser_version' => $session['browser_ver'] === null ? null : (int) $session['browser_ver'],
				'screen'     => $session['screen_w'] === null ? null : [ (int) $session['screen_w'], (int) $session['screen_h'] ],
				'viewport'   => $session['view_w'] === null ? null : [ (int) $session['view_w'], (int) $session['view_h'] ],
				'language'   => (string) ( $session['lang'] ?? '' ),
				'referrer'   => (string) ( $session['referrer'] ?? '' ) !== '' ? (string) $session['referrer'] : 'Direct',
				'campaign'   => [
					'source'   => (string) ( $session['utm_source'] ?? '' ),
					'medium'   => (string) ( $session['utm_medium'] ?? '' ),
					'campaign' => (string) ( $session['utm_campaign'] ?? '' ),
				],
				'entry'      => self::clean_title( (string) ( $session['entry_title'] ?: $session['entry_path'] ) ),
				'exit'       => self::clean_title( (string) ( $session['exit_title'] ?: $session['exit_path'] ) ),
			],
			'visitor'    => [
				'first_seen' => (int) ( $session['visitor_first_seen'] ?? 0 ),
				'sessions'   => (int) ( $session['visitor_sessions'] ?? 0 ),
			],
			'steps'      => array_values( $steps ),
			'unattached' => $unattached,
			'others'     => array_map( static fn( $row ) => [
				'id'         => (int) $row['id'],
				'started_at' => (int) $row['started_at'],
				'views'      => (int) $row['views'],
				'duration'   => (int) $row['duration'],
				'bounce'     => (bool) $row['is_bounce'],
			], $others ),
		];
	}
}
