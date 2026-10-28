<?php
/**
 * Which user roles can see which features.
 *
 * Administrators (manage_options) always see everything. For every other role, the admin picks
 * features under Settings. The choice lives in the settings option, and the capabilities are
 * granted on the fly through user_has_cap, so no role is ever modified in the database and
 * nothing is left behind when the plugin is removed.
 *
 * Capability names: snn_a_view (any feature) and snn_a_view_{feature}.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Access {

	const ANY = 'snn_a_view';

	/** Feature key => label. Keys match the admin view names. */
	const FEATURES = [
		'dashboard' => 'Dashboard',
		'realtime'  => 'Realtime',
		'journeys'  => 'Journeys',
		'pages'     => 'Pages',
		'compare'   => 'Compare',
		'heatmaps'  => 'Heatmaps',
		'replay'    => 'Replay',
		'referrers' => 'Referrers',
		'geo'       => 'Geographic',
		'devices'   => 'Devices',
		'goals'     => 'Conversion Tracking',
		'widget'    => 'WordPress dashboard widget',
	];

	public static function register(): void {
		add_filter( 'user_has_cap', [ self::class, 'grant' ], 10, 4 );
	}

	public static function cap( string $feature ): string {
		return self::ANY . '_' . $feature;
	}

	/** True when the current user can use the feature. */
	public static function can( string $feature ): bool {
		return current_user_can( self::cap( $feature ) );
	}

	/** True when the current user can use at least one of the features. */
	public static function can_any( string ...$features ): bool {
		foreach ( $features as $feature ) {
			if ( self::can( $feature ) ) {
				return true;
			}
		}

		return false;
	}

	/** Roles (other than administrators) that may use a feature. */
	public static function roles_for( string $feature ): array {
		$map = Settings::get()['role_access'];

		return is_array( $map ) && isset( $map[ $feature ] ) ? array_values( (array) $map[ $feature ] ) : [];
	}

	/** user_has_cap filter. Only ever adds capabilities that start with snn_a_view. */
	public static function grant( array $allcaps, array $caps, array $args, $user ): array {
		foreach ( $caps as $cap ) {
			if ( strncmp( (string) $cap, self::ANY, strlen( self::ANY ) ) !== 0 ) {
				continue;
			}

			if ( ! empty( $allcaps['manage_options'] ) || self::roles_allow( (string) $cap, $user instanceof \WP_User ? (array) $user->roles : [] ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}

	private static function roles_allow( string $cap, array $roles ): bool {
		if ( $roles === [] ) {
			return false;
		}

		if ( $cap === self::ANY ) {
			foreach ( array_keys( self::FEATURES ) as $feature ) {
				if ( array_intersect( $roles, self::roles_for( $feature ) ) ) {
					return true;
				}
			}

			return false;
		}

		$feature = substr( $cap, strlen( self::ANY ) + 1 );

		return isset( self::FEATURES[ $feature ] ) && array_intersect( $roles, self::roles_for( $feature ) ) !== [];
	}

	/**
	 * Cleans a submitted role_access array: known features, existing roles, never administrator.
	 *
	 * @return array<string, string[]>
	 */
	public static function sanitize( mixed $submitted ): array {
		$roles = array_diff( array_keys( wp_roles()->get_names() ), [ 'administrator' ] );
		$out   = [];

		foreach ( array_keys( self::FEATURES ) as $feature ) {
			$picked = array_map( 'sanitize_key', (array) ( is_array( $submitted ) ? ( $submitted[ $feature ] ?? [] ) : [] ) );
			$keep   = array_values( array_intersect( $picked, $roles ) );
			if ( $keep ) {
				$out[ $feature ] = $keep;
			}
		}

		return $out;
	}
}
