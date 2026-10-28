<?php
/**
 * Validates every field of a tracking hit. Treat all input as hostile:
 * types are checked, strings are cleaned and capped, unknown keys are dropped.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Validator {

	/** Page keys the server prints into each page: s:ID, t:ID, a:ID, pta:type, or a fixed word. */
	private const PAGE_KEY = '/^(?:[sta]:\d{1,12}|pta:[a-z0-9_-]{1,20}|home|blog|404|search|date|other)$/';

	/** Custom event names: letters, digits, space, dot, colon, dash, underscore. */
	private const EVENT_NAME = '/^[\p{L}\p{N} _.:\-]{1,64}$/u';

	/** Link fragments that are kept: plain anchor ids, with no "=", "&", "/" or "." that tokens need. */
	private const ANCHOR = '/^[A-Za-z0-9_\-:]{1,64}$/';

	/**
	 * Returns a clean hit, or null when anything is wrong.
	 */
	public static function hit( array $in ): ?array {
		$type = $in['e'] ?? null;
		$vk   = $in['vk'] ?? null;

		if ( ! is_string( $vk ) || ! preg_match( '/^[0-9a-f]{16}$/', $vk ) ) {
			return null;
		}

		switch ( $type ) {
			case 'pv':
				$page = self::page( $in['pg'] ?? null );
				if ( $page === null ) {
					return null;
				}
				return [
					'e'    => 'pv',
					'vk'   => $vk,
					'page' => $page,
					'ref'  => self::text( $in['r'] ?? null, 2048 ),
					'q'    => self::text( $in['q'] ?? null, 2048 ),
					// Optional device facts. A bad value becomes null; it never rejects the view.
					'sw'   => self::int( $in['sw'] ?? null, 1, 20000 ),
					'sh'   => self::int( $in['sh'] ?? null, 1, 20000 ),
					'vw'   => self::int( $in['vw'] ?? null, 1, 20000 ),
					'vh'   => self::int( $in['vh'] ?? null, 1, 20000 ),
					'lang' => self::language( $in['l'] ?? null ),
				];

			case 'ping':
				$active = self::int( $in['d'] ?? null, 0, PHP_INT_MAX );
				if ( $active === null ) {
					return null;
				}
				return [ 'e' => 'ping', 'vk' => $vk, 'd' => min( $active, 14400 ) ];

			case 'heat':
				// Click position: 50 columns of 2 percent of the page width, and 40 px rows.
				$x = self::int( $in['x'] ?? null, 0, 49 );
				$y = self::int( $in['y'] ?? null, 0, 1999 );
				if ( $x === null || $y === null ) {
					return null;
				}
				return [ 'e' => 'heat', 'vk' => $vk, 'x' => $x, 'y' => $y ];

			case 'trail':
				// Hex of 6-byte samples: x (per 10000 of page width), y (px), t (ms). 1 to 240 samples.
				$samples = self::text( $in['s'] ?? null, 2880 );
				if ( ! preg_match( '/^(?:[0-9a-f]{12}){1,240}$/', $samples ) ) {
					return null;
				}
				return [ 'e' => 'trail', 'vk' => $vk, 's' => $samples ];

			case 'ev':
				$kind = self::int( $in['t'] ?? null, 1, 11 );
				if ( $kind === null ) {
					return null;
				}
				$value = match ( $kind ) {
					3       => 'mailto',
					4       => 'tel',
					5       => self::name( $in['v'] ?? null ),
					6       => self::goal_id( $in['v'] ?? null ),
					8, 9, 10 => self::label( $in['v'] ?? null, 120 ),
					11       => self::scroll_mark( $in['v'] ?? null ),
					default  => self::url( $in['v'] ?? null ),
				};
				if ( $value === null ) {
					return null;
				}
				return [ 'e' => 'ev', 'vk' => $vk, 't' => $kind, 'v' => $value ];
		}

		return null;
	}

	/**
	 * Cleans a string: removes control characters, caps the length in characters, trims.
	 * Non-strings become an empty string.
	 */
	public static function text( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$clean = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );

		return trim( mb_substr( (string) $clean, 0, $max ) );
	}

	/**
	 * HMAC over the page identity fields. The browser echoes them back, so a bot
	 * cannot invent a page: the signature only exists for pages this site rendered.
	 */
	public static function page_signature( string $key, string $path, string $title, string $post_type ): string {
		$parts = [
			self::text( $key, 32 ),
			self::text( $path, 512 ),
			self::text( $title, 255 ),
			self::text( $post_type, 32 ),
		];

		return substr( hash_hmac( 'sha256', (string) json_encode( $parts, JSON_INVALID_UTF8_SUBSTITUTE ), Settings::secrets()['hmac'] ), 0, 32 );
	}

	private static function page( mixed $pg ): ?array {
		if ( ! is_array( $pg ) ) {
			return null;
		}

		$key       = self::text( $pg['k'] ?? null, 32 );
		$path      = self::text( $pg['p'] ?? null, 512 );
		$title     = self::text( $pg['t'] ?? null, 255 );
		$post_type = self::text( $pg['y'] ?? null, 32 );
		$signature = self::text( $pg['s'] ?? null, 32 );

		if ( ! preg_match( self::PAGE_KEY, $key ) || ! str_starts_with( $path, '/' ) ) {
			return null;
		}

		$expected = self::page_signature( $key, $path, $title, $post_type );
		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		return [
			'key'       => $key,
			'type'      => self::page_type( $key ),
			'object_id' => preg_match( '/^[sta]:(\d+)$/', $key, $m ) ? (int) $m[1] : 0,
			'path'      => $path,
			'title'     => $title,
			'post_type' => $post_type,
		];
	}

	private static function page_type( string $key ): int {
		return match ( true ) {
			str_starts_with( $key, 's:' )   => 1, // singular
			$key === 'home'                  => 2,
			str_starts_with( $key, 't:' )   => 3, // term
			str_starts_with( $key, 'a:' )   => 4, // author
			$key === 'date'                  => 5,
			$key === 'search'                => 6,
			$key === '404'                   => 7,
			str_starts_with( $key, 'pta:' ) => 8, // post type archive
			default                          => 9, // blog, other
		};
	}

	private static function url( mixed $value ): ?string {
		$parts = parse_url( self::text( $value, 2048 ) );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( $parts['scheme'] );
		if ( $scheme !== 'http' && $scheme !== 'https' ) {
			return null;
		}

		// Query strings are dropped: they often carry tokens or personal data.
		// A fragment is kept only when it reads like a plain anchor id, so "#access_token=..." never gets through.
		$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$url      = $scheme . '://' . strtolower( $parts['host'] ) . $port . ( $parts['path'] ?? '/' );
		$fragment = (string) ( $parts['fragment'] ?? '' );
		if ( preg_match( self::ANCHOR, $fragment ) ) {
			$url .= '#' . $fragment;
		}

		return mb_substr( $url, 0, 512 );
	}

	/** Visible text from the page (button labels, form names, headings). Empty values are rejected. */
	private static function label( mixed $value, int $max ): ?string {
		$text = self::text( $value, $max );

		return $text === '' ? null : $text;
	}

	/** Scroll depth accepts only the four marks, so the value cannot be used to fill the table. */
	private static function scroll_mark( mixed $value ): ?string {
		$mark = self::text( $value, 4 );

		return in_array( $mark, [ '25%', '50%', '75%', '100%' ], true ) ? $mark : null;
	}

	/** Goal clicks carry the goal id, stored as "goal:ID" so the timeline can show it. */
	private static function goal_id( mixed $value ): ?string {
		$id = self::text( $value, 8 );

		return preg_match( '/^[1-9][0-9]{0,5}$/', $id ) ? 'goal:' . $id : null;
	}

	/** A browser language tag such as "en-US" or "zh-Hant-TW". Anything else is dropped. */
	private static function language( mixed $value ): ?string {
		$tag = self::text( $value, 12 );

		return preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,4}){0,2}$/', $tag ) ? $tag : null;
	}

	private static function name( mixed $value ): ?string {
		$name = self::text( $value, 64 );

		return preg_match( self::EVENT_NAME, $name ) ? $name : null;
	}

	private static function int( mixed $value, int $min, int $max ): ?int {
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		$int = (int) $value;

		return $int < $min || $int > $max ? null : $int;
	}
}
