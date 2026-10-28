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
				];

			case 'ping':
				$active = self::int( $in['d'] ?? null, 0, PHP_INT_MAX );
				if ( $active === null ) {
					return null;
				}
				return [ 'e' => 'ping', 'vk' => $vk, 'd' => min( $active, 14400 ) ];

			case 'ev':
				$kind = self::int( $in['t'] ?? null, 1, 6 );
				if ( $kind === null ) {
					return null;
				}
				$value = match ( $kind ) {
					3       => 'mailto',
					4       => 'tel',
					5       => self::name( $in['v'] ?? null ),
					6       => self::goal_id( $in['v'] ?? null ),
					default => self::url( $in['v'] ?? null ),
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

		// Query strings and fragments are dropped: they often carry tokens or personal data.
		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$url  = $scheme . '://' . strtolower( $parts['host'] ) . $port . ( $parts['path'] ?? '/' );

		return mb_substr( $url, 0, 512 );
	}

	/** Goal clicks carry the goal id, stored as "goal:ID" so the timeline can show it. */
	private static function goal_id( mixed $value ): ?string {
		$id = self::text( $value, 8 );

		return preg_match( '/^[1-9][0-9]{0,5}$/', $id ) ? 'goal:' . $id : null;
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
