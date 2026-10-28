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
		$months = (int) Settings::get()['retention_months'];
		if ( $months <= 0 ) {
			return;
		}

		$cutoff = time() - (int) round( $months * 30.44 * 86400 );
		$stop   = time() + self::TIME_LIMIT;

		foreach ( self::RAW as $table => $column ) {
			self::delete_older_than( DB::table( $table ), $column, $cutoff, $stop );
		}
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
			'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . DB::table( $table ) );
		}

		// A finished import would otherwise refuse to run again, and its maps are gone.
		delete_option( Importer::OPTION );

		// Cached unique-visitor counts would describe data that no longer exists.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_snn_a_uv_' ) . '%'
		) );
	}

	/** Removes everything on uninstall when the admin asked for it. */
	public static function uninstall(): void {
		global $wpdb;

		foreach ( [ 'views', 'events', 'event_targets', 'sessions', 'visitors', 'conversions', 'pages', 'referrers', 'campaigns', 'utm_values', 'countries', 'cities', 'ua', 'goals', 'funnels', 'funnel_steps', 'd_site', 'd_pages', 'd_refs', 'd_geo', 'd_devices', 'd_camps', 'd_goals' ] as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . DB::table( $table ) );
		}

		foreach ( [ Settings::SETTINGS, Settings::SECRETS, DB::OPTION, Geo::STATUS, 'snn_a_import' ] as $option ) {
			delete_option( $option );
		}

		delete_metadata( 'user', 0, 'snn_a_dashboard_layout', '', true );
	}
}
