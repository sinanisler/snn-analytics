<?php
/**
 * The hot path. Turns one validated hit into rows: visitor, session, view or event.
 *
 * Runs both from t.php (SHORTINIT) and from the REST fallback, so it uses only
 * $wpdb and plain PHP. No WordPress helpers that need a full load.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Tracker {

	const MAX_BODY   = 4096;  // bytes accepted in one hit
	const RATE_LIMIT = 120;   // hits per visitor per minute (APCu only)
	const MAX_VIEWS  = 2000;  // views per session before it stops counting

	/**
	 * Entry point. Always produces the same response, so it never reveals what was stored.
	 */
	public static function process( string $raw, array $server ): void {
		if ( $raw === '' || strlen( $raw ) > self::MAX_BODY ) {
			return;
		}

		$in  = json_decode( $raw, true, 4 );
		$hit = is_array( $in ) ? Validator::hit( $in ) : null;
		if ( $hit === null ) {
			return;
		}

		$settings = Settings::get();
		$ua       = Validator::text( $server['HTTP_USER_AGENT'] ?? null, 512 );

		if ( Bot::is_bot( $ua, $server ) ) {
			return;
		}
		if ( $settings['respect_dnt'] && self::opted_out( $server ) ) {
			return;
		}

		// The visitor hash is the only identifier. The IP exists only in memory, never in a row.
		$vh = DB::key( Settings::salt() . '|' . self::client_ip( $server ) . '|' . $ua . '|' . $settings['site_host'] );
		if ( ! self::within_rate_limit( $vh ) ) {
			return;
		}

		$now = time();
		if ( $hit['e'] === 'pv' ) {
			self::pageview( $hit, $vh, $now, $ua, $server, $settings );
		} else {
			self::activity( $hit, $vh, $now, $settings );
		}
	}

	private static function pageview( array $hit, string $vh, int $now, string $ua, array $server, array $settings ): void {
		$visitor = self::visitor( $vh, $now );
		$session = self::session_for_visitor( $visitor['id'], $now, $settings['session_timeout'] );

		if ( $session !== null && $session['views'] >= self::MAX_VIEWS ) {
			return;
		}

		$page_id = self::page_id( $hit['page'] );
		if ( $session === null ) {
			$session = self::new_session( $visitor, $hit, $page_id, $now, $ua, $server, $settings['site_host'] );
		}

		self::add_view( $session, $hit['vk'], $page_id, $now );
	}

	/**
	 * Heartbeats and custom events. They attach to an existing view of a live session.
	 */
	private static function activity( array $hit, string $vh, int $now, array $settings ): void {
		global $wpdb;

		$session = self::session_for_hash( $vh, $now, $settings['session_timeout'] );
		if ( $session === null ) {
			return;
		}

		$view_id = self::view_id( $hit['vk'], $session['id'] );
		if ( $view_id === 0 ) {
			return;
		}

		if ( $hit['e'] === 'ping' ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . DB::table( 'views' ) . ' SET duration = GREATEST(duration, %d) WHERE id = %d',
				$hit['d'],
				$view_id
			) );
		} else {
			$target = DB::upsert( DB::table( 'event_targets' ), [ 'hash' => DB::key( $hit['v'] ), 'value' => $hit['v'] ] );

			DB::insert( DB::table( 'events' ), [
				'session_id' => $session['id'],
				'view_id'    => $view_id,
				'created_at' => $now,
				'type'       => $hit['t'],
				'target_id'  => $target,
			] );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . DB::table( 'sessions' ) . ' SET events = events + 1, is_bounce = 0 WHERE id = %d',
				$session['id']
			) );
		}

		self::touch( $session, $now );
	}

	/**
	 * Returns the visitor id. "new" is true only when this hit created the visitor row.
	 */
	private static function visitor( string $vh, int $now ): array {
		global $wpdb;

		$id = DB::upsert( DB::table( 'visitors' ), [
			'hash'       => $vh,
			'first_seen' => $now,
			'last_seen'  => $now,
			'sessions'   => 0,
		] );

		return [ 'id' => $id, 'new' => $wpdb->rows_affected === 1 ];
	}

	private static function session_for_visitor( int $visitor_id, int $now, int $timeout ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id, views, started_at FROM ' . DB::table( 'sessions' ) . ' WHERE visitor_id = %d AND last_seen >= %d ORDER BY last_seen DESC LIMIT 1',
			$visitor_id,
			$now - $timeout
		), 'ARRAY_A' );

		return $row ? array_map( 'intval', $row ) : null;
	}

	private static function session_for_hash( string $vh, int $now, int $timeout ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT s.id, s.views, s.started_at FROM ' . DB::table( 'visitors' ) . ' v
			 JOIN ' . DB::table( 'sessions' ) . ' s ON s.visitor_id = v.id
			 WHERE v.hash = UNHEX(%s) AND s.last_seen >= %d
			 ORDER BY s.last_seen DESC LIMIT 1',
			$vh,
			$now - $timeout
		), 'ARRAY_A' );

		return $row ? array_map( 'intval', $row ) : null;
	}

	private static function view_id( string $vk, int $session_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . DB::table( 'views' ) . ' WHERE view_key = UNHEX(%s) AND session_id = %d ORDER BY id DESC LIMIT 1',
			$vk,
			$session_id
		) );
	}

	private static function new_session( array $visitor, array $hit, int $page_id, int $now, string $ua, array $server, string $site_host ): array {
		global $wpdb;

		$ua_info = UA::classify( $ua );

		$id = DB::insert( DB::table( 'sessions' ), [
			'visitor_id'    => $visitor['id'],
			'started_at'    => $now,
			'last_seen'     => $now,
			'entry_page_id' => $page_id,
			'exit_page_id'  => $page_id,
			'referrer_id'   => self::referrer_id( $hit['ref'], $site_host ),
			'campaign_id'   => self::campaign_id( $hit['q'] ),
			'country_id'    => Geo::country_id( $server ),
			'device'        => $ua_info['device'],
			'browser_id'    => DB::upsert( DB::table( 'ua' ), [ 'kind' => 1, 'name' => $ua_info['browser'] ] ),
			'os_id'         => DB::upsert( DB::table( 'ua' ), [ 'kind' => 2, 'name' => $ua_info['os'] ] ),
			'is_new'        => $visitor['new'] ? 1 : 0,
		] );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . DB::table( 'visitors' ) . ' SET sessions = sessions + 1, last_seen = %d WHERE id = %d',
			$now,
			$visitor['id']
		) );

		return [ 'id' => $id, 'views' => 0, 'started_at' => $now ];
	}

	private static function add_view( array $session, string $vk, int $page_id, int $now ): void {
		global $wpdb;

		$views = $session['views'] + 1;

		DB::insert( DB::table( 'views' ), [
			'session_id' => $session['id'],
			'page_id'    => $page_id,
			'view_key'   => $vk,
			'created_at' => $now,
			'duration'   => 0,
			'seq'        => $views,
		] );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . DB::table( 'sessions' ) . ' SET views = %d, exit_page_id = %d, last_seen = %d, duration = %d,
			 is_bounce = IF(events > 0 OR %d > 1, 0, 1)
			 WHERE id = %d',
			$views,
			$page_id,
			$now,
			self::duration( $now, $session['started_at'] ),
			$views,
			$session['id']
		) );
	}

	private static function touch( array $session, int $now ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . DB::table( 'sessions' ) . ' SET last_seen = %d, duration = %d WHERE id = %d',
			$now,
			self::duration( $now, $session['started_at'] ),
			$session['id']
		) );
	}

	private static function page_id( array $page ): int {
		return DB::upsert(
			DB::table( 'pages' ),
			[
				'hash'      => DB::key( $page['key'] ),
				'type'      => $page['type'],
				'object_id' => $page['object_id'],
				'path'      => $page['path'],
				'title'     => $page['title'],
				'post_type' => $page['post_type'],
			],
			[ 'path' => $page['path'], 'title' => $page['title'] ] // Titles and permalinks can change.
		);
	}

	private static function referrer_id( string $url, string $site_host ): ?int {
		$host = Settings::host_of( $url );

		// NULL means direct: no referrer, or the visitor came from this site.
		if ( $host === '' || $host === $site_host ) {
			return null;
		}

		return DB::upsert( DB::table( 'referrers' ), [
			'hash'   => DB::key( $host ),
			'domain' => $host,
			'type'   => self::referrer_type( $host ),
		] );
	}

	/**
	 * 2 search, 3 social, 4 referral, 6 email. Direct is NULL, and ad is decided per campaign.
	 */
	private static function referrer_type( string $host ): int {
		return match ( true ) {
			(bool) preg_match( '/^(mail|webmail|outlook)\./', $host ) => 6,
			(bool) preg_match( '/(^|\.)(google|bing|duckduckgo|yahoo|baidu|yandex|ecosia|brave|qwant|startpage|ask)\.[a-z.]+$/', $host ) => 2,
			(bool) preg_match( '/(^|\.)(facebook|instagram|twitter|linkedin|reddit|pinterest|tiktok|youtube|threads|bsky)\.[a-z.]+$|(^|\.)(t\.co|x\.com|lnkd\.in|youtu\.be|fb\.com)$/', $host ) => 3,
			default => 4,
		};
	}

	/**
	 * Stores the five UTM values once each, then one campaign row that points at them.
	 */
	private static function campaign_id( string $query ): ?int {
		if ( $query === '' ) {
			return null;
		}

		$params = [];
		parse_str( ltrim( $query, '?' ), $params );

		$columns   = [
			'utm_source'   => 'source_id',
			'utm_medium'   => 'medium_id',
			'utm_campaign' => 'campaign_id',
			'utm_term'     => 'term_id',
			'utm_content'  => 'content_id',
		];
		$row       = [];
		$signature = [];

		foreach ( $columns as $param => $column ) {
			$value       = mb_strtolower( Validator::text( $params[ $param ] ?? null, 255 ) );
			$signature[] = $value;

			$row[ $column ] = $value === ''
				? null
				: DB::upsert( DB::table( 'utm_values' ), [ 'hash' => DB::key( $value ), 'value' => $value ] );
		}

		if ( implode( '', $signature ) === '' ) {
			return null;
		}

		return DB::upsert( DB::table( 'campaigns' ), [ 'hash' => DB::key( implode( '|', $signature ) ) ] + $row );
	}

	/**
	 * Limits hits per visitor when APCu is available. Without APCu, the session view cap still applies.
	 */
	private static function within_rate_limit( string $vh ): bool {
		if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled() ) {
			return true;
		}

		$key = 'snn_a_rl_' . $vh;
		apcu_add( $key, 0, 60 );
		$count = apcu_inc( $key );

		return $count === false || $count <= self::RATE_LIMIT;
	}

	/**
	 * CF-Connecting-IP is trusted only because Cloudflare sets it. Without Cloudflare, the
	 * client can forge it, so the forged value only changes that client's own visitor hash.
	 */
	private static function client_ip( array $server ): string {
		$cf = Validator::text( $server['HTTP_CF_CONNECTING_IP'] ?? null, 45 );
		if ( filter_var( $cf, FILTER_VALIDATE_IP ) !== false ) {
			return $cf;
		}

		return Validator::text( $server['REMOTE_ADDR'] ?? null, 45 );
	}

	private static function opted_out( array $server ): bool {
		return Validator::text( $server['HTTP_DNT'] ?? null, 1 ) === '1'
			|| Validator::text( $server['HTTP_SEC_GPC'] ?? null, 1 ) === '1';
	}

	private static function duration( int $now, int $started_at ): int {
		return min( max( 0, $now - $started_at ), 16777215 );
	}
}
