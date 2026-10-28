<?php
/**
 * Country detection. Step 1 reads CDN headers only. The MMDB reader for DB-IP
 * (country and city) is added in the geo step.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Geo {

	/** Headers set by common CDNs and proxies, in priority order. */
	private const HEADERS = [
		'HTTP_CF_IPCOUNTRY',               // Cloudflare
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY',  // AWS CloudFront
		'HTTP_X_COUNTRY_CODE',
		'HTTP_X_GEOIP_COUNTRY_CODE',
	];

	public static function country_id( array $server ): ?int {
		$code = self::country_code( $server );

		return $code === null ? null : DB::upsert( DB::table( 'countries' ), [ 'code' => $code ] );
	}

	public static function country_code( array $server ): ?string {
		foreach ( self::HEADERS as $header ) {
			$code = strtoupper( Validator::text( $server[ $header ] ?? null, 8 ) );

			// XX and T1 are Cloudflare's codes for "unknown" and "Tor".
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && ! in_array( $code, [ 'XX', 'T1' ], true ) ) {
				return $code;
			}
		}

		return null;
	}
}
