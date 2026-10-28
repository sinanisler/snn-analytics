<?php
/**
 * Imports Independent Analytics (IAWP) data into SNN Analytics.
 *
 * Reads only the IAWP tables and never changes or deletes them. The import runs in stages.
 * Each call to step() handles one chunk of 5,000 rows, and progress is stored in an option,
 * so an interrupted import resumes where it stopped and a request never runs long.
 *
 * Old ids are mapped to new ids in temporary tables, which are dropped at the end.
 * IAWP stores datetimes in UTC, and so does this plugin.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Importer {

	const OPTION    = 'snn_a_import';
	const CHUNK     = 5000;
	const BATCH     = 500;
	const DAY_BATCH = 30;

	/** Stage => [ old table, primary key ]. Dimensions come first, because facts refer to them. */
	const STAGES = [
		'countries'       => [ 'countries', 'country_id' ],
		'cities'          => [ 'cities', 'city_id' ],
		'resources'       => [ 'resources', 'id' ],
		'referrers'       => [ 'referrers', 'id' ],
		'campaigns'       => [ 'campaigns', 'campaign_id' ],
		'device_types'    => [ 'device_types', 'device_type_id' ],
		'device_oss'      => [ 'device_oss', 'device_os_id' ],
		'device_browsers' => [ 'device_browsers', 'device_browser_id' ],
		'visitors'        => [ 'visitors', 'visitor_id' ],
		'sessions'        => [ 'sessions', 'session_id' ],
		'views'           => [ 'views', 'id' ],
		'clicks'          => [ 'clicks', 'click_id' ],
	];

	/** Map tables: old id => new id. */
	const MAPS = [ 'countries', 'cities', 'resources', 'referrers', 'campaigns', 'device_types', 'device_oss', 'device_browsers', 'visitors', 'sessions', 'views' ];

	const REFERRER_TYPES = [ 'Search' => 2, 'Social' => 3, 'Referrer' => 4, 'AI' => 4, 'Ad' => 5 ];

	// ----------------------------------------------------------- public API

	/** Whether the IAWP tables exist, and roughly how much data they hold. */
	public static function detect(): array {
		global $wpdb;

		$tables = [ 'sessions', 'views', 'clicks' ];
		$found  = [];
		foreach ( $tables as $table ) {
			$name = self::old( $table );
			$rows = $wpdb->get_var( $wpdb->prepare(
				'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$name
			) );
			$found[ $table ] = $rows === null ? null : (int) $rows;
		}

		$missing = self::missing_columns();

		return [
			'available' => $found['sessions'] !== null && $found['views'] !== null && $missing === [],
			'sessions'  => $found['sessions'] ?? 0,
			'views'     => $found['views'] ?? 0,
			'clicks'    => $found['clicks'] ?? 0,
			'missing'   => $missing,
		];
	}

	/** Columns the importer reads, per IAWP table. Anything absent means a different IAWP version. */
	const REQUIRED_COLUMNS = [
		'sessions'        => [ 'session_id', 'visitor_id', 'initial_view_id', 'final_view_id', 'referrer_id', 'campaign_id', 'created_at', 'ended_at', 'total_views', 'country_id', 'city_id', 'device_type_id', 'device_os_id', 'device_browser_id', 'is_first_session' ],
		'views'           => [ 'id', 'resource_id', 'viewed_at', 'session_id', 'next_viewed_at' ],
		'resources'       => [ 'id', 'resource', 'singular_id', 'author_id', 'term_id', 'post_type', 'cached_title', 'cached_url' ],
		'visitors'        => [ 'visitor_id', 'hash' ],
		'referrers'       => [ 'id', 'domain', 'referrer_type_id' ],
		'referrer_types'  => [ 'id', 'referrer_type' ],
		'campaigns'       => [ 'campaign_id', 'utm_term', 'utm_content', 'utm_source_id', 'utm_medium_id', 'utm_campaign_id' ],
		'utm_sources'     => [ 'id', 'utm_source' ],
		'utm_mediums'     => [ 'id', 'utm_medium' ],
		'utm_campaigns'   => [ 'id', 'utm_campaign' ],
		'countries'       => [ 'country_id', 'country_code' ],
		'cities'          => [ 'city_id', 'country_id', 'subdivision', 'city' ],
		'device_types'    => [ 'device_type_id', 'device_type' ],
		'device_oss'      => [ 'device_os_id', 'device_os' ],
		'device_browsers' => [ 'device_browser_id', 'device_browser' ],
		'clicks'          => [ 'click_id', 'view_id', 'created_at' ],
		'clicked_links'   => [ 'click_id', 'link_id' ],
		'links'           => [ 'id', 'click_target_id' ],
		'click_targets'   => [ 'click_target_id', 'target', 'protocol' ],
	];

	/** "table.column" entries that are missing from the IAWP tables on this site. */
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

		// Refuse before touching anything when the IAWP tables do not match what the importer reads.
		$detect = self::detect();
		if ( ! $detect['available'] ) {
			return self::save( [
				'state'   => 'error',
				'stage'   => '',
				'message' => $detect['sessions'] === 0 && $detect['missing'] === []
					? 'No Independent Analytics data was found.'
					: 'This Independent Analytics version has a different layout. Missing: ' . implode( ', ', $detect['missing'] ) . '.',
			] );
		}

		self::create_maps();

		if ( $state === 'error' && ! empty( $current['stage'] ) ) {
			return self::save( array_merge( $current, [ 'state' => 'running', 'message' => 'Resuming the import.' ] ) );
		}

		return self::save( [
			'state'   => 'running',
			'stage'   => 'countries',
			'last'    => 0,
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
					$state['last']  = 0;
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

		if ( $table === 'clicks' ) {
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT c.click_id, c.view_id, c.created_at, MIN(ct.target) AS target, MIN(ct.protocol) AS protocol
				 FROM ' . self::old( 'clicks' ) . ' c
				 LEFT JOIN ' . self::old( 'clicked_links' ) . ' cl ON cl.click_id = c.click_id
				 LEFT JOIN ' . self::old( 'links' ) . ' l ON l.id = cl.link_id
				 LEFT JOIN ' . self::old( 'click_targets' ) . ' ct ON ct.click_target_id = l.click_target_id
				 WHERE c.click_id > %d GROUP BY c.click_id, c.view_id, c.created_at ORDER BY c.click_id LIMIT %d',
				$last,
				self::CHUNK
			), ARRAY_A );
		}

		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::old( $table ) . " WHERE {$pk} > %d ORDER BY {$pk} LIMIT %d",
			$last,
			self::CHUNK
		), ARRAY_A );
	}

	private static function import_countries( array $rows ): void {
		foreach ( $rows as $row ) {
			$code = strtoupper( Validator::text( $row['country_code'], 2 ) );
			$id   = preg_match( '/^[A-Z]{2}$/', $code ) ? DB::upsert( DB::table( 'countries' ), [ 'code' => $code ] ) : 0;
			self::map( 'countries', [ (int) $row['country_id'] => $id ] );
		}
	}

	private static function import_cities( array $rows ): void {
		$countries = self::lookup( 'countries', array_column( $rows, 'country_id' ) );

		foreach ( $rows as $row ) {
			$country = $countries[ (int) $row['country_id'] ] ?? 0;
			$city    = mb_substr( (string) $row['city'], 0, 64 );
			$region  = mb_substr( (string) $row['subdivision'], 0, 64 );
			$id      = 0;

			if ( $country > 0 && $city !== '' ) {
				$id = DB::upsert( DB::table( 'cities' ), [
					'hash'       => DB::key( $country . '|' . $region . '|' . $city ),
					'country_id' => $country,
					'region'     => $region,
					'name'       => $city,
				] );
			}
			self::map( 'cities', [ (int) $row['city_id'] => $id ] );
		}
	}

	private static function import_resources( array $rows ): void {
		foreach ( $rows as $row ) {
			$page = self::page_identity( $row );
			$path = self::path_of( (string) $row['cached_url'] );

			$id = DB::upsert(
				DB::table( 'pages' ),
				[
					'hash'      => DB::key( $page['key'] ),
					'type'      => $page['type'],
					'object_id' => $page['object_id'],
					'path'      => $path,
					'title'     => mb_substr( (string) $row['cached_title'], 0, 255 ),
					'post_type' => mb_substr( (string) ( $row['post_type'] ?? '' ), 0, 32 ),
				],
				[ 'title' => mb_substr( (string) $row['cached_title'], 0, 255 ), 'path' => $path ]
			);
			self::map( 'resources', [ (int) $row['id'] => $id ] );
		}
	}

	private static function import_referrers( array $rows ): void {
		global $wpdb;

		$types = [];
		foreach ( $wpdb->get_results( 'SELECT id, referrer_type FROM ' . self::old( 'referrer_types' ), ARRAY_A ) as $type ) {
			$types[ (int) $type['id'] ] = (string) $type['referrer_type'];
		}

		foreach ( $rows as $row ) {
			$domain = Validator::text( $row['domain'], 253 );
			$label  = $types[ (int) $row['referrer_type_id'] ] ?? '';
			$id     = 0;

			if ( $domain !== '' && $label !== 'Direct' ) {
				$id = DB::upsert( DB::table( 'referrers' ), [
					'hash'   => DB::key( strtolower( $domain ) ),
					'domain' => strtolower( $domain ),
					'type'   => self::REFERRER_TYPES[ $label ] ?? 4,
				] );
			}
			self::map( 'referrers', [ (int) $row['id'] => $id ] );
		}
	}

	/** Campaigns, with their UTM values, in one step. */
	private static function import_campaigns( array $rows ): void {
		global $wpdb;

		$campaign_ids = array_column( $rows, 'campaign_id' );
		$in           = implode( ',', array_map( 'intval', $campaign_ids ) );

		$joined = $wpdb->get_results(
			'SELECT c.campaign_id, c.utm_term, c.utm_content, s.utm_source, m.utm_medium, u.utm_campaign
			 FROM ' . self::old( 'campaigns' ) . ' c
			 LEFT JOIN ' . self::old( 'utm_sources' ) . ' s ON s.id = c.utm_source_id
			 LEFT JOIN ' . self::old( 'utm_mediums' ) . ' m ON m.id = c.utm_medium_id
			 LEFT JOIN ' . self::old( 'utm_campaigns' ) . ' u ON u.id = c.utm_campaign_id
			 WHERE c.campaign_id IN (' . $in . ')',
			ARRAY_A
		);

		$map = [];
		foreach ( $joined as $row ) {
			$values = [
				Validator::text( $row['utm_source'], 255 ),
				Validator::text( $row['utm_medium'], 255 ),
				Validator::text( $row['utm_campaign'], 255 ),
				Validator::text( $row['utm_term'], 255 ),
				Validator::text( $row['utm_content'], 255 ),
			];
			$values = array_map( 'mb_strtolower', $values );

			$ids = [];
			$cols = [ 'source_id', 'medium_id', 'campaign_id', 'term_id', 'content_id' ];
			foreach ( $values as $i => $value ) {
				$ids[ $cols[ $i ] ] = $value === ''
					? null
					: DB::upsert( DB::table( 'utm_values' ), [ 'hash' => DB::key( $value ), 'value' => $value ] );
			}

			$id = implode( '', $values ) === '' ? 0 : DB::upsert(
				DB::table( 'campaigns' ),
				[ 'hash' => DB::key( implode( '|', $values ) ) ] + $ids
			);
			$map[ (int) $row['campaign_id'] ] = $id;
		}

		// Campaigns with no UTM values at all map to nothing.
		foreach ( $campaign_ids as $old ) {
			$map[ (int) $old ] = $map[ (int) $old ] ?? 0;
		}

		self::map( 'campaigns', $map );
	}

	private static function import_device_types( array $rows ): void {
		$labels = [ 'Desktop' => UA::DESKTOP, 'Mobile' => UA::MOBILE, 'Tablet' => UA::TABLET ];
		$map    = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row['device_type_id'] ] = $labels[ (string) $row['device_type'] ] ?? UA::DESKTOP;
		}
		self::map( 'device_types', $map );
	}

	private static function import_device_oss( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$name = Validator::text( $row['device_os'], 32 );
			$map[ (int) $row['device_os_id'] ] = $name === '' ? 0 : DB::upsert( DB::table( 'ua' ), [ 'kind' => 2, 'name' => $name ] );
		}
		self::map( 'device_oss', $map );
	}

	private static function import_device_browsers( array $rows ): void {
		$map = [];
		foreach ( $rows as $row ) {
			$name = Validator::text( $row['device_browser'], 32 );
			$map[ (int) $row['device_browser_id'] ] = $name === '' ? 0 : DB::upsert( DB::table( 'ua' ), [ 'kind' => 1, 'name' => $name ] );
		}
		self::map( 'device_browsers', $map );
	}

	private static function import_visitors( array $rows ): void {
		$now = time();
		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row['visitor_id'] ] = DB::upsert( DB::table( 'visitors' ), [
				'hash'       => DB::key( (string) $row['hash'] ),
				'first_seen' => $now,
				'last_seen'  => $now,
				'sessions'   => 0,
			] );
		}
		self::map( 'visitors', $map );
	}

	private static function import_sessions( array $rows ): void {
		global $wpdb;

		// Entry and exit pages come from the views the session points at.
		$view_ids = [];
		foreach ( $rows as $row ) {
			$view_ids[] = (int) $row['initial_view_id'];
			$view_ids[] = (int) $row['final_view_id'];
		}
		$view_ids = array_values( array_unique( array_filter( $view_ids ) ) );
		$resource_of = [];
		if ( $view_ids ) {
			$in = implode( ',', $view_ids );
			foreach ( $wpdb->get_results( 'SELECT id, resource_id FROM ' . self::old( 'views' ) . " WHERE id IN ({$in})", ARRAY_A ) as $v ) {
				$resource_of[ (int) $v['id'] ] = (int) $v['resource_id'];
			}
		}

		$pages     = self::lookup( 'resources', array_values( $resource_of ) );
		$visitors  = self::lookup( 'visitors', array_column( $rows, 'visitor_id' ) );
		$referrers = self::lookup( 'referrers', array_column( $rows, 'referrer_id' ) );
		$campaigns = self::lookup( 'campaigns', array_column( $rows, 'campaign_id' ) );
		$countries = self::lookup( 'countries', array_column( $rows, 'country_id' ) );
		$cities    = self::lookup( 'cities', array_column( $rows, 'city_id' ) );
		$devices   = self::lookup( 'device_types', array_column( $rows, 'device_type_id' ) );
		$oses      = self::lookup( 'device_oss', array_column( $rows, 'device_os_id' ) );
		$browsers  = self::lookup( 'device_browsers', array_column( $rows, 'device_browser_id' ) );

		$batch = [];
		$keys  = [];
		foreach ( $rows as $row ) {
			$visitor = $visitors[ (int) $row['visitor_id'] ] ?? 0;
			if ( $visitor <= 0 ) {
				continue; // A session with no visitor cannot be placed.
			}

			$started = self::utc( (string) $row['created_at'] );
			$ended   = self::utc( (string) ( $row['ended_at'] ?? '' ) ) ?: $started;
			$entry   = $pages[ $resource_of[ (int) $row['initial_view_id'] ] ?? 0 ] ?? 0;
			$exit    = $pages[ $resource_of[ (int) $row['final_view_id'] ] ?? 0 ] ?? 0;
			$views   = min( 65535, max( 0, (int) $row['total_views'] ) );

			$batch[] = [
				'visitor_id'    => $visitor,
				'started_at'    => $started,
				'last_seen'     => max( $started, $ended ),
				'entry_page_id' => $entry ?: 0,
				'exit_page_id'  => $exit ?: ( $entry ?: 0 ),
				'referrer_id'   => ( $referrers[ (int) $row['referrer_id'] ] ?? 0 ) ?: null,
				'campaign_id'   => ( $campaigns[ (int) $row['campaign_id'] ] ?? 0 ) ?: null,
				'country_id'    => ( $countries[ (int) $row['country_id'] ] ?? 0 ) ?: null,
				'city_id'       => ( $cities[ (int) $row['city_id'] ] ?? 0 ) ?: null,
				'device'        => $devices[ (int) $row['device_type_id'] ] ?? UA::DESKTOP,
				'browser_id'    => ( $browsers[ (int) $row['device_browser_id'] ] ?? 0 ) ?: null,
				'os_id'         => ( $oses[ (int) $row['device_os_id'] ] ?? 0 ) ?: null,
				'views'         => $views,
				'events'        => 0,
				'duration'      => min( 16777215, max( 0, $ended - $started ) ),
				'is_new'        => (int) (bool) $row['is_first_session'],
				'is_bounce'     => $views <= 1 ? 1 : 0,
			];
			$keys[] = (int) $row['session_id'];

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
		$pages    = self::lookup( 'resources', array_column( $rows, 'resource_id' ) );

		$batch = [];
		$keys  = [];
		foreach ( $rows as $row ) {
			$session = $sessions[ (int) $row['session_id'] ] ?? 0;
			$page    = $pages[ (int) $row['resource_id'] ] ?? 0;
			if ( $session <= 0 || $page <= 0 ) {
				continue;
			}

			$created = self::utc( (string) $row['viewed_at'] );
			$next    = self::utc( (string) ( $row['next_viewed_at'] ?? '' ) );

			$batch[] = [
				'session_id' => $session,
				'page_id'    => $page,
				'view_key'   => bin2hex( random_bytes( 8 ) ),
				'created_at' => $created,
				'duration'   => $next > $created ? min( 14400, $next - $created ) : 0,
				'seq'        => 1,
			];
			$keys[] = (int) $row['id'];

			if ( count( $batch ) === self::BATCH ) {
				self::insert_batch( 'views', $batch, $keys );
				$batch = [];
				$keys  = [];
			}
		}

		if ( $batch ) {
			self::insert_batch( 'views', $batch, $keys );
		}
	}

	private static function import_clicks( array $rows ): void {
		global $wpdb;

		$views    = self::lookup( 'views', array_column( $rows, 'view_id' ) );
		$sessions = [];
		$new_views = array_values( array_filter( $views ) );
		if ( $new_views ) {
			$in = implode( ',', array_map( 'intval', $new_views ) );
			foreach ( $wpdb->get_results( 'SELECT id, session_id FROM ' . DB::table( 'views' ) . " WHERE id IN ({$in})", ARRAY_A ) as $v ) {
				$sessions[ (int) $v['id'] ] = (int) $v['session_id'];
			}
		}

		$batch = [];
		foreach ( $rows as $row ) {
			$view    = $views[ (int) $row['view_id'] ] ?? 0;
			$session = $sessions[ $view ] ?? 0;
			$target  = Validator::text( $row['target'] ?? '', 2048 );
			if ( $view <= 0 || $session <= 0 || $target === '' ) {
				continue;
			}

			[ $type, $value ] = self::click_target( $target, (string) ( $row['protocol'] ?? '' ) );
			if ( $value === null ) {
				continue;
			}

			$batch[] = [
				'session_id' => $session,
				'view_id'    => $view,
				'created_at' => self::utc( (string) $row['created_at'] ),
				'type'       => $type,
				'target_id'  => DB::upsert( DB::table( 'event_targets' ), [ 'hash' => DB::key( $value ), 'value' => $value ] ),
			];

			if ( count( $batch ) === self::BATCH ) {
				DB::insert_many( DB::table( 'events' ), $batch );
				$batch = [];
			}
		}

		if ( $batch ) {
			DB::insert_many( DB::table( 'events' ), $batch );
		}
	}

	/** Final corrections once every session and event is in. */
	private static function totals(): void {
		global $wpdb;

		$visitors = DB::table( 'visitors' );
		$sessions = DB::table( 'sessions' );
		$events   = DB::table( 'events' );

		$wpdb->query( "UPDATE {$visitors} v JOIN (
				SELECT visitor_id, MIN(started_at) AS first_seen, MAX(last_seen) AS last_seen, LEAST(COUNT(*), 65535) AS sessions
				FROM {$sessions} GROUP BY visitor_id
			) x ON x.visitor_id = v.id
			SET v.first_seen = x.first_seen, v.last_seen = x.last_seen, v.sessions = x.sessions" );

		$wpdb->query( "UPDATE {$sessions} s JOIN (
				SELECT session_id, LEAST(COUNT(*), 65535) AS total FROM {$events} GROUP BY session_id
			) e ON e.session_id = s.id
			SET s.events = e.total, s.is_bounce = 0" );

		$wpdb->query( "UPDATE {$sessions} SET is_bounce = 0 WHERE views > 1" );
	}

	private static function first_imported_day(): ?int {
		global $wpdb;

		$min = (int) $wpdb->get_var( 'SELECT MIN(started_at) FROM ' . DB::table( 'sessions' ) );

		return $min > 0 ? Ranges::day_of( $min ) : null;
	}

	// ------------------------------------------------------------- helpers

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

	private static function map_table( string $name ): string {
		return DB::table( 'tmp_map_' . $name );
	}

	private static function old( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . 'independent_analytics_' . $table;
	}

	private static function save( array $state ): array {
		update_option( self::OPTION, $state, false );

		return $state;
	}

	private static function summary( array $rows ): string {
		$parts = [];
		foreach ( [ 'sessions', 'views', 'clicks' ] as $key ) {
			$parts[] = $key . ' ' . number_format_i18n( (int) ( $rows[ $key ] ?? 0 ) );
		}

		return implode( ', ', $parts );
	}

	/** IAWP's resource row => the page key this plugin uses. */
	private static function page_identity( array $row ): array {
		if ( (int) $row['singular_id'] > 0 ) {
			return [ 'key' => 's:' . (int) $row['singular_id'], 'type' => 1, 'object_id' => (int) $row['singular_id'] ];
		}
		if ( (int) $row['term_id'] > 0 ) {
			return [ 'key' => 't:' . (int) $row['term_id'], 'type' => 3, 'object_id' => (int) $row['term_id'] ];
		}
		if ( (int) $row['author_id'] > 0 ) {
			return [ 'key' => 'a:' . (int) $row['author_id'], 'type' => 4, 'object_id' => (int) $row['author_id'] ];
		}
		if ( ! empty( $row['post_type'] ) && $row['resource'] === 'post_type_archive' ) {
			return [ 'key' => 'pta:' . substr( preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $row['post_type'] ) ), 0, 20 ), 'type' => 8, 'object_id' => 0 ];
		}

		return match ( (string) $row['resource'] ) {
			'home'   => [ 'key' => 'home', 'type' => 2, 'object_id' => 0 ],
			'search' => [ 'key' => 'search', 'type' => 6, 'object_id' => 0 ],
			'404'    => [ 'key' => '404', 'type' => 7, 'object_id' => 0 ],
			'date', 'date_archive' => [ 'key' => 'date', 'type' => 5, 'object_id' => 0 ],
			default  => [ 'key' => 'other', 'type' => 9, 'object_id' => 0 ],
		};
	}

	private static function path_of( string $url ): string {
		if ( $url === '' ) {
			return '/';
		}
		$path = (string) parse_url( $url, PHP_URL_PATH );

		return mb_substr( $path === '' ? '/' : $path, 0, 512 );
	}

	/**
	 * [ event type, stored value ] for a click target, or [ 0, null ] when it should be skipped.
	 */
	private static function click_target( string $target, string $protocol ): array {
		if ( $protocol === 'mailto' ) {
			return [ 3, 'mailto' ];
		}
		if ( $protocol === 'tel' || $protocol === 'sms' ) {
			return [ 4, 'tel' ];
		}

		$parts = parse_url( $target );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return [ 0, null ];
		}

		$url  = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] ) . ( $parts['path'] ?? '/' );
		$path = (string) ( $parts['path'] ?? '' );
		$type = preg_match( '/\.(pdf|zip|docx?|xlsx?|pptx?|csv|rar|7z|dmg|exe|mp3|mp4)$/i', $path ) ? 2 : 1;

		return [ $type, mb_substr( $url, 0, 512 ) ];
	}

	/** Parses an IAWP UTC datetime into a unix timestamp. */
	private static function utc( string $value ): int {
		if ( $value === '' || $value === '0000-00-00 00:00:00' ) {
			return 0;
		}

		try {
			return ( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}
}
