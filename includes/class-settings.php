<?php
/**
 * Plugin settings and the secrets used for visitor hashing and page signing.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const SETTINGS = 'snn_a_settings';
	const SECRETS  = 'snn_a_secrets';

	private static ?array $settings = null;
	private static ?array $secrets  = null;

	public static function defaults(): array {
		return [
			'salt_rotation'   => 'yearly', // never | yearly | monthly | daily
			'respect_dnt'     => false,    // honour DNT and Sec-GPC
			'exclude_roles'   => [ 'administrator' ],
			'session_timeout' => 1800,     // seconds without a hit before a new session starts
			'site_host'       => '',       // host without "www.", used for internal referrers
			'retention_months' => 12,       // raw rows older than this are deleted; 0 keeps everything
			'goal_rules'      => [],       // active goals, copied here so the tracker can check them without a query
			'geo_city_file'   => '',       // absolute path of the installed city database, if any
			'delete_on_uninstall' => false,  // drop all tables and data when the plugin is deleted
			'heatmap'             => true,   // record click positions as per-page counters
			'cursor_trails'       => false,  // record pointer paths, one trail per view (off by default)
			'cursor_retention_days' => 30,   // trails older than this are deleted: 14, 30, 365 or 3650
			'tz_offset'           => 0,      // site UTC offset in seconds, kept current so the tracker can number days
			'debug_until'         => 0,      // unix time debug mode switches itself off; 0 is off
			'role_access'         => [],     // feature => roles allowed to see it (administrators always can)
		];
	}

	public static function get(): array {
		return self::$settings ??= array_merge( self::defaults(), Store::read( self::SETTINGS ) );
	}

	public static function update( array $values ): void {
		Store::write( self::SETTINGS, array_merge( Store::read( self::SETTINGS ), $values ), false );
		self::$settings = null;
	}

	/**
	 * Stores the site's current UTC offset. The tracker numbers days with it, so it must match
	 * the offset the daily summaries use. Refreshed on activation, on timezone changes and daily.
	 */
	public static function refresh_offset(): void {
		self::update( [ 'tz_offset' => Ranges::offset() ] );
	}

	/**
	 * Salt and HMAC key. Created on first use, never shown in the UI, never autoloaded.
	 */
	public static function secrets(): array {
		if ( self::$secrets !== null ) {
			return self::$secrets;
		}

		$secrets = Store::read( self::SECRETS );
		if ( empty( $secrets['salt'] ) || empty( $secrets['hmac'] ) ) {
			$secrets = [
				'salt'    => bin2hex( random_bytes( 32 ) ),
				'salt_at' => time(),
				'hmac'    => bin2hex( random_bytes( 32 ) ),
			];
			Store::write( self::SECRETS, $secrets, false );
		}

		return self::$secrets = $secrets;
	}

	/**
	 * Current visitor salt. Rotates when the configured period has passed.
	 * "never" keeps one salt forever, so journeys stay linked across days.
	 */
	public static function salt(): string {
		$secrets = self::secrets();
		$period  = self::rotation_period( (string) self::get()['salt_rotation'] );

		if ( $period > 0 && time() - (int) $secrets['salt_at'] >= $period ) {
			$secrets = [
				'salt'    => bin2hex( random_bytes( 32 ) ),
				'salt_at' => time(),
				'hmac'    => $secrets['hmac'],
			];
			Store::write( self::SECRETS, $secrets, false );
			self::$secrets = $secrets;
		}

		return $secrets['salt'];
	}

	public static function host_of( string $url ): string {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

		return (string) preg_replace( '/^www\./', '', $host );
	}

	private static function rotation_period( string $policy ): int {
		return match ( $policy ) {
			'daily'   => 86400,
			'monthly' => 2592000,
			'yearly'  => 31536000,
			default   => 0,
		};
	}
}
