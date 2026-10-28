<?php
/**
 * Small regex classifier for device, browser and OS. Runs once per new session.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class UA {

	const DESKTOP = 1;
	const MOBILE  = 2;
	const TABLET  = 3;

	/**
	 * @return array{device:int, browser:string, os:string}
	 */
	public static function classify( string $ua ): array {
		return [
			'device'  => self::device( $ua ),
			'browser' => self::browser( $ua ),
			'os'      => self::os( $ua ),
		];
	}

	private static function device( string $ua ): int {
		$android_tablet = stripos( $ua, 'android' ) !== false && stripos( $ua, 'mobile' ) === false;

		if ( $android_tablet || preg_match( '/ipad|tablet|kindle|silk|playbook/i', $ua ) ) {
			return self::TABLET;
		}

		return preg_match( '/mobi|iphone|ipod|android|blackberry|opera mini|windows phone/i', $ua )
			? self::MOBILE
			: self::DESKTOP;
	}

	private static function browser( string $ua ): string {
		return match ( true ) {
			(bool) preg_match( '/edg(e|a|ios)?\//i', $ua )  => 'Edge',
			(bool) preg_match( '/opr\/|opera/i', $ua )       => 'Opera',
			(bool) preg_match( '/samsungbrowser/i', $ua )    => 'Samsung Internet',
			(bool) preg_match( '/firefox|fxios/i', $ua )     => 'Firefox',
			(bool) preg_match( '/chrome|crios|chromium/i', $ua ) => 'Chrome',
			(bool) preg_match( '/safari/i', $ua )            => 'Safari',
			(bool) preg_match( '/msie|trident/i', $ua )      => 'Internet Explorer',
			default                                          => 'Other',
		};
	}

	private static function os( string $ua ): string {
		// iPadOS and Android both contain other OS names, so they are checked first.
		return match ( true ) {
			(bool) preg_match( '/iphone|ipad|ipod/i', $ua )       => 'iOS',
			(bool) preg_match( '/android/i', $ua )                 => 'Android',
			(bool) preg_match( '/windows/i', $ua )                 => 'Windows',
			(bool) preg_match( '/mac os x|macintosh/i', $ua )      => 'macOS',
			(bool) preg_match( '/cros/i', $ua )                    => 'ChromeOS',
			(bool) preg_match( '/linux/i', $ua )                   => 'Linux',
			default                                                => 'Other',
		};
	}
}
