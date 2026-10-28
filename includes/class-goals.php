<?php
/**
 * Goals (matched when a hit is written) and funnels (computed on request).
 *
 * The active goal rules live in the settings option, which the tracker already reads on
 * every hit, so checking goals costs no extra query.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Goals {

	const TYPE_PAGE  = 1; // page path
	const TYPE_CLICK = 2; // CSS selector, reported by the tracker as goal id
	const TYPE_EVENT = 3; // custom event name from snnA.track()

	const MATCH_EXACT    = 1;
	const MATCH_CONTAINS = 2;
	const MATCH_PREFIX   = 3;

	const MAX_FUNNEL_ROWS = 500000;

	// ------------------------------------------------------------ on each hit

	public static function check_page( int $session_id, string $path, int $now ): void {
		foreach ( Settings::get()['goal_rules'] as $rule ) {
			if ( $rule['type'] === self::TYPE_PAGE && self::matches( $rule, $path ) ) {
				self::convert( $rule['id'], $session_id, $now );
			}
		}
	}

	/**
	 * $type is the tracker event type: 5 for a custom event, 6 for a goal click
	 * (value "goal:ID"). Other types never convert a goal.
	 */
	public static function check_event( int $session_id, int $type, string $value, int $now ): void {
		foreach ( Settings::get()['goal_rules'] as $rule ) {
			if ( $type === 6 && $rule['type'] === self::TYPE_CLICK && 'goal:' . $rule['id'] === $value ) {
				self::convert( $rule['id'], $session_id, $now );
			} elseif ( $type === 5 && $rule['type'] === self::TYPE_EVENT && self::matches( $rule, $value ) ) {
				self::convert( $rule['id'], $session_id, $now );
			}
		}
	}

	private static function matches( array $rule, string $value ): bool {
		return match ( $rule['match'] ) {
			self::MATCH_EXACT    => $value === $rule['value'],
			self::MATCH_CONTAINS => $rule['value'] !== '' && str_contains( $value, $rule['value'] ),
			self::MATCH_PREFIX   => $rule['value'] !== '' && str_starts_with( $value, $rule['value'] ),
			default              => false,
		};
	}

	private static function convert( int $goal_id, int $session_id, int $now ): void {
		global $wpdb;

		// One conversion per goal per session. INSERT IGNORE keeps repeats harmless.
		$wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . DB::table( 'conversions' ) . ' (goal_id, session_id, created_at) VALUES (%d, %d, %d)',
			$goal_id,
			$session_id,
			$now
		) );
	}

	// ------------------------------------------------------------ goals admin

	public static function all( array $r ): array {
		global $wpdb;

		$rows   = $wpdb->get_results( 'SELECT * FROM ' . DB::table( 'goals' ) . ' ORDER BY id', ARRAY_A );
		$totals = Stats::goal_totals( $r );

		return array_map( static fn( $row ) => [
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'type'        => (int) $row['type'],
			'match'       => (int) $row['match_type'],
			'value'       => (string) $row['value'],
			'active'      => (bool) $row['is_active'],
			'conversions' => $totals[ (int) $row['id'] ] ?? 0,
		], $rows );
	}

	public static function save( array $data ): int {
		global $wpdb;

		$id    = (int) ( $data['id'] ?? 0 );
		$type  = (int) ( $data['type'] ?? 0 );
		$match = (int) ( $data['match'] ?? self::MATCH_EXACT );
		$name  = Validator::text( $data['name'] ?? '', 100 );
		$value = Validator::text( $data['value'] ?? '', 255 );

		if ( ! in_array( $type, [ self::TYPE_PAGE, self::TYPE_CLICK, self::TYPE_EVENT ], true ) ) {
			throw new \InvalidArgumentException( 'Unknown goal type.' );
		}
		if ( ! in_array( $match, [ self::MATCH_EXACT, self::MATCH_CONTAINS, self::MATCH_PREFIX ], true ) ) {
			$match = self::MATCH_EXACT;
		}
		if ( $value === '' ) {
			throw new \InvalidArgumentException( 'A goal needs a value.' );
		}
		if ( $type === self::TYPE_PAGE && ! str_starts_with( $value, '/' ) ) {
			$value = '/' . $value;
		}
		if ( $type === self::TYPE_EVENT && ! preg_match( '/^[\p{L}\p{N} _.:\-]{1,64}$/u', $value ) ) {
			throw new \InvalidArgumentException( 'Event names may use letters, numbers, spaces, dots, colons, dashes and underscores.' );
		}
		if ( $type === self::TYPE_CLICK ) {
			$match = self::MATCH_EXACT;
		}
		if ( $name === '' ) {
			$name = $value;
		}

		$row = [
			'name'       => $name,
			'type'       => $type,
			'match_type' => $match,
			'value'      => $value,
			'is_active'  => 1,
		];

		if ( $id > 0 ) {
			$wpdb->update( DB::table( 'goals' ), $row, [ 'id' => $id ] );
		} else {
			$row['created_at'] = time();
			$id                = DB::insert( DB::table( 'goals' ), $row );
		}

		self::sync_rules();

		return $id;
	}

	public static function delete( int $id ): void {
		global $wpdb;

		$wpdb->delete( DB::table( 'goals' ), [ 'id' => $id ] );
		$wpdb->delete( DB::table( 'conversions' ), [ 'goal_id' => $id ] );
		$wpdb->delete( DB::table( 'd_goals' ), [ 'goal_id' => $id ] );
		$wpdb->delete( DB::table( 'funnel_steps' ), [ 'goal_id' => $id ] );

		self::sync_rules();
	}

	/** Copies active goals into the settings option, which the tracker reads on each hit. */
	public static function sync_rules(): void {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT id, type, match_type, value FROM ' . DB::table( 'goals' ) . ' WHERE is_active = 1 ORDER BY id LIMIT 200',
			ARRAY_A
		);

		Settings::update( [
			'goal_rules' => array_map( static fn( $row ) => [
				'id'    => (int) $row['id'],
				'type'  => (int) $row['type'],
				'match' => (int) $row['match_type'],
				'value' => (string) $row['value'],
			], $rows ),
		] );
	}

	/** Click goals the tracker script needs to listen for. */
	public static function click_rules(): array {
		return array_values( array_map(
			static fn( $rule ) => [ 'id' => $rule['id'], 'selector' => $rule['value'] ],
			array_filter( Settings::get()['goal_rules'], static fn( $rule ) => $rule['type'] === self::TYPE_CLICK )
		) );
	}

	// ----------------------------------------------------------- funnels

	public static function funnels(): array {
		global $wpdb;

		$funnels = $wpdb->get_results( 'SELECT * FROM ' . DB::table( 'funnels' ) . ' ORDER BY id', ARRAY_A );
		$steps   = $wpdb->get_results( 'SELECT funnel_id, position, goal_id FROM ' . DB::table( 'funnel_steps' ) . ' ORDER BY funnel_id, position', ARRAY_A );

		$by_funnel = [];
		foreach ( $steps as $step ) {
			$by_funnel[ (int) $step['funnel_id'] ][] = (int) $step['goal_id'];
		}

		return array_map( static fn( $row ) => [
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'window_days' => (int) round( (int) $row['window_sec'] / 86400 ),
			'steps'       => $by_funnel[ (int) $row['id'] ] ?? [],
		], $funnels );
	}

	public static function save_funnel( array $data ): int {
		global $wpdb;

		$id     = (int) ( $data['id'] ?? 0 );
		$name   = Validator::text( $data['name'] ?? '', 100 );
		$days   = min( 365, max( 1, (int) ( $data['window_days'] ?? 1 ) ) );
		$steps  = array_values( array_unique( array_map( 'intval', (array) ( $data['steps'] ?? [] ) ) ) );
		$steps  = array_values( array_filter( $steps, static fn( $g ) => $g > 0 ) );

		if ( count( $steps ) < 2 || count( $steps ) > 8 ) {
			throw new \InvalidArgumentException( 'A funnel needs between 2 and 8 goals.' );
		}
		if ( $name === '' ) {
			$name = 'Funnel';
		}

		if ( $id > 0 ) {
			$wpdb->update( DB::table( 'funnels' ), [ 'name' => $name, 'window_sec' => $days * 86400 ], [ 'id' => $id ] );
			$wpdb->delete( DB::table( 'funnel_steps' ), [ 'funnel_id' => $id ] );
		} else {
			$id = DB::insert( DB::table( 'funnels' ), [ 'name' => $name, 'window_sec' => $days * 86400, 'created_at' => time() ] );
		}

		foreach ( $steps as $position => $goal_id ) {
			DB::insert( DB::table( 'funnel_steps' ), [
				'funnel_id' => $id,
				'position'  => $position + 1,
				'goal_id'   => $goal_id,
			] );
		}

		return $id;
	}

	public static function delete_funnel( int $id ): void {
		global $wpdb;

		$wpdb->delete( DB::table( 'funnels' ), [ 'id' => $id ] );
		$wpdb->delete( DB::table( 'funnel_steps' ), [ 'funnel_id' => $id ] );
	}

	/**
	 * Sessions that reach each step in order. Each step must happen after the previous one
	 * and within the funnel's window of it.
	 */
	public static function funnel_report( int $id, array $r ): ?array {
		global $wpdb;

		$funnel = null;
		foreach ( self::funnels() as $f ) {
			if ( $f['id'] === $id ) {
				$funnel = $f;
			}
		}
		if ( $funnel === null ) {
			return null;
		}

		$window  = $funnel['window_days'] * 86400;
		$from    = Ranges::day_start( $r['from'] );
		$to      = Ranges::day_start( $r['to'] + 1 );
		$conv    = DB::table( 'conversions' );
		$goals   = $wpdb->get_results( 'SELECT id, name FROM ' . DB::table( 'goals' ), OBJECT_K );

		$reached = null; // session id => time of the last completed step
		$steps   = [];

		foreach ( $funnel['steps'] as $index => $goal_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT session_id, created_at FROM {$conv} WHERE goal_id = %d AND created_at >= %d AND created_at < %d LIMIT %d",
				$goal_id,
				$from,
				$to,
				self::MAX_FUNNEL_ROWS
			), ARRAY_A );

			$next = [];
			foreach ( $rows as $row ) {
				$session = (int) $row['session_id'];
				$at      = (int) $row['created_at'];

				if ( $index === 0 ) {
					$next[ $session ] = $at;
					continue;
				}
				if ( isset( $reached[ $session ] ) && $at >= $reached[ $session ] && $at - $reached[ $session ] <= $window ) {
					$next[ $session ] = $at;
				}
			}

			$reached  = $next;
			$steps[]  = [
				'goal_id'  => $goal_id,
				'name'     => isset( $goals[ $goal_id ] ) ? (string) $goals[ $goal_id ]->name : 'Deleted goal',
				'sessions' => count( $next ),
			];
		}

		$first = $steps[0]['sessions'] ?? 0;
		$prev  = null;
		foreach ( $steps as $i => $step ) {
			$steps[ $i ]['rate_from_start'] = $first > 0 ? round( 100 * $step['sessions'] / $first, 1 ) : 0;
			$steps[ $i ]['drop_from_prev']  = $prev === null ? 0 : max( 0, $prev - $step['sessions'] );
			$prev = $step['sessions'];
		}

		return [
			'id'     => $funnel['id'],
			'name'   => $funnel['name'],
			'window' => $funnel['window_days'],
			'steps'  => $steps,
		];
	}
}
