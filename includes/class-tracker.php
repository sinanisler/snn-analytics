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
	const RATE_LIMIT       = 120; // activity hits per page view per minute (APCu only)
	const RATE_LIMIT_VIEWS = 600; // page views per visitor hash per minute (APCu only)
	const MAX_VIEWS  = 2000;  // views per session before it stops counting
	const TRAIL_MAX_HEX    = 86400; // 30 chunks of 240 samples, 12 hex characters each
	const PARK_MAX         = 300;   // activity hits kept per page view while its page view has not arrived
	const PARK_FOR         = 3600;  // seconds a parked hit waits for its page view

	/** Payment and sign-in hosts that send a visitor back to the site in the middle of a visit. */
	const ROUND_TRIP = '/(^|\.)(paypal|stripe|iyzipay|iyzico|klarna|mollie|adyen|payu|paytr|braintreegateway|squareup|authorize|2checkout|checkout|razorpay|mercadopago|sumup|wise)\.[a-z.]+$|^(checkout|pay|payment|payments|secure|3ds|acs|login|auth|accounts|signin|sso)\.|(^|\.)(accounts\.google|appleid\.apple|login\.microsoftonline|login\.live)\.com$/';

	/**
	 * Entry point. Always produces the same response, so it never reveals what was stored.
	 */
	public static function process( string $raw, array $server ): void {
		if ( $raw === '' || strlen( $raw ) > self::MAX_BODY ) {
			Debug::log( $raw === '' ? 'empty-body' : 'body-too-big', $raw, $server, null );
			return;
		}

		$in  = json_decode( $raw, true, 4 );
		$hit = is_array( $in ) ? Validator::hit( $in ) : null;
		if ( $hit === null ) {
			Debug::log( 'invalid', $raw, $server, is_array( $in ) ? [ 'e' => $in['e'] ?? '', 'vk' => is_string( $in['vk'] ?? null ) ? $in['vk'] : '' ] : null );
			return;
		}

		$settings = Settings::get();
		$ua       = Validator::text( $server['HTTP_USER_AGENT'] ?? null, 512 );

		if ( Bot::is_bot( $ua, $server ) ) {
			Debug::log( 'bot', $raw, $server, $hit );
			return;
		}
		if ( $settings['respect_dnt'] && self::opted_out( $server ) ) {
			Debug::log( 'dnt', $raw, $server, $hit );
			return;
		}

		// The visitor hash is the only identifier: the salt, the IP, the browser family, the OS,
		// the device type and the site. No version numbers, so a browser update does not split a visitor.
		// The IP exists only in memory, never in a row.
		$info = UA::classify( $ua );
		$vh   = DB::key( Settings::salt() . '|' . self::hash_ip( self::client_ip( $server ) ) . '|' . $info['browser'] . '|' . $info['os'] . '|' . $info['device'] . '|' . $settings['site_host'] );
		// Page views are limited per visitor hash, which many people can share (office, school or mobile carrier
		// behind one IP), so that limit is high. Activity is limited per page view, whose key is random.
		$limited = $hit['e'] === 'pv'
			? ! self::within_rate_limit( $vh, self::RATE_LIMIT_VIEWS )
			: ! self::within_rate_limit( $hit['vk'], self::RATE_LIMIT );
		if ( $limited ) {
			Debug::log( 'rate-limit', $raw, $server, $hit );
			return;
		}

		$now    = time();
		$result = $hit['e'] === 'pv'
			? self::pageview( $hit, $vh, $now, $info, $server, $settings )
			: self::activity( $hit, $now, $settings );
		Debug::log( $result[0], $raw, $server, $hit, $result[1] ?? '' );
	}

	/**
	 * Page views of one visitor run one at a time. Tabs opened together (or a page with several
	 * requests in flight) would otherwise each see "no session" and create one each.
	 */
	private static function pageview( array $hit, string $vh, int $now, array $info, array $server, array $settings ): array {
		$lock = 'snn_a_' . $vh;
		$held = self::lock( $lock );

		try {
			// The browser retries on the next route when an answer is not 204, even if this route stored the view.
			// The view key is random per page load, so a key that exists is a repeat and counts once.
			if ( self::view_by_key( $hit['vk'] ) !== null ) {
				return [ 'dup-view' ];
			}

			$visitor = self::visitor( $vh, $now );
			$session = self::session_for_visitor( $visitor['id'], $now, $settings['session_timeout'] );

			if ( $session !== null && $session['views'] >= self::MAX_VIEWS ) {
				return [ 'session-cap', 'session ' . $session['id'] ];
			}

			// A visitor who comes back from another site or campaign while a session is open starts a new
			// session, or that source would be lost. No referrer (direct, or a link on this site) continues it,
			// and so does a reload of the landing page, which repeats the session's own source.
			// A return from a payment page or a sign-in provider is the same visit, so it never splits one.
			$referrer_id = self::referrer_id( $hit['ref'], $settings['site_host'] );
			$campaign_id = self::campaign_id( $hit['q'] );
			$round_trip  = (bool) preg_match( self::ROUND_TRIP, Settings::host_of( $hit['ref'] ) );
			if ( $session !== null
				&& ( ( $referrer_id !== null && $referrer_id !== $session['referrer_id'] && ! $round_trip )
					|| ( $campaign_id !== null && $campaign_id !== $session['campaign_id'] ) ) ) {
				$session = null;
			}

			$page_id = self::page_id( $hit['page'] );
			$fresh = $session === null;
			if ( $fresh ) {
				$session = self::new_session( $visitor, $referrer_id, $campaign_id, $page_id, $now, $info, $server, $hit );
			}

			self::add_view( $session, $hit['vk'], $page_id, $now );
			Goals::check_page( $session['id'], $hit['page']['path'], $now );
			$replayed = self::replay( $hit['vk'], $now, $settings );

			return [ 'ok', ( $fresh ? 'new session ' : 'session ' ) . $session['id'] . ', visitor ' . $visitor['id'] . ', ' . $hit['page']['path'] . ( $replayed ? ', ' . $replayed . ' parked hits applied' : '' ) ];
		} finally {
			if ( $held ) {
				self::unlock( $lock );
			}
		}
	}

	/** Named lock on the database connection. Waits up to 2 seconds. A failed lock lets the hit through anyway. */
	private static function lock( string $name ): bool {
		global $wpdb;

		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $name ) ) === '1';
	}

	private static function unlock( string $name ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	/**
	 * Heartbeats, custom events, clicks, heatmap positions and cursor trails. They attach to the
	 * view they were sent from, whatever the session's state is now.
	 */
	private static function activity( array $hit, int $now, array $settings ): array {
		global $wpdb;

		// The view key is random per page load, so it finds the view on its own. The visitor hash is not
		// used here: the IP can change during a visit, and a page can stay open past the session timeout.
		// Activity can arrive before its page view (both in flight when the visitor leaves, a slow or busy
		// server), so it is parked and the page view replays it.
		$found = self::view_by_key( $hit['vk'] );
		if ( $found === null ) {
			return self::park( $hit, $now, $settings );
		}

		return self::apply( $hit, $found, $now, $settings );
	}

	/**
	 * Keeps activity whose page view is not stored yet. If the view was stored meanwhile (another worker
	 * on MySQL), the row is claimed back and applied here, so it is never left behind.
	 */
	private static function park( array $hit, int $now, array $settings ): array {
		global $wpdb;

		$table = DB::table( 'pending' );
		$held  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table . ' WHERE view_key = ' . DB::hex( $hit['vk'] ) );
		if ( $held >= self::PARK_MAX ) {
			return [ 'no-view', 'parking full' ];
		}

		$id = DB::insert( $table, [ 'view_key' => $hit['vk'], 'at' => $now, 'body' => (string) json_encode( $hit ) ] );
		if ( mt_rand( 1, 200 ) === 1 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE at < %d LIMIT 500", $now - self::PARK_FOR ) );
		}

		$found = self::view_by_key( $hit['vk'] );
		if ( $found !== null && self::claim( $id ) ) {
			return self::apply( $hit, $found, $now, $settings );
		}

		return [ 'parked', 'row ' . $id ];
	}

	/** Deletes one parked row. True only for the request whose delete removed it, so it is applied once. */
	private static function claim( int $id ): bool {
		global $wpdb;

		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . DB::table( 'pending' ) . ' WHERE id = %d', $id ) ) === 1;
	}

	/** Applies the activity that waited for a page view that is now stored. Returns how many were applied. */
	private static function replay( string $vk, int $now, array $settings ): int {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT id, body FROM ' . DB::table( 'pending' ) . ' WHERE view_key = ' . DB::hex( $vk ) . ' ORDER BY id',
			ARRAY_A
		);
		$done = 0;
		foreach ( (array) $rows as $row ) {
			$hit = json_decode( (string) $row['body'], true );
			if ( ! is_array( $hit ) || ! self::claim( (int) $row['id'] ) ) {
				continue;
			}
			$found = self::view_by_key( $vk );
			if ( $found !== null ) {
				self::apply( $hit, $found, $now, $settings );
				$done++;
			}
		}

		return $done;
	}

	private static function apply( array $hit, array $found, int $now, array $settings ): array {
		global $wpdb;

		[ $view, $session ] = $found;
		$note = 'view ' . $view['id'] . ', session ' . $session['id'];

		if ( $hit['e'] === 'ping' ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . DB::table( 'views' ) . ' SET duration = GREATEST(duration, %d) WHERE id = %d',
				$hit['d'],
				$view['id']
			) );
		} elseif ( $hit['e'] === 'heat' ) {
			if ( ! $settings['heatmap'] ) {
				return [ 'setting-off', 'heatmap is off' ];
			}
			self::heat( $hit, $view, $session, $now, $settings );
		} elseif ( $hit['e'] === 'trail' ) {
			if ( ! $settings['cursor_trails'] ) {
				return [ 'setting-off', 'cursor trails are off' ];
			}
			self::trail( $hit, $view, $session, $now );
			$note .= ', ' . intdiv( strlen( $hit['s'] ), 12 ) . ' samples';
		} elseif ( $hit['e'] === 'ev' ) {
			$target = DB::upsert( DB::table( 'event_targets' ), [ 'hash' => DB::key( $hit['v'] ), 'value' => $hit['v'] ] );

			DB::insert( DB::table( 'events' ), [
				'session_id' => $session['id'],
				'view_id'    => $view['id'],
				'created_at' => $now,
				'type'       => $hit['t'],
				'target_id'  => $target,
			] );
			// Deliberate actions count as engagement: links, downloads, goals, custom events, button clicks
			// and form submits (types 1 to 9). Text selection and scroll depth (10 and 11) are automatic,
			// so they are stored but do not change bounce rate.
			if ( $hit['t'] <= 9 ) {
				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . DB::table( 'sessions' ) . ' SET events = events + 1, is_bounce = 0 WHERE id = %d',
					$session['id']
				) );
			}
			Goals::check_event( $session['id'], $hit['t'], $hit['v'], $now );
			$note .= ', type ' . $hit['t'];
		}

		self::touch( $session, $now );

		return [ 'ok', $note ];
	}

	/**
	 * One click position. Stored as a counter per page, day, device and 2%-by-40px cell, so the
	 * table grows with the number of cells, not with the number of clicks.
	 */
	private static function heat( array $hit, array $view, array $session, int $now, array $settings ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . DB::table( 'heat' ) . ' (day, page_id, device, x, y, clicks) VALUES (%d, %d, %d, %d, %d, 1)
			 ON DUPLICATE KEY UPDATE clicks = clicks + 1',
			self::day( $now, $settings ),
			$view['page_id'],
			$session['device'],
			$hit['x'],
			$hit['y']
		) );
	}

	/**
	 * One mouse path per view, sent in chunks. The page and device are stored with it, so a trail can be
	 * read on its own. A chunk after the first starts with a marker sample (x 0x7fff, then the chunk
	 * number) and is appended. A chunk number that is already stored is a repeat and is ignored, and
	 * INSERT IGNORE does the same for a repeated first chunk.
	 */
	private static function trail( array $hit, array $view, array $session, int $now ): void {
		global $wpdb;

		$part = self::trail_part( $hit['s'] );
		$old  = $wpdb->get_var( $wpdb->prepare(
			'SELECT HEX(samples) FROM ' . DB::table( 'trails' ) . ' WHERE view_id = %d',
			$view['id']
		) );

		// Chunks can arrive out of order (a parked chunk, a slow request), so they are kept sorted by number.
		// A chunk number that is already stored is a repeat.
		if ( $old !== null ) {
			$old    = strtolower( (string) $old );
			$chunks = self::trail_chunks( $old );
			if ( isset( $chunks[ $part ] ) || strlen( $old ) + strlen( $hit['s'] ) > self::TRAIL_MAX_HEX ) {
				return;
			}
			$chunks[ $part ] = $hit['s'];
			ksort( $chunks );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . DB::table( 'trails' ) . ' SET samples = ' . DB::hex( implode( '', $chunks ) ) . ' WHERE view_id = %d',
				$view['id']
			) );
			return;
		}

		$wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . DB::table( 'trails' ) . ' (view_id, page_id, device, created_at, samples) VALUES (%d, %d, %d, %d, ' . DB::hex( $hit['s'] ) . ')',
			$view['id'],
			$view['page_id'],
			$session['device'],
			$now
		) );
	}

	/** A stored trail split into its chunks, keyed by chunk number. Each chunk keeps its marker sample. */
	private static function trail_chunks( string $hex ): array {
		$chunks = [];
		$part   = 0;
		foreach ( str_split( $hex, 12 ) as $sample ) {
			if ( strncmp( $sample, '7fff', 4 ) === 0 ) {
				$part = self::trail_part( $sample );
			}
			$chunks[ $part ] = ( $chunks[ $part ] ?? '' ) . $sample;
		}

		return $chunks;
	}

	/** The chunk number in a marker sample, or 0 for a plain sample (the first chunk has no marker). */
	private static function trail_part( string $sample ): int {
		return strncmp( $sample, '7fff', 4 ) === 0 ? (int) hexdec( substr( $sample, 4, 4 ) ) : 0;
	}

	/**
	 * Day number in site time, from the stored offset. Same numbering as Ranges::day_of().
	 */
	private static function day( int $now, array $settings ): int {
		return (int) floor( ( $now + (int) $settings['tz_offset'] ) / 86400 );
	}

	/**
	 * Returns the visitor id. "new" is true only when this hit created the visitor row.
	 */
	private static function visitor( string $vh, int $now ): array {
		$id = DB::upsert( DB::table( 'visitors' ), [
			'hash'       => $vh,
			'first_seen' => $now,
			'last_seen'  => $now,
			'sessions'   => 0,
		], [ 'last_seen' => $now ] );

		return [ 'id' => $id, 'new' => DB::$inserted ];
	}

	private static function session_for_visitor( int $visitor_id, int $now, int $timeout ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id, views, started_at, referrer_id, campaign_id FROM ' . DB::table( 'sessions' ) . ' WHERE visitor_id = %d AND last_seen >= %d ORDER BY last_seen DESC LIMIT 1',
			$visitor_id,
			$now - $timeout
		), 'ARRAY_A' );

		if ( ! $row ) {
			return null;
		}

		return [
			'id'          => (int) $row['id'],
			'views'       => (int) $row['views'],
			'started_at'  => (int) $row['started_at'],
			'referrer_id' => $row['referrer_id'] === null ? null : (int) $row['referrer_id'],
			'campaign_id' => $row['campaign_id'] === null ? null : (int) $row['campaign_id'],
		];
	}

	/**
	 * The view that a heartbeat, event, heatmap or trail belongs to, with its session. Null when the key is unknown.
	 */
	private static function view_by_key( string $vk ): ?array {
		global $wpdb;

		// The key was checked as 16 hex characters by the validator, so the literal is safe.
		$row = $wpdb->get_row(
			'SELECT v.id AS view_id, v.page_id, s.id AS session_id, s.started_at, s.device
			 FROM ' . DB::table( 'views' ) . ' v
			 JOIN ' . DB::table( 'sessions' ) . ' s ON s.id = v.session_id
			 WHERE v.view_key = ' . DB::hex( $vk ) . '
			 ORDER BY v.id DESC LIMIT 1',
			'ARRAY_A'
		);

		if ( ! $row ) {
			return null;
		}

		return [
			[ 'id' => (int) $row['view_id'], 'page_id' => (int) $row['page_id'] ],
			[ 'id' => (int) $row['session_id'], 'started_at' => (int) $row['started_at'], 'device' => (int) $row['device'] ],
		];
	}

	/**
	 * The part of the IP that goes into the visitor hash. IPv6 keeps the first 64 bits only: browsers rotate
	 * the lower half (privacy addresses), and that would split one visitor into several.
	 */
	private static function hash_ip( string $ip ): string {
		if ( strpos( $ip, ':' ) === false ) {
			return $ip;
		}

		$packed = inet_pton( $ip );
		if ( $packed === false || strlen( $packed ) !== 16 ) {
			return $ip;
		}

		return (string) inet_ntop( substr( $packed, 0, 8 ) . str_repeat( "\0", 8 ) );
	}

	private static function new_session( array $visitor, ?int $referrer_id, ?int $campaign_id, int $page_id, int $now, array $info, array $server, array $hit ): array {
		global $wpdb;

		$geo = Geo::locate( $server, self::client_ip( $server ) );

		$id = DB::insert( DB::table( 'sessions' ), [
			'visitor_id'    => $visitor['id'],
			'started_at'    => $now,
			'last_seen'     => $now,
			'entry_page_id' => $page_id,
			'exit_page_id'  => $page_id,
			'referrer_id'   => $referrer_id,
			'campaign_id'   => $campaign_id,
			'country_id'    => $geo['country_id'],
			'city_id'       => $geo['city_id'],
			'device'        => $info['device'],
			'browser_id'    => DB::upsert( DB::table( 'ua' ), [ 'kind' => 1, 'name' => $info['browser'] ] ),
			'os_id'         => DB::upsert( DB::table( 'ua' ), [ 'kind' => 2, 'name' => $info['os'] ] ),
			'browser_ver'   => $info['version'],
			'screen_w'      => $hit['sw'],
			'screen_h'      => $hit['sh'],
			'view_w'        => $hit['vw'],
			'view_h'        => $hit['vh'],
			'lang'          => $hit['lang'],
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
			 is_bounce = CASE WHEN events > 0 OR %d > 1 THEN 0 ELSE 1 END
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
	private static function within_rate_limit( string $id, int $max ): bool {
		if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled() ) {
			return true;
		}

		$key = 'snn_a_rl_' . $id;
		apcu_add( $key, 0, 60 );
		$count = apcu_inc( $key );

		return $count === false || $count <= $max;
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
