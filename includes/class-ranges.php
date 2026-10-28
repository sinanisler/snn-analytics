<?php
/**
 * Date ranges. Days are numbered from 1970-01-01 in the site's local timezone,
 * which is the key used by every daily summary table.
 *
 * The UTC offset is the one in effect now. Around a DST change, one hour of
 * data can fall on the neighbouring day. That is accepted in exchange for
 * plain integer days.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Ranges {

	const PRESETS = [
		'today' => 1,
		'7d'    => 7,
		'30d'   => 30,
		'90d'   => 90,
		'12m'   => 365,
	];

	const MAX_CUSTOM_DAYS = 1826; // five years

	public static function offset(): int {
		return wp_timezone()->getOffset( new \DateTimeImmutable( 'now' ) );
	}

	public static function day_of( int $ts ): int {
		return (int) floor( ( $ts + self::offset() ) / 86400 );
	}

	/** Unix timestamp of local midnight at the start of $day. */
	public static function day_start( int $day ): int {
		return $day * 86400 - self::offset();
	}

	/**
	 * Resolves a preset or a custom range.
	 *
	 * @return array{range:string, from:int, to:int, prev_from:int, prev_to:int, from_date:string, to_date:string}
	 */
	public static function resolve( string $range, string $from = '', string $to = '' ): array {
		$today = self::day_of( time() );

		if ( $range === 'custom' ) {
			$f = self::parse_date( $from );
			$t = self::parse_date( $to );
			if ( $f === null || $t === null ) {
				$range = '30d';
			} else {
				if ( $f > $t ) {
					[ $f, $t ] = [ $t, $f ];
				}
				$t = min( $t, $today );
				$f = max( $f, $t - self::MAX_CUSTOM_DAYS + 1 );
			}
		}

		if ( $range !== 'custom' ) {
			$days = self::PRESETS[ $range ] ?? self::PRESETS['30d'];
			$range = isset( self::PRESETS[ $range ] ) ? $range : '30d';
			$t = $today;
			$f = $today - $days + 1;
		}

		$length = $t - $f + 1;

		return [
			'range'     => $range,
			'from'      => $f,
			'to'        => $t,
			'prev_from' => $f - $length,
			'prev_to'   => $f - 1,
			'from_date' => gmdate( 'Y-m-d', self::day_start( $f ) + self::offset() ),
			'to_date'   => gmdate( 'Y-m-d', self::day_start( $t ) + self::offset() ),
		];
	}

	/** Day number for a YYYY-MM-DD string in site time, or null when invalid. */
	public static function parse_date( string $date ): ?int {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}

		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		if ( $dt === false || $dt->format( 'Y-m-d' ) !== $date ) {
			return null;
		}

		return self::day_of( $dt->getTimestamp() );
	}
}
