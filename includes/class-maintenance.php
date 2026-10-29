<?php
/**
 * Data retention (daily cron) and "delete all data".
 *
 * Retention deletes raw rows in chunks, so a large table never locks the database for long.
 * The daily summaries are kept, because they are small and they are the history.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Maintenance {

	const HOOK       = 'snn_a_daily';
	const CHUNK      = 5000;
	const TIME_LIMIT = 25; // seconds per run, so the cron job stays short on shared hosts

	/** Raw tables and the column that dates each row. */
	const RAW = [
		'views'       => 'created_at',
		'events'      => 'created_at',
		'conversions' => 'created_at',
		'sessions'    => 'last_seen',
	];

	public static function retention(): void {
		$settings = Settings::get();
		$stop     = time() + self::TIME_LIMIT;

		// Keeps the stored UTC offset current after DST changes, so day numbers match the summaries.
		Settings::refresh_offset();

		// One size reading per day, so the Settings screen can show how the data grows.
		self::db_stats();

		$months = (int) $settings['retention_months'];
		if ( $months > 0 ) {
			$cutoff = time() - (int) round( $months * 30.44 * 86400 );

			foreach ( self::RAW as $table => $column ) {
				self::delete_older_than( DB::table( $table ), $column, $cutoff, $stop );
			}
		}

		// Cursor trails have their own retention, from 14 days to 10 years.
		$days = max( 1, (int) $settings['cursor_retention_days'] );
		self::delete_older_than( DB::table( 'trails' ), 'created_at', time() - $days * 86400, $stop );

		// Activity whose page view never arrived (see Tracker::park).
		self::delete_older_than( DB::table( 'pending' ), 'at', time() - Tracker::PARK_FOR, $stop );
	}

	private static function delete_older_than( string $table, string $column, int $cutoff, int $stop ): void {
		global $wpdb;

		do {
			$deleted = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE {$column} < %d LIMIT " . self::CHUNK,
				$cutoff
			) );
		} while ( $deleted === self::CHUNK && time() < $stop );
	}

	/**
	 * Empties every statistic table. Goals, funnels and settings stay, so tracking keeps
	 * working the way it was set up.
	 */
	public static function delete_all_data(): void {
		global $wpdb;

		$tables = [
			'views', 'events', 'event_targets', 'sessions', 'visitors', 'conversions',
			'pages', 'referrers', 'campaigns', 'utm_values', 'countries', 'cities', 'ua',
			'heat', 'trails',
			'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . DB::table( $table ) );
		}

		// A finished import would otherwise refuse to run again, and its maps are gone.
		delete_option( Importer::OPTION );
		delete_option( Burst_Importer::OPTION );

		self::clear_visitor_counts();
	}

	/**
	 * Removes one visitor and everything they did: sessions, views, events, conversions and cursor trails.
	 * The daily totals of the days they were active are rebuilt, so the dashboard stops counting them.
	 *
	 * Heatmap clicks stay: they are anonymous per-cell counters with no link to a visitor.
	 *
	 * @return array{sessions:int, views:int}
	 */
	public static function delete_visitor( int $visitor_id ): array {
		global $wpdb;

		$t        = static fn( string $name ): string => DB::table( $name );
		$sessions = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, started_at, last_seen FROM ' . $t( 'sessions' ) . ' WHERE visitor_id = %d',
			$visitor_id
		), ARRAY_A );

		$ids  = array_map( static fn( $row ) => (int) $row['id'], (array) $sessions );
		$days = [];
		foreach ( (array) $sessions as $row ) {
			for ( $day = Ranges::day_of( (int) $row['started_at'] ), $last = Ranges::day_of( (int) $row['last_seen'] ); $day <= $last && count( $days ) < 400; $day++ ) {
				$days[ $day ] = true;
			}
		}

		$view_count = 0;
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$list  = implode( ',', $chunk );
			$views = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . $t( 'views' ) . " WHERE session_id IN ({$list})" ) );
			foreach ( array_chunk( $views, 500 ) as $view_chunk ) {
				$wpdb->query( 'DELETE FROM ' . $t( 'trails' ) . ' WHERE view_id IN (' . implode( ',', $view_chunk ) . ')' );
			}
			$view_count += count( $views );

			foreach ( [ 'events', 'conversions', 'views' ] as $table ) {
				$wpdb->query( 'DELETE FROM ' . $t( $table ) . " WHERE session_id IN ({$list})" );
			}
			$wpdb->query( 'DELETE FROM ' . $t( 'sessions' ) . " WHERE id IN ({$list})" );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $t( 'visitors' ) . ' WHERE id = %d', $visitor_id ) );

		// Days older than the retention window have lost part of their raw rows already, so rebuilding them
		// from what is left would shrink their totals. Those days keep their numbers.
		$months = (int) Settings::get()['retention_months'];
		$cutoff = $months > 0 ? time() - (int) round( $months * 30.44 * 86400 ) : 0;
		foreach ( array_keys( $days ) as $day ) {
			if ( Ranges::day_start( $day ) >= $cutoff ) {
				Rollup::refresh_day( $day );
			}
		}

		self::clear_visitor_counts();

		return [ 'sessions' => count( $ids ), 'views' => $view_count ];
	}

	/** Cached unique-visitor counts would still include a deleted visitor. */
	private static function clear_visitor_counts(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_snn_a_uv_' ) . '%'
		) );
	}

	// ------------------------------------------------------ database statistics

	const HISTORY      = 'snn_a_db_history';
	const HISTORY_DAYS = 180;

	/** Every plugin table with the group it is shown under on the Settings screen. */
	const TABLE_GROUPS = [
		'Raw visits'    => [ 'views', 'events', 'event_targets', 'sessions', 'visitors', 'conversions', 'heat', 'trails', 'pending' ],
		'Lookups'       => [ 'pages', 'referrers', 'campaigns', 'utm_values', 'countries', 'cities', 'ua' ],
		'Daily totals'  => [ 'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals' ],
		'Configuration' => [ 'goals', 'funnels', 'funnel_steps' ],
	];

	/**
	 * Size and row count of every table, plus the saved daily history. Taking the numbers also saves
	 * today's snapshot, so the history fills in even when the daily job has not run yet.
	 *
	 * Sizes come from information_schema and are null where the database cannot report them (SQLite).
	 */
	public static function db_stats(): array {
		global $wpdb;

		$names = [];
		foreach ( self::TABLE_GROUPS as $group => $tables ) {
			foreach ( $tables as $table ) {
				$names[ DB::table( $table ) ] = [ $group, $table ];
			}
		}

		$info   = [];
		$marks  = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$hidden = $wpdb->suppress_errors( true );
		$found  = $wpdb->get_results( $wpdb->prepare(
			"SELECT table_name AS name, data_length AS data_bytes, index_length AS index_bytes, table_rows AS estimate
			 FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name IN ({$marks})",
			...array_keys( $names )
		), ARRAY_A );
		$wpdb->suppress_errors( $hidden );

		foreach ( (array) $found as $row ) {
			$info[ (string) $row['name'] ] = $row;
		}

		$tables      = [];
		$total_bytes = 0;
		$total_rows  = 0;
		$sized       = $info !== [];

		foreach ( $names as $full => [ $group, $short ] ) {
			$est = isset( $info[ $full ] ) ? (int) $info[ $full ]['estimate'] : null;

			// information_schema row counts are estimates. Exact counts are cheap until a table is large.
			if ( $est === null || $est < 500000 ) {
				$hidden = $wpdb->suppress_errors( true );
				$rows   = $wpdb->get_var( "SELECT COUNT(*) FROM {$full}" );
				$wpdb->suppress_errors( $hidden );
				if ( $rows === null ) {
					continue; // table missing
				}
				$rows   = (int) $rows;
				$approx = false;
			} else {
				$rows   = $est;
				$approx = true;
			}

			$data  = $sized && isset( $info[ $full ] ) ? (int) $info[ $full ]['data_bytes'] : null;
			$index = $sized && isset( $info[ $full ] ) ? (int) $info[ $full ]['index_bytes'] : null;

			$total_bytes += (int) $data + (int) $index;
			$total_rows  += $rows;

			$tables[] = [
				'name'   => $short,
				'group'  => $group,
				'rows'   => $rows,
				'approx' => $approx,
				'data'   => $data,
				'index'  => $index,
				'bytes'  => $data === null ? null : $data + (int) $index,
			];
		}

		// SQLite answers the size query with zeros. That is "not reported", not an empty database.
		if ( $total_bytes === 0 ) {
			$sized  = false;
			$tables = array_map( static fn( $t ) => [ 'data' => null, 'index' => null, 'bytes' => null ] + $t, $tables );
		}

		$history = self::snapshot( $sized ? $total_bytes : null, $total_rows );

		$views = DB::table( 'views' );
		$first = $wpdb->get_var( "SELECT MIN(created_at) FROM {$views}" );

		return [
			'tables'      => $tables,
			'total_bytes' => $sized ? $total_bytes : null,
			'total_rows'  => $total_rows,
			'oldest_view' => $first ? (int) $first : null,
			'history'     => $history,
		];
	}

	/** Saves today's totals into the history and returns it, oldest first. Only the latest reading of a day is kept. */
	public static function snapshot( ?int $bytes = null, ?int $rows = null ): array {
		$history = Store::read( self::HISTORY );
		$today   = Ranges::day_of( time() );

		if ( $rows !== null ) {
			$history[ $today ] = [ 'bytes' => $bytes, 'rows' => $rows ];
		}

		krsort( $history );
		$history = array_slice( $history, 0, self::HISTORY_DAYS, true );
		ksort( $history );

		if ( $rows !== null ) {
			Store::write( self::HISTORY, $history, false );
		}

		$out = [];
		foreach ( $history as $day => $entry ) {
			$out[] = [
				'date'  => gmdate( 'Y-m-d', (int) $day * 86400 ),
				'bytes' => isset( $entry['bytes'] ) ? (int) $entry['bytes'] : null,
				'rows'  => (int) ( $entry['rows'] ?? 0 ),
			];
		}

		return $out;
	}

	/** Removes everything on uninstall when the admin asked for it. */
	public static function uninstall(): void {
		global $wpdb;

		foreach ( [ 'views', 'events', 'event_targets', 'sessions', 'visitors', 'conversions', 'pages', 'referrers', 'campaigns', 'utm_values', 'countries', 'cities', 'ua', 'heat', 'trails', 'goals', 'funnels', 'funnel_steps', 'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals', 'debug_log', 'pending' ] as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . DB::table( $table ) );
		}

		foreach ( [ Settings::SETTINGS, Settings::SECRETS, DB::OPTION, Geo::STATUS, 'snn_a_import', Burst_Importer::OPTION, self::HISTORY ] as $option ) {
			delete_option( $option );
		}

		foreach ( [ 'snn_a_dashboard_layout', 'snn_a_marks', 'snn_a_views' ] as $meta ) {
			delete_metadata( 'user', 0, $meta, '', true );
		}
	}
}
