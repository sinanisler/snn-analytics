<?php
/**
 * Imports Burst Statistics data into SNN Analytics.
 *
 * Reads only the Burst tables and never changes or deletes them. The import runs in stages.
 * Each call to step() handles one chunk of 5,000 rows, and progress is stored in an option,
 * so an interrupted import resumes where it stopped and a request never runs long.
 *
 * Burst keeps no session end time, so session duration, last view and entry/exit pages are
 * worked out from the imported views in the totals stage. Burst visitor ids cannot be matched
 * to this plugin's visitor hashes (the salts differ), so each Burst visitor becomes its own visitor.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Burst_Importer {

	const OPTION    = 'snn_a_import_burst';
	const CHUNK     = 5000;
	const BATCH     = 500;
	const DAY_BATCH = 30;

	/** Burst's city_code can be negative (country-level rows), so the first key is the lowest int. */
	const FIRST_KEY = -2147483648;

	/** Stage => [ old table, primary key ]. Dimensions come first, because facts refer to them. */
	const STAGES = [
		'visitors'  => [ 'burst_uids', 'ID' ],
		'locations' => [ 'burst_locations', 'city_code' ],
		'browsers'  => [ 'burst_browsers', 'ID' ],
		'platforms' => [ 'burst_platforms', 'ID' ],
		'devices'   => [ 'burst_devices', 'ID' ],
		'sessions'  => [ 'burst_sessions', 'ID' ],
		'views'     => [ 'burst_statistics', 'ID' ],
	];

	/** Map tables: old id => new id. Views need none, because nothing refers to them. */
	const MAPS = [ 'visitors', 'cities', 'browsers', 'platforms', 'devices', 'sessions' ];

	/** Burst's source_category => referrer type. Anything else is a plain referrer. */
	const REFERRER_TYPES = [ 'search' => 2, 'social' => 3, 'referral' => 4, 'paid' => 5, 'ad' => 5 ];

	/** Per-request cache of page ids and referrer ids, so repeated values cost one lookup. */
	private static array $page_cache     = [];
	private static array $referrer_cache = [];

	// ----------------------------------------------------------- public API

	/** Whether the Burst tables exist, and roughly how much data they hold. */
	public static function detect(): array {
		global $wpdb;

		$found = [];
		foreach ( [ 'burst_sessions', 'burst_statistics' ] as $table ) {
			$rows = $wpdb->get_var( $wpdb->prepare(
				'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				self::old( $table )
			) );
			$found[ $table ] = $rows === null ? null : (int) $rows;
		}

		$missing = self::missing_columns();

		return [
			'available' => $found['burst_sessions'] !== null && $found['burst_statistics'] !== null && $missing === [],
			'sessions'  => $found['burst_sessions'] ?? 0,
			'views'     => $found['burst_statistics'] ?? 0,
			'missing'   => $missing,
		];
	}

	/** Columns the importer reads, per Burst table. Anything absent means a different Burst version. */
	const REQUIRED_COLUMNS = [
		'burst_statistics' => [ 'ID', 'page_url', 'page_id', 'page_type', 'time', 'time_on_page', 'session_id' ],
		'burst_sessions'   => [ 'ID', 'start_time', 'uid_id', 'referrer', 'city_code', 'browser_id', 'platform_id', 'device_id', 'first_time_visit' ],
		'burst_uids'       => [ 'ID', 'uid' ],
		'burst_locations'  => [ 'city_code', 'city', 'state', 'country_code' ],
		'burst_browsers'   => [ 'ID', 'name' ],
		'burst_platforms'  => [ 'ID', 'name' ],
		'burst_devices'    => [ 'ID', 'name' ],
	];

	/** "table.column" entries that are missing from the Burst tables on this site. */
	public static function missing_columns(): array {
		global $wpdb;

		$missing = [];
		foreach ( self::REQUIRED_COLUMNS as $table => $columns ) {
			$present = $wpdb->get_col( $wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				self::old( $table )
			) );

			if ( $present === [] ) {
				$missing[] = $table . ' (table)';
				continue;
			}
			foreach ( array_diff( $columns, $present ) as $column ) {
				$missing[] = $table . '.' . $column;
			}
		}

		return $missing;
	}

	public static function status(): array {
		$state = get_option( self::OPTION, [] );

		return is_array( $state ) && $state ? $state : [ 'state' => 'idle', 'stage' => '', 'message' => '' ];
	}

	/**
	 * Starts an import, or resumes one that stopped with an error. A finished import is
	 * not started again, because that would duplicate its data.
	 */
	public static function start(): array {
		$current = self::status();
		$state   = $current['state'] ?? '';

		if ( $state === 'running' || $state === 'done' ) {
			return $current;
		}

		// Refuse before touching anything when the Burst tables do not match what the importer reads.
		$detect = self::detect();
		if ( ! $detect['available'] ) {
			return self::save( [
				'state'   => 'error',
				'stage'   => '',
				'message' => $detect['sessions'] === 0 && $detect['missing'] === []
					? 'No Burst Statistics data was found.'
					: 'This Burst Statistics version has a different layout. Missing: ' . implode( ', ', $detect['missing'] ) . '.',
			] );
		}

		self::create_maps();

		if ( $state === 'error' && ! empty( $current['stage'] ) ) {
			return self::save( array_merge( $current, [ 'state' => 'running', 'message' => 'Resuming the import.' ] ) );
		}

		return self::save( [
			'state'   => 'running',
			'stage'   => 'visitors',
			'last'    => self::FIRST_KEY,
			'rows'    => [],
			'message' => 'Import started.',
			'started' => time(),
		] );
	}

	public static function cancel(): array {
		self::drop_maps();

		return self::save( [ 'state' => 'idle', 'stage' => '', 'message' => 'Import cancelled. Imported data stays.' ] );
	}

	/**
	 * Handles one chunk. Returns the new status.
	 */
	public static function step(): array {
		@set_time_limit( 300 );

		$state = self::status();
		if ( ( $state['state'] ?? '' ) !== 'running' ) {
			return $state;
		}

		global $wpdb;

		try {
			$stage = (string) $state['stage'];

			if ( isset( self::STAGES[ $stage ] ) ) {
				[ $table, $pk ] = self::STAGES[ $stage ];
				$last           = (int) $state['last'];

				// One transaction per chunk, so a failed chunk leaves nothing half-written.
				$wpdb->query( 'START TRANSACTION' );
				$rows  = self::chunk( $table, $pk, $last );
				$count = count( $rows );

				if ( $count > 0 ) {
					$handler = 'import_' . $stage;
					self::$handler( $rows );
					$state['last'] = (int) end( $rows )[ $pk ];
					$state['rows'][ $stage ] = ( $state['rows'][ $stage ] ?? 0 ) + $count;
				}
				$wpdb->query( 'COMMIT' );

				if ( $count < self::CHUNK ) {
					$state['stage'] = self::next_stage( $stage );
					$state['last']  = self::FIRST_KEY;
				}

				$state['message'] = 'Importing ' . $stage . ' (' . number_format_i18n( $state['rows'][ $stage ] ?? 0 ) . ' rows so far).';

				return self::save( $state );
			}

			if ( $stage === 'totals' ) {
				self::totals();
				$state['stage']   = 'rollup';
				$state['day']     = self::first_imported_day();
				$state['message'] = 'Rebuilding daily totals.';

				return self::save( $state );
			}

			if ( $stage === 'rollup' ) {
				$to = Ranges::day_of( time() );
				if ( $state['day'] === null || $state['day'] > $to ) {
					self::drop_maps();

					return self::save( [
						'state'    => 'done',
						'stage'    => '',
						'message'  => 'Import finished. Rows: ' . self::summary( $state['rows'] ?? [] ) . '.',
						'finished' => time(),
						'rows'     => $state['rows'] ?? [],
					] );
				}

				$end = min( $to, (int) $state['day'] + self::DAY_BATCH - 1 );
				Rollup::refresh_range( (int) $state['day'], $end );
				$state['day']     = $end + 1;
				$state['message'] = 'Rebuilding daily totals up to ' . gmdate( 'Y-m-d', ( $end + 1 ) * 86400 ) . '.';

				return self::save( $state );
			}

			return self::save( [ 'state' => 'idle', 'stage' => '', 'message' => 'Nothing to do.' ] );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );

			return self::save( [
				'state'   => 'error',
				'stage'   => $state['stage'] ?? '',
				'message' => 'The import stopped: ' . $e->getMessage() . ' Start again to resume from the same stage.',
			] + array_diff_key( $state, [ 'message' => true ] ) );
		}
	}

	// -------------------------------------------------------------- stages

	private static function next_stage( string $stage ): string {
		$names = array_keys( self::STAGES );
		$index = array_search( $stage, $names, true );

		return $names[ $index + 1 ] ?? 'totals';
	}

	/** Rows with primary key above $last, in key order. */
	private static function chunk( string $table, string $pk, int $last ): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::old( $table ) . " WHERE {$pk} > %d ORDER BY {$pk} LIMIT %d",
			$last,
			self::CHUNK
		), ARRAY_A );
	}

	private static function import_visitors( array $rows ): void {
		$now = time();
		$map = [];
		foreach ( $rows as $row ) {
			$uid = Validator::text( $row['uid'], 64 );

			$map[ (int) $row['ID'] ] = $uid === '' ? 0 : DB::upsert( DB::table( 'visitors' ), [
				'hash'       => DB::key( $uid ),
				'first_seen' => $now,
				'last_seen'  => $now,
				'sessions'   => 0,
			] );
		}
		self::map( 'visitors', $map );
	}

	/** Burst keys cities by city_code, which can be negative. Map keys are shifted to stay non-negative. */
	private static function import_locations( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$country    = strtoupper( Validator::text( $row['country_code'], 2 ) );
			$country_id = preg_match( '/^[A-Z]{2}$/', $country ) ? DB::upsert( DB::table( 'countries' ), [ 'code' => $country ] ) : 0;
			$city       = mb_substr( (string) $row['city'], 0, 64 );
			$region     = mb_substr( (string) $row['state'], 0, 64 );
			$id         = 0;

			if ( $country_id > 0 ) {
				$id = DB::upsert( DB::table( 'cities' ), [
					'hash'       => DB::key( $country . '|' . $region . '|' . $city ),
					'country_id' => $country_id,
					'region'     => $region,
					'name'       => $city,
				] );
			}
			$map[ self::city_key( (int) $row['city_code'] ) ] = $id;
		}
		self::map( 'cities', $map );
	}

	private static function import_browsers( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$name = Validator::text( $row['name'], 32 );

			$map[ (int) $row['ID'] ] = $name === '' ? 0 : DB::upsert( DB::table( 'ua' ), [ 'kind' => 1, 'name' => $name ] );
		}
		self::map( 'browsers', $map );
	}

	private static function import_platforms( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$name = Validator::text( $row['name'], 32 );

			$map[ (int) $row['ID'] ] = $name === '' ? 0 : DB::upsert( DB::table( 'ua' ), [ 'kind' => 2, 'name' => $name ] );
		}
		self::map( 'platforms', $map );
	}

	/** Burst's device names are free text, so this only looks for the three classes the plugin knows. */
	private static function import_devices( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$name = strtolower( (string) $row['name'] );

			if ( str_contains( $name, 'tablet' ) ) {
				$type = UA::TABLET;
			} elseif ( str_contains( $name, 'mobile' ) || str_contains( $name, 'phone' ) ) {
				$type = UA::MOBILE;
			} else {
				$type = UA::DESKTOP;
			}
			$map[ (int) $row['ID'] ] = $type;
		}
		self::map( 'devices', $map );
	}

	private static function import_sessions( array $rows ): void {
		$visitors  = self::lookup( 'visitors', array_column( $rows, 'uid_id' ) );
		$browsers  = self::lookup( 'browsers', array_column( $rows, 'browser_id' ) );
		$platforms = self::lookup( 'platforms', array_column( $rows, 'platform_id' ) );
		$devices   = self::lookup( 'devices', array_column( $rows, 'device_id' ) );

		$city_keys = array_map( static fn( $code ) => self::city_key( (int) $code ), array_column( $rows, 'city_code' ) );
		$cities    = self::lookup( 'cities', $city_keys );
		$countries = self::countries_of( array_values( $cities ) );

		$batch = [];
		$keys  = [];
		foreach ( $rows as $row ) {
			$visitor = $visitors[ (int) $row['uid_id'] ] ?? 0;
			$start   = (int) $row['start_time'];
			if ( $visitor <= 0 || $start <= 0 ) {
				continue; // A session with no visitor or start time cannot be placed.
			}

			$city    = $cities[ self::city_key( (int) $row['city_code'] ) ] ?? 0;
			$browser = $browsers[ (int) $row['browser_id'] ] ?? 0;
			$os      = $platforms[ (int) $row['platform_id'] ] ?? 0;

			$batch[] = [
				'visitor_id'    => $visitor,
				'started_at'    => $start,
				'last_seen'     => $start,
				'entry_page_id' => 0, // Filled in by totals() from the views.
				'exit_page_id'  => 0,
				'referrer_id'   => self::referrer_id( (string) ( $row['referrer'] ?? '' ), (string) ( $row['source_category'] ?? '' ) ),
				'campaign_id'   => null,
				'country_id'    => $countries[ $city ] ?? null,
				'city_id'       => $city ?: null,
				'device'        => $devices[ (int) $row['device_id'] ] ?? UA::DESKTOP,
				'browser_id'    => $browser ?: null,
				'os_id'         => $os ?: null,
				'views'         => 0,
				'events'        => 0,
				'duration'      => 0,
				'is_new'        => (int) (bool) $row['first_time_visit'],
				'is_bounce'     => 1,
			];
			$keys[] = (int) $row['ID'];

			if ( count( $batch ) === self::BATCH ) {
				self::insert_batch( 'sessions', $batch, $keys );
				$batch = [];
				$keys  = [];
			}
		}

		if ( $batch ) {
			self::insert_batch( 'sessions', $batch, $keys );
		}
	}

	private static function import_views( array $rows ): void {
		$sessions = self::lookup( 'sessions', array_column( $rows, 'session_id' ) );

		$batch = [];
		foreach ( $rows as $row ) {
			$session = $sessions[ (int) $row['session_id'] ] ?? 0;
			$created = (int) $row['time'];
			if ( $session <= 0 || $created <= 0 ) {
				continue;
			}

			$batch[] = [
				'session_id' => $session,
				'page_id'    => self::page_id( $row ),
				'view_key'   => bin2hex( random_bytes( 8 ) ),
				'created_at' => $created,
				'duration'   => min( 14400, max( 0, (int) $row['time_on_page'] ) ),
				'seq'        => 1,
			];

			if ( count( $batch ) === self::BATCH ) {
				DB::insert_many( DB::table( 'views' ), $batch );
				$batch = [];
			}
		}

		if ( $batch ) {
			DB::insert_many( DB::table( 'views' ), $batch );
		}
	}

	/**
	 * Final corrections once every session and view is in. Only the rows this import created
	 * are changed, so the plugin's own visitors and sessions keep their values.
	 */
	private static function totals(): void {
		global $wpdb;

		$visitors = DB::table( 'visitors' );
		$sessions = DB::table( 'sessions' );
		$views    = DB::table( 'views' );
		$v_map    = self::map_table( 'visitors' );
		$s_map    = self::map_table( 'sessions' );

		$wpdb->query( "UPDATE {$visitors} v JOIN {$v_map} vm ON vm.new_id = v.id JOIN (
				SELECT s.visitor_id, MIN(s.started_at) AS first_seen, MAX(s.last_seen) AS last_seen, LEAST(COUNT(*), 65535) AS sessions
				FROM {$sessions} s JOIN {$s_map} sm ON sm.new_id = s.id GROUP BY s.visitor_id
			) x ON x.visitor_id = v.id
			SET v.first_seen = x.first_seen, v.last_seen = x.last_seen, v.sessions = x.sessions" );

		$wpdb->query( "UPDATE {$sessions} s JOIN {$s_map} sm ON sm.new_id = s.id JOIN (
				SELECT vw.session_id, LEAST(COUNT(*), 65535) AS total, MAX(vw.created_at) AS last_at
				FROM {$views} vw JOIN {$s_map} m ON m.new_id = vw.session_id GROUP BY vw.session_id
			) x ON x.session_id = s.id
			SET s.views = x.total, s.last_seen = GREATEST(s.started_at, x.last_at),
				s.duration = LEAST(16777215, GREATEST(0, x.last_at - s.started_at))" );

		// Entry and exit are the first and last view of each session, in the order they were imported.
		$wpdb->query( "UPDATE {$sessions} s JOIN {$s_map} sm ON sm.new_id = s.id JOIN (
				SELECT vw.session_id, MIN(vw.id) AS first_id, MAX(vw.id) AS last_id
				FROM {$views} vw JOIN {$s_map} m ON m.new_id = vw.session_id GROUP BY vw.session_id
			) f ON f.session_id = s.id
			JOIN {$views} ve ON ve.id = f.first_id
			JOIN {$views} vx ON vx.id = f.last_id
			SET s.entry_page_id = ve.page_id, s.exit_page_id = vx.page_id" );

		$wpdb->query( "UPDATE {$sessions} s JOIN {$s_map} sm ON sm.new_id = s.id
			SET s.is_bounce = CASE WHEN s.views > 1 THEN 0 ELSE 1 END" );
	}

	private static function first_imported_day(): ?int {
		global $wpdb;

		$min = (int) $wpdb->get_var(
			'SELECT MIN(s.started_at) FROM ' . DB::table( 'sessions' ) . ' s JOIN ' . self::map_table( 'sessions' ) . ' m ON m.new_id = s.id'
		);

		return $min > 0 ? Ranges::day_of( $min ) : null;
	}

	// ------------------------------------------------------------- helpers

	/**
	 * Burst page_url and page_id => the page id in SNN Analytics. Singular posts are keyed by
	 * their post id. Everything else is keyed by its path.
	 */
	private static function page_id( array $row ): int {
		$path    = self::path_of( (string) $row['page_url'] );
		$post_id = (int) ( $row['page_id'] ?? 0 );
		$type    = self::page_type_of( (string) ( $row['page_type'] ?? '' ), $path, $post_id );
		$object  = $type === 1 ? $post_id : 0;
		$key     = $type === 1 ? 's:' . $object : 'u:' . $path;

		if ( ! isset( self::$page_cache[ $key ] ) ) {
			self::$page_cache[ $key ] = DB::upsert( DB::table( 'pages' ), [
				'hash'      => DB::key( $key ),
				'type'      => $type,
				'object_id' => $object,
				'path'      => $path,
				'title'     => '',
				'post_type' => '',
			] );
		}

		return self::$page_cache[ $key ];
	}

	/** Burst page_type => SNN Analytics page type. Burst's value names are not all known, so unknown ones are "other". */
	private static function page_type_of( string $page_type, string $path, int $post_id ): int {
		return match ( $page_type ) {
			'404'                              => 7,
			'search'                           => 6,
			'author'                           => 4,
			'date', 'day', 'month', 'year'     => 5,
			'category', 'tag', 'tax', 'term'   => 3,
			'archive', 'post_type_archive'     => 8,
			default                            => $post_id > 0 ? 1 : ( $path === '/' ? 2 : 9 ),
		};
	}

	/** Referrer domain => id, or null for a visit with no referrer. */
	private static function referrer_id( string $referrer, string $source_category ): ?int {
		$domain = self::referrer_domain( $referrer );
		if ( $domain === '' ) {
			return null;
		}

		if ( ! isset( self::$referrer_cache[ $domain ] ) ) {
			$type = self::REFERRER_TYPES[ strtolower( $source_category ) ] ?? 4;

			self::$referrer_cache[ $domain ] = DB::upsert( DB::table( 'referrers' ), [
				'hash'   => DB::key( $domain ),
				'domain' => $domain,
				'type'   => $type,
			] );
		}

		return self::$referrer_cache[ $domain ];
	}

	private static function referrer_domain( string $referrer ): string {
		$referrer = trim( $referrer );
		if ( $referrer === '' ) {
			return '';
		}
		if ( ! str_contains( $referrer, '://' ) ) {
			$referrer = 'http://' . $referrer;
		}

		$host = parse_url( $referrer, PHP_URL_HOST );

		return is_string( $host ) ? Validator::text( strtolower( $host ), 253 ) : '';
	}

	/** Country id for each of the given city ids, read from the imported cities. */
	private static function countries_of( array $city_ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $city_ids ) ) ) );
		if ( ! $ids ) {
			return [];
		}

		$in  = implode( ',', $ids );
		$out = [];
		foreach ( $wpdb->get_results( 'SELECT id, country_id FROM ' . DB::table( 'cities' ) . " WHERE id IN ({$in})", ARRAY_A ) as $row ) {
			$out[ (int) $row['id'] ] = (int) $row['country_id'];
		}

		return $out;
	}

	/**
	 * Inserts a batch of rows and maps each old key to its new id. Ids come back
	 * consecutively, starting at the id of the first row.
	 */
	private static function insert_batch( string $table, array $rows, array $old_keys ): void {
		$first = DB::insert_many( DB::table( $table ), $rows );

		$map = [];
		foreach ( $old_keys as $i => $old ) {
			$map[ $old ] = $first + $i;
		}
		self::map( $table, $map );
	}

	/** Writes old => new pairs into the map table of $name. */
	private static function map( string $name, array $pairs ): void {
		global $wpdb;

		if ( $pairs === [] ) {
			return;
		}

		$table = self::map_table( $name );
		$marks = [];
		$args  = [];
		foreach ( $pairs as $old => $new ) {
			$marks[] = '(%d, %d)';
			$args[]  = (int) $old;
			$args[]  = (int) $new;
		}

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (old_id, new_id) VALUES " . implode( ', ', $marks ) . ' ON DUPLICATE KEY UPDATE new_id = VALUES(new_id)',
			...$args
		) );
	}

	/** New ids for the given old ids. Missing ids are absent from the result. */
	private static function lookup( string $name, array $old_ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_map( 'intval', array_filter( $old_ids, static fn( $v ) => $v !== null && $v !== '' ) ) ) );
		if ( ! $ids ) {
			return [];
		}

		$table = self::map_table( $name );
		$in    = implode( ',', $ids );
		$out   = [];
		foreach ( $wpdb->get_results( "SELECT old_id, new_id FROM {$table} WHERE old_id IN ({$in})", ARRAY_A ) as $row ) {
			$out[ (int) $row['old_id'] ] = (int) $row['new_id'];
		}

		return $out;
	}

	/** Non-negative map key for a Burst city_code. */
	private static function city_key( int $city_code ): int {
		return $city_code - self::FIRST_KEY;
	}

	private static function create_maps(): void {
		global $wpdb;

		foreach ( self::MAPS as $name ) {
			$wpdb->query( 'CREATE TABLE IF NOT EXISTS ' . self::map_table( $name ) . ' (
				old_id BIGINT UNSIGNED NOT NULL,
				new_id BIGINT UNSIGNED NOT NULL,
				PRIMARY KEY (old_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' );
		}
	}

	private static function drop_maps(): void {
		global $wpdb;

		foreach ( self::MAPS as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::map_table( $name ) );
		}
	}

	/** Burst's map tables have their own names, so this importer never shares a map with the IAWP importer. */
	private static function map_table( string $name ): string {
		return DB::table( 'tmp_burst_map_' . $name );
	}

	private static function old( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	private static function path_of( string $url ): string {
		if ( $url === '' ) {
			return '/';
		}
		$path = (string) parse_url( $url, PHP_URL_PATH );

		return mb_substr( $path === '' ? '/' : $path, 0, 512 );
	}

	private static function save( array $state ): array {
		update_option( self::OPTION, $state, false );

		return $state;
	}

	private static function summary( array $rows ): string {
		$parts = [];
		foreach ( [ 'sessions', 'views' ] as $key ) {
			$parts[] = $key . ' ' . number_format_i18n( (int) ( $rows[ $key ] ?? 0 ) );
		}

		return implode( ', ', $parts );
	}
}
