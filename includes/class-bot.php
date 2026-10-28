<?php
/**
 * Bot and prefetch filter for the tracking endpoint.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Bot {

	private const PATTERN = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|headless|phantomjs|lighthouse|pingdom|gtmetrix|uptime|monitor|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|httpclient|libwww|ahrefs|semrush|preview/i';

	/**
	 * An empty user agent counts as a bot. Prefetch and prerender requests are not visits.
	 */
	public static function is_bot( string $ua, array $server ): bool {
		if ( $ua === '' || preg_match( self::PATTERN, $ua ) ) {
			return true;
		}

		$purpose = strtolower( Validator::text( $server['HTTP_SEC_PURPOSE'] ?? null, 64 ) . Validator::text( $server['HTTP_PURPOSE'] ?? null, 64 ) );

		return str_contains( $purpose, 'prefetch' ) || str_contains( $purpose, 'prerender' );
	}
}
