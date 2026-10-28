<?php
/**
 * REST fallback for hosts that block direct PHP files in plugin folders.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class REST {

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
		// Last resort: admin-ajax.php is rarely blocked. The payload is form-encoded, so it is read from POST.
		add_action( 'wp_ajax_nopriv_snn_a_track', [ self::class, 'ajax' ] );
		add_action( 'wp_ajax_snn_a_track', [ self::class, 'ajax' ] );
	}

	public static function ajax(): void {
		// WordPress adds slashes to $_POST, so the JSON is unslashed before it is parsed.
		Tracker::process( (string) wp_unslash( $_POST['payload'] ?? '' ), $_SERVER );

		http_response_code( 204 );
		exit;
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
