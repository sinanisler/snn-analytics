<?php
/**
 * Builds the daily summary tables from the raw facts.
 *
 * Every chart and table reads these summaries, so a 90-day chart reads about 90 rows
 * however many raw views there are. A day is rebuilt inside one transaction, which means
 * readers never see half a day. Rebuilding is idempotent.
 *
 * Definitions:
 *  - Session metrics (sessions, visitors, bounces, entrances, exits) count sessions that
 *    started on the day.
 *  - View metrics (views, page visitors, time on page) count views created on the day.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Rollup {

	const HOOK = 'snn_a_rollup';
	const MARK = 'snn_a_rolled_at'; // option holding the time of the last rebuild

	/** Cron: rebuild today and yesterday. Older days are final. */
	public static function refresh_recent(): void {
		$today = Ranges::day_of( time() );
		self::refresh_range( $today - 1, $today );
	}

	public static function refresh_range( int $from, int $to ): void {
		for ( $day = $from; $day <= $to; $day++ ) {
			self::refresh_day( $day );
		}
	}

	/** Earliest day with any session, or null when there is no data. */
	public static function first_day(): ?int {
		global $wpdb;

		$min = (int) $wpdb->get_var( 'SELECT MIN(started_at) FROM ' . DB::table( 'sessions' ) );

		return $min > 0 ? Ranges::day_of( $min ) : null;
	}

	public static function refresh_day( int $day ): void {
		global $wpdb;

		$start = Ranges::day_start( $day );
		$end   = $start + 86400;

		$wpdb->query( 'START TRANSACTION' );

		self::site( $day, $start, $end );
		self::pages( $day, $start, $end );
		self::referrers( $day, $start, $end );
		self::geo( $day, $start, $end );
		self::devices( $day, $start, $end );
		self::campaigns( $day, $start, $end );
		self::goals( $day, $start, $end );

		$wpdb->query( 'COMMIT' );
		update_option( self::MARK, time(), false );
	}

	private static function site( int $day, int $s, int $e ): void {
		global $wpdb;

		$sessions = DB::table( 'sessions' );
		$views    = DB::table( 'views' );
		$events   = DB::table( 'events' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM " . DB::table( 'd_site' ) . " WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO " . DB::table( 'd_site' ) . " (day, sessions, visitors, new_visitors, bounces, duration_sum)
			 SELECT %d, COUNT(*), COUNT(DISTINCT visitor_id), COALESCE(SUM(is_new), 0), COALESCE(SUM(is_bounce), 0), COALESCE(SUM(duration), 0)
			 FROM {$sessions} WHERE started_at >= %d AND started_at < %d",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . DB::table( 'd_site' ) . " SET
			   views  = (SELECT COUNT(*) FROM {$views} WHERE created_at >= %d AND created_at < %d),
			   events = (SELECT COUNT(*) FROM {$events} WHERE created_at >= %d AND created_at < %d)
			 WHERE day = %d",
			$s, $e, $s, $e, $day
		) );
	}

	private static function pages( int $day, int $s, int $e ): void {
		global $wpdb;

		$views    = DB::table( 'views' );
		$sessions = DB::table( 'sessions' );
		$target   = DB::table( 'd_pages' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, page_id, views, visitors, duration_sum)
			 SELECT %d, v.page_id, COUNT(*), COUNT(DISTINCT s.visitor_id), COALESCE(SUM(v.duration), 0)
			 FROM {$views} v JOIN {$sessions} s ON s.id = v.session_id
			 WHERE v.created_at >= %d AND v.created_at < %d
			 GROUP BY v.page_id",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, page_id, entrances)
			 SELECT %d, entry_page_id, COUNT(*) FROM {$sessions}
			 WHERE started_at >= %d AND started_at < %d GROUP BY entry_page_id
			 ON DUPLICATE KEY UPDATE entrances = VALUES(entrances)",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, page_id, exits)
			 SELECT %d, exit_page_id, COUNT(*) FROM {$sessions}
			 WHERE started_at >= %d AND started_at < %d GROUP BY exit_page_id
			 ON DUPLICATE KEY UPDATE exits = VALUES(exits)",
			$day, $s, $e
		) );
	}

	private static function referrers( int $day, int $s, int $e ): void {
		global $wpdb;

		$views    = DB::table( 'views' );
		$sessions = DB::table( 'sessions' );
		$target   = DB::table( 'd_refs' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, referrer_id, sessions, visitors, bounces)
			 SELECT %d, COALESCE(referrer_id, 0), COUNT(*), COUNT(DISTINCT visitor_id), COALESCE(SUM(is_bounce), 0)
			 FROM {$sessions} WHERE started_at >= %d AND started_at < %d
			 GROUP BY COALESCE(referrer_id, 0)",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, referrer_id, views)
			 SELECT %d, COALESCE(s.referrer_id, 0), COUNT(*)
			 FROM {$views} v JOIN {$sessions} s ON s.id = v.session_id
			 WHERE v.created_at >= %d AND v.created_at < %d
			 GROUP BY COALESCE(s.referrer_id, 0)
			 ON DUPLICATE KEY UPDATE views = VALUES(views)",
			$day, $s, $e
		) );
	}

	private static function geo( int $day, int $s, int $e ): void {
		global $wpdb;

		$views    = DB::table( 'views' );
		$sessions = DB::table( 'sessions' );
		$target   = DB::table( 'd_geo' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, country_id, city_id, sessions, visitors)
			 SELECT %d, COALESCE(country_id, 0), COALESCE(city_id, 0), COUNT(*), COUNT(DISTINCT visitor_id)
			 FROM {$sessions} WHERE started_at >= %d AND started_at < %d
			 GROUP BY COALESCE(country_id, 0), COALESCE(city_id, 0)",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, country_id, city_id, views)
			 SELECT %d, COALESCE(s.country_id, 0), COALESCE(s.city_id, 0), COUNT(*)
			 FROM {$views} v JOIN {$sessions} s ON s.id = v.session_id
			 WHERE v.created_at >= %d AND v.created_at < %d
			 GROUP BY COALESCE(s.country_id, 0), COALESCE(s.city_id, 0)
			 ON DUPLICATE KEY UPDATE views = VALUES(views)",
			$day, $s, $e
		) );
	}

	private static function devices( int $day, int $s, int $e ): void {
		global $wpdb;

		$sessions = DB::table( 'sessions' );
		$target   = DB::table( 'd_devices' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, device, browser_id, os_id, sessions, visitors)
			 SELECT %d, device, COALESCE(browser_id, 0), COALESCE(os_id, 0), COUNT(*), COUNT(DISTINCT visitor_id)
			 FROM {$sessions} WHERE started_at >= %d AND started_at < %d
			 GROUP BY device, COALESCE(browser_id, 0), COALESCE(os_id, 0)",
			$day, $s, $e
		) );
	}

	private static function campaigns( int $day, int $s, int $e ): void {
		global $wpdb;

		$views       = DB::table( 'views' );
		$sessions    = DB::table( 'sessions' );
		$conversions = DB::table( 'conversions' );
		$target      = DB::table( 'd_camps' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, campaign_id, sessions, visitors)
			 SELECT %d, COALESCE(campaign_id, 0), COUNT(*), COUNT(DISTINCT visitor_id)
			 FROM {$sessions} WHERE started_at >= %d AND started_at < %d
			 GROUP BY COALESCE(campaign_id, 0)",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, campaign_id, views)
			 SELECT %d, COALESCE(s.campaign_id, 0), COUNT(*)
			 FROM {$views} v JOIN {$sessions} s ON s.id = v.session_id
			 WHERE v.created_at >= %d AND v.created_at < %d
			 GROUP BY COALESCE(s.campaign_id, 0)
			 ON DUPLICATE KEY UPDATE views = VALUES(views)",
			$day, $s, $e
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, campaign_id, conversions)
			 SELECT %d, COALESCE(s.campaign_id, 0), COUNT(*)
			 FROM {$conversions} c JOIN {$sessions} s ON s.id = c.session_id
			 WHERE c.created_at >= %d AND c.created_at < %d
			 GROUP BY COALESCE(s.campaign_id, 0)
			 ON DUPLICATE KEY UPDATE conversions = VALUES(conversions)",
			$day, $s, $e
		) );
	}

	private static function goals( int $day, int $s, int $e ): void {
		global $wpdb;

		$conversions = DB::table( 'conversions' );
		$target      = DB::table( 'd_goals' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$target} WHERE day = %d", $day ) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$target} (day, goal_id, conversions)
			 SELECT %d, goal_id, COUNT(*) FROM {$conversions}
			 WHERE created_at >= %d AND created_at < %d GROUP BY goal_id",
			$day, $s, $e
		) );
	}
}
