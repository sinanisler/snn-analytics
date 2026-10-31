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
	const PROJECT_DAYS = 365;

	/** Every plugin table with the group it is shown under on the Settings screen. */
	const TABLE_GROUPS = [
		'Raw visits'    => [ 'views', 'events', 'event_targets', 'sessions', 'visitors', 'conversions', 'heat', 'trails', 'pending' ],
		'Lookups'       => [ 'pages', 'referrers', 'campaigns', 'utm_values', 'countries', 'cities', 'ua' ],
		'Daily totals'  => [ 'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals' ],
		'Configuration' => [ 'goals', 'funnels', 'funnel_steps' ],
		'Debug log'     => [ 'debug_log' ],
	];

	/**
	 * Size and row count of every table, plus the saved daily history. Taking the numbers also saves
	 * today's snapshot, so the history fills in even when the daily job has not run yet.
	 *
	 * Sizes come from information_schema and are null where the database cannot report them (SQLite).
	 * Growth and the projection are worked out here too, so the Settings screen only shows them.
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

		// Bytes by how the table ages, which decides how it grows. See project().
		$by_age = [ 'raw' => 0, 'trails' => 0, 'capped' => 0, 'growing' => 0 ];

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

			$bytes        = (int) $data + (int) $index;
			$total_bytes += $bytes;
			$total_rows  += $rows;

			// Raw rows and trails leave after their retention, and the debug log is capped by row count.
			// Everything else only grows.
			if ( isset( self::RAW[ $short ] ) ) {
				$by_age['raw'] += $bytes;
			} elseif ( $short === 'trails' ) {
				$by_age['trails'] += $bytes;
			} elseif ( $short === 'debug_log' ) {
				$by_age['capped'] += $bytes;
			} else {
				$by_age['growing'] += $bytes;
			}

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

		$oldest = [ 'raw' => null, 'trails' => null ];
		if ( $sized ) {
			foreach ( self::RAW as $table => $column ) {
				$min = $wpdb->get_var( "SELECT MIN({$column}) FROM " . DB::table( $table ) );
				if ( $min !== null ) {
					$oldest['raw'] = $oldest['raw'] === null ? (int) $min : min( $oldest['raw'], (int) $min );
				}
			}
			$min              = $wpdb->get_var( 'SELECT MIN(created_at) FROM ' . DB::table( 'trails' ) );
			$oldest['trails'] = $min === null ? null : (int) $min;
		}

		$history = self::snapshot( $sized ? $total_bytes : null, $total_rows, $sized ? $by_age['growing'] : null );

		// Growth uses every reading in the history, not only the first and the last.
		$bytes_points   = [];
		$rows_points    = [];
		$growing_points = [];
		foreach ( $history as $row ) {
			$rows_points[] = [ $row['day'], $row['rows'] ];
			if ( $row['bytes'] !== null ) {
				$bytes_points[] = [ $row['day'], $row['bytes'] ];
			}
			if ( $row['growing'] !== null ) {
				$growing_points[] = [ $row['day'], $row['growing'] ];
			}
		}

		$growth     = [ 'bytes_per_day' => null, 'rows_per_day' => self::slope( $rows_points ) ];
		$projection = null;

		if ( $sized ) {
			$settings                = Settings::get();
			$months                  = (int) $settings['retention_months'];
			$growth['bytes_per_day'] = self::slope( $bytes_points );
			$growing_rate            = self::slope( $growing_points );

			// The projection needs the growing tables' own rate, which the history only has from the second day.
			if ( $growing_rate !== null ) {
				$windows = [
					[
						'bytes'  => $by_age['raw'],
						'window' => $months > 0 ? $months * 30.44 : 0,
						'age'    => self::age_days( $oldest['raw'] ),
					],
					[
						'bytes'  => $by_age['trails'],
						'window' => max( 1, (int) $settings['cursor_retention_days'] ),
						'age'    => self::age_days( $oldest['trails'] ),
					],
				];

				$projection = [
					'days'  => self::PROJECT_DAYS,
					'bytes' => self::project( $by_age['growing'], $growing_rate, $by_age['capped'], $windows, self::PROJECT_DAYS ),
				];
			}
		}

		$views = DB::table( 'views' );
		$first = $wpdb->get_var( "SELECT MIN(created_at) FROM {$views}" );

		return [
			'tables'      => $tables,
			'total_bytes' => $sized ? $total_bytes : null,
			'total_rows'  => $total_rows,
			'oldest_view' => $first ? (int) $first : null,
			'history'     => $history,
			'growth'      => $growth,
			'projection'  => $projection,
		];
	}

	/** Saves today's totals into the history and returns it, oldest first. Only the latest reading of a day is kept. */
	public static function snapshot( ?int $bytes = null, ?int $rows = null, ?int $growing = null ): array {
		$history = Store::read( self::HISTORY );
		$today   = Ranges::day_of( time() );

		if ( $rows !== null ) {
			$history[ $today ] = [ 'bytes' => $bytes, 'rows' => $rows, 'growing' => $growing ];
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
				'day'     => (int) $day,
				'date'    => gmdate( 'Y-m-d', (int) $day * 86400 ),
				'bytes'   => isset( $entry['bytes'] ) ? (int) $entry['bytes'] : null,
				'rows'    => (int) ( $entry['rows'] ?? 0 ),
				'growing' => isset( $entry['growing'] ) ? (int) $entry['growing'] : null,
			];
		}

		return $out;
	}

	/**
	 * Least-squares slope of the points: the change per day across all of them. Points are [day, value].
	 * Null with fewer than two days.
	 */
	public static function slope( array $points ): ?float {
		$count = count( $points );
		if ( $count < 2 ) {
			return null;
		}

		$mean_x = array_sum( array_column( $points, 0 ) ) / $count;
		$mean_y = array_sum( array_column( $points, 1 ) ) / $count;
		$num    = 0.0;
		$den    = 0.0;

		foreach ( $points as [ $x, $y ] ) {
			$num += ( $x - $mean_x ) * ( $y - $mean_y );
			$den += ( $x - $mean_x ) ** 2;
		}

		return $den > 0 ? $num / $den : null;
	}

	/**
	 * Size in $days from what is measured now.
	 *
	 * Growing tables keep their measured rate. A windowed table keeps the average it has per day of history it
	 * holds, and stops adding once that history fills its window. Capped tables do not change.
	 * Under a day of history counts as a day, so a few hours of data cannot turn into a large daily rate.
	 *
	 * @param array<int, array{bytes: int, window: float, age: float}> $windows Current bytes, window in days (0 keeps
	 *                                                                           everything) and age of the oldest row in days.
	 */
	public static function project( int $growing, float $rate, int $capped, array $windows, int $days ): int {
		$total = $growing + $rate * $days + $capped;

		foreach ( $windows as $w ) {
			$held_now = max( 1.0, self::held( $w['age'], $w['window'] ) );
			$total   += $w['bytes'] * self::held( $w['age'] + $days, $w['window'] ) / $held_now;
		}

		return (int) max( 0, round( $total ) );
	}

	/** Days of history a window holds: its age, capped at the window. */
	private static function held( float $age, float $window ): float {
		return $window > 0 ? min( $age, $window ) : $age;
	}

	private static function age_days( ?int $oldest ): float {
		return $oldest === null ? 0.0 : max( 0.0, ( time() - $oldest ) / 86400 );
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
