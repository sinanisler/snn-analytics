<?php
/**
 * Debug mode: a time-limited switch that logs every hit the tracker receives, with the reason when it is
 * dropped, and opens a secret endpoint to read that log, inspect a session and run read-only SQL.
 *
 * Off by default. Turning it on creates a new 256-bit secret and an expiry. The endpoint answers 404
 * unless debug mode is on and the secret matches, so it does not reveal that it exists.
 *
 * Loaded by t.php too (SHORTINIT), so log() uses only $wpdb and plain PHP.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Debug {

	const OPTION   = 'snn_a_debug';
	const DURATION = 3600; // seconds debug mode stays on
	const KEEP     = 2000; // log rows kept
	const ROWS     = 1000; // most rows one SQL answer returns

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function on(): bool {
		return (int) Settings::get()['debug_until'] > time();
	}

	/** Turns debug mode on with a fresh secret, or off. Called from the settings screen. */
	public static function set( bool $enable ): void {
		if ( $enable && ! self::on() ) {
			Store::write( self::OPTION, [ 'secret' => bin2hex( random_bytes( 32 ) ) ], false );
			Settings::update( [ 'debug_until' => time() + self::DURATION ] );
		} elseif ( ! $enable ) {
			Settings::update( [ 'debug_until' => 0 ] );
		}
	}

	/** The endpoint URL while debug mode is on, else an empty string. */
	public static function url(): string {
		$secret = (string) ( Store::read( self::OPTION )['secret'] ?? '' );

		return self::on() && $secret !== '' ? rest_url( 'snn-a/v1/debug/' . $secret . '/' ) : '';
	}

	/**
	 * Records one hit and what became of it. $reason is "ok" or why it was dropped.
	 * Never throws: a logging problem must not change how a hit is handled.
	 */
	public static function log( string $reason, string $raw, array $server, ?array $hit, string $detail = '' ): void {
		if ( ! self::on() ) {
			return;
		}

		global $wpdb;
		$wpdb->suppress_errors( true );

		$script = basename( (string) strtok( (string) ( $server['SCRIPT_NAME'] ?? '' ), '?' ) );
		$route  = $script === 't.php' ? 't.php' : ( $script === 'admin-ajax.php' ? 'ajax' : 'rest' );
		$table  = DB::table( 'debug_log' );

		DB::insert( $table, [
			'at'     => time(),
			'reason' => substr( $reason, 0, 40 ),
			'route'  => $route,
			'ev'     => substr( (string) ( $hit['e'] ?? '' ), 0, 8 ),
			'bytes'  => strlen( $raw ),
			'vk'     => (string) ( $hit['vk'] ?? '' ),
			'ua'     => Validator::text( $server['HTTP_USER_AGENT'] ?? null, 120 ),
			'detail' => substr( $detail, 0, 255 ),
			'body'   => substr( $raw, 0, 4096 ),
		] );

		if ( mt_rand( 1, 25 ) === 1 ) {
			$newest = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id < %d", $newest - self::KEEP ) );
		}
		$wpdb->suppress_errors( false );
	}

	public static function routes(): void {
		register_rest_route( API::NS, '/debug/(?P<secret>[0-9a-f]{64})/(?P<action>log|session|sql|info)', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ self::class, 'handle' ],
			// The secret is the credential. It is checked here so a wrong one gets the same 404 as no route.
			'permission_callback' => [ self::class, 'allowed' ],
		] );
	}

	public static function allowed( \WP_REST_Request $request ) {
		$secret = (string) ( Store::read( self::OPTION )['secret'] ?? '' );

		if ( self::on() && $secret !== '' && hash_equals( $secret, (string) $request['secret'] ) ) {
			return true;
		}

		return new \WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', [ 'status' => 404 ] );
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$data = match ( $request['action'] ) {
			'log'     => self::read_log( $request ),
			'session' => self::session( $request ),
			'sql'     => self::sql( (string) $request->get_param( 'q' ) ),
			default   => self::info(),
		};

		$response = new \WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/** Newest first. Filters: reason (exact, or "drops" for everything but ok), vk, limit. */
	private static function read_log( \WP_REST_Request $request ): array {
		global $wpdb;

		$table = DB::table( 'debug_log' );
		if ( $request->get_param( 'clear' ) ) {
			$wpdb->query( "DELETE FROM {$table}" );
		}

		$where = [];
		$args  = [];
		$reason = (string) $request->get_param( 'reason' );
		if ( $reason === 'drops' ) {
			$where[] = "reason <> 'ok'";
		} elseif ( $reason !== '' ) {
			$where[] = 'reason = %s';
			$args[]  = $reason;
		}
		$vk = (string) $request->get_param( 'vk' );
		if ( preg_match( '/^[0-9a-f]{16}$/', $vk ) ) {
			$where[] = 'vk = %s';
			$args[]  = $vk;
		}
		$limit = min( 500, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );

		$sql = "SELECT * FROM {$table}" . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . ' ORDER BY id DESC LIMIT ' . $limit;
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, ...$args ) : $sql, ARRAY_A );

		$counts = $wpdb->get_results( "SELECT reason, COUNT(*) AS n FROM {$table} GROUP BY reason ORDER BY n DESC", ARRAY_A );

		return [ 'counts' => $counts, 'rows' => self::clean( (array) $rows ) ];
	}

	/** One session with its views, events and trails. Look up by id, by view key (vk), or by visitor id. */
	private static function session( \WP_REST_Request $request ): array {
		global $wpdb;

		$t  = static fn( string $n ): string => DB::table( $n );
		$id = (int) $request->get_param( 'id' );
		$vk = (string) $request->get_param( 'vk' );

		if ( $id === 0 && preg_match( '/^[0-9a-f]{16}$/', $vk ) ) {
			$id = (int) $wpdb->get_var( 'SELECT session_id FROM ' . $t( 'views' ) . ' WHERE view_key = ' . DB::hex( $vk ) . ' ORDER BY id DESC LIMIT 1' );
		}
		if ( $id === 0 && $request->get_param( 'visitor' ) ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $t( 'sessions' ) . ' WHERE visitor_id = %d ORDER BY id DESC LIMIT 1', (int) $request->get_param( 'visitor' ) ) );
		}
		if ( $id === 0 ) {
			$id = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . $t( 'sessions' ) );
		}

		$session = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $t( 'sessions' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $session ) {
			return [ 'error' => 'no such session' ];
		}

		$views = $wpdb->get_results( $wpdb->prepare(
			'SELECT v.id, v.seq, LOWER(HEX(v.view_key)) AS view_key, v.created_at, v.duration, p.path
			 FROM ' . $t( 'views' ) . ' v LEFT JOIN ' . $t( 'pages' ) . ' p ON p.id = v.page_id
			 WHERE v.session_id = %d ORDER BY v.id', $id ), ARRAY_A );
		$events = $wpdb->get_results( $wpdb->prepare(
			'SELECT e.id, e.view_id, e.created_at, e.type, et.value
			 FROM ' . $t( 'events' ) . ' e LEFT JOIN ' . $t( 'event_targets' ) . ' et ON et.id = e.target_id
			 WHERE e.session_id = %d ORDER BY e.id', $id ), ARRAY_A );
		$trails = $wpdb->get_results( $wpdb->prepare(
			'SELECT tr.view_id, tr.created_at, LENGTH(tr.samples) AS bytes
			 FROM ' . $t( 'trails' ) . ' tr JOIN ' . $t( 'views' ) . ' v ON v.id = tr.view_id
			 WHERE v.session_id = %d ORDER BY tr.view_id', $id ), ARRAY_A );

		// Per view: which kinds of data arrived. A view with no ping or trail often left before they were sent.
		$with_trail = array_column( (array) $trails, 'view_id' );
		$by_view    = [];
		foreach ( (array) $events as $event ) {
			$by_view[ $event['view_id'] ][] = (int) $event['type'];
		}
		foreach ( $views as &$view ) {
			$view['event_types'] = $by_view[ $view['id'] ] ?? [];
			$view['has_trail']   = in_array( $view['id'], $with_trail, false );
			$view['has_ping']    = (int) $view['duration'] > 0;
		}

		return self::clean( [ 'session' => $session, 'views' => $views, 'events' => $events, 'trails' => $trails ] );
	}

	/**
	 * Runs one read-only statement on any table. On MySQL it runs inside a READ ONLY transaction, so the
	 * server itself refuses a write. The SQLite driver has no such mode, so the text is checked as well.
	 */
	private static function sql( string $query ): array {
		global $wpdb;

		// Comments are removed first, so a keyword cannot hide in one.
		$query = trim( (string) preg_replace( [ '#/\*.*?\*/#s', '/(^|\s)--[^\n]*/', '/(^|\s)#[^\n]*/' ], ' ', $query ) );
		$query = rtrim( $query, "; \t\n\r" );

		if ( $query === '' ) {
			return [ 'error' => 'pass the statement as q' ];
		}
		if ( strpos( $query, ';' ) !== false ) {
			return [ 'error' => 'one statement only' ];
		}
		if ( ! preg_match( '/^(select|with|show|describe|desc|explain|pragma\s+table_info)\b/i', $query )
			|| preg_match( '/\b(insert|update|delete|drop|alter|create|truncate|grant|into\s+(out|dump)file|load_file|sleep|benchmark)\b/i', $query ) ) {
			return [ 'error' => 'read-only statements only' ];
		}
		if ( preg_match( '/^select\b/i', $query ) && ! preg_match( '/\blimit\s+\d/i', $query ) ) {
			$query .= ' LIMIT ' . self::ROWS;
		}

		$mysql = ! ( $wpdb instanceof \WP_SQLite_DB );
		$wpdb->suppress_errors( true );
		if ( $mysql ) {
			$wpdb->query( 'START TRANSACTION READ ONLY' );
		}
		$rows  = $wpdb->get_results( $query, ARRAY_A );
		$error = $wpdb->last_error;
		if ( $mysql ) {
			$wpdb->query( 'ROLLBACK' );
		}
		$wpdb->suppress_errors( false );

		if ( $error !== '' ) {
			return [ 'error' => $error ];
		}

		$rows = array_slice( (array) $rows, 0, self::ROWS );

		return [ 'count' => count( $rows ), 'rows' => self::clean( $rows ) ];
	}

	private static function info(): array {
		global $wpdb;

		$tables = [];
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'snn_a_' ) . '%' ) ) as $name ) {
			$tables[ $name ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}`" );
		}

		$settings = Settings::get();
		unset( $settings['goal_rules'] );

		return [
			'plugin'   => SNN_A_VERSION,
			'db'       => [ 'engine' => $wpdb instanceof \WP_SQLite_DB ? 'sqlite' : 'mysql', 'version' => $wpdb->db_version(), 'schema' => (int) get_option( DB::OPTION ) ],
			'php'      => PHP_VERSION,
			'apcu'     => function_exists( 'apcu_fetch' ) && ini_get( 'apc.enabled' ),
			'wp'       => get_bloginfo( 'version' ),
			'time'     => [ 'now' => time(), 'tz_offset' => Ranges::offset() ],
			'debug'    => [ 'until' => (int) $settings['debug_until'] ],
			'settings' => $settings,
			'tables'   => $tables,
			'limits'   => [ 'max_body' => Tracker::MAX_BODY, 'rate_activity' => Tracker::RATE_LIMIT, 'rate_views' => Tracker::RATE_LIMIT_VIEWS, 'max_views' => Tracker::MAX_VIEWS ],
		];
	}

	/** Makes rows safe for JSON: binary values (view keys, hashes, trail bytes) become "hex:...". */
	private static function clean( array $data ): array {
		array_walk_recursive( $data, static function ( &$value ): void {
			if ( is_string( $value ) && ! mb_check_encoding( $value, 'UTF-8' ) ) {
				$value = 'hex:' . bin2hex( $value );
			}
		} );

		return $data;
	}
}
