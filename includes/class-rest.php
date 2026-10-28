<?php
/**
 * REST fallback for hosts that block direct PHP files in plugin folders.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class REST {

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( 'snn-a/v1', '/t', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'track' ],
			// Anonymous by design. Tracker validates everything, so there is nothing to protect here.
			'permission_callback' => '__return_true',
		] );
	}

	public static function track( \WP_REST_Request $request ): \WP_REST_Response {
		Tracker::process( (string) $request->get_body(), $_SERVER );

		return new \WP_REST_Response( null, 204 );
	}
}
