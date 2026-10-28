<?php
/**
 * Admin REST API (snn-a/v1). Used only by the admin screens.
 *
 * Viewing needs the snn_a_view capability (granted to administrators on activation).
 * Changing settings, rebuilding data, importing and deleting need manage_options.
 * Every route is also covered by WordPress's REST nonce check for logged-in requests.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class API {

	const NS  = 'snn-a/v1';
	const CAP = 'snn_a_view';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$view  = static fn() => current_user_can( self::CAP );
		$admin = static fn() => current_user_can( 'manage_options' );

		$range = [
			'range' => [ 'type' => 'string', 'default' => '30d', 'sanitize_callback' => 'sanitize_text_field' ],
			'from'  => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'to'    => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
		];

		$get = static fn( string $path, callable $cb, array $args, callable $perm ) => register_rest_route( self::NS, $path, [
			'methods'             => 'GET',
			'callback'            => $cb,
			'permission_callback' => $perm,
			'args'                => $args,
		] );

		$post = static fn( string $path, callable $cb, callable $perm ) => register_rest_route( self::NS, $path, [
			'methods'             => 'POST',
			'callback'            => $cb,
			'permission_callback' => $perm,
		] );

		$get( '/stats/overview', [ self::class, 'overview' ], $range, $view );
		$get( '/stats/pages', [ self::class, 'pages' ], $range + [
			'search'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'orderby'  => [ 'type' => 'string', 'default' => 'views', 'sanitize_callback' => 'sanitize_key' ],
			'order'    => [ 'type' => 'string', 'default' => 'desc', 'sanitize_callback' => 'sanitize_key' ],
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 25 ],
		], $view );
		$get( '/stats/page-series', [ self::class, 'page_series' ], $range + [
			'id' => [ 'type' => 'integer', 'required' => true ],
		], $view );
		$get( '/stats/referrers', [ self::class, 'referrers' ], $range, $view );
		$get( '/stats/geo', [ self::class, 'geo' ], $range, $view );
		$get( '/stats/devices', [ self::class, 'devices' ], $range, $view );
		$get( '/stats/campaigns', [ self::class, 'campaigns' ], $range, $view );
		$get( '/stats/realtime', [ self::class, 'realtime' ], [], $view );
		$get( '/stats/recent', [ self::class, 'recent' ], [
			'limit' => [ 'type' => 'integer', 'default' => 10 ],
		], $view );
		$get( '/stats/journeys', [ self::class, 'journeys' ], $range + [
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 25 ],
		], $view );
		$get( '/stats/journey', [ self::class, 'journey' ], [
			'id' => [ 'type' => 'integer', 'required' => true ],
		], $view );
		$get( '/goals', [ self::class, 'goals' ], $range, $view );
		$post( '/goals', [ self::class, 'save_goal' ], $view );
		$post( '/goals/delete', [ self::class, 'delete_goal' ], $view );
		$get( '/funnels', [ self::class, 'funnels' ], $range, $view );
		$post( '/funnels', [ self::class, 'save_funnel' ], $view );
		$post( '/funnels/delete', [ self::class, 'delete_funnel' ], $view );
		$get( '/funnels/report', [ self::class, 'funnel_report' ], $range + [
			'id' => [ 'type' => 'integer', 'required' => true ],
		], $view );

		$get( '/prefs/dashboard', [ self::class, 'get_layout' ], [], $view );
		$post( '/prefs/dashboard', [ self::class, 'save_layout' ], $view );

		$post( '/admin/rebuild', [ self::class, 'rebuild' ], $admin );
		$post( '/admin/delete-data', [ self::class, 'delete_data' ], $admin );
		$get( '/geo', [ self::class, 'geo_status' ], [], $admin );
		$post( '/geo/download', [ self::class, 'geo_download' ], $admin );
		$post( '/geo/remove', [ self::class, 'geo_remove' ], $admin );

		$get( '/import', [ self::class, 'import_status' ], [], $admin );
		$post( '/import/start', [ self::class, 'import_start' ], $admin );
		$post( '/import/step', [ self::class, 'import_step' ], $admin );
		$post( '/import/cancel', [ self::class, 'import_cancel' ], $admin );
	}

	// ------------------------------------------------------------- helpers

	private static function range( \WP_REST_Request $req ): array {
		return Ranges::resolve(
			(string) $req->get_param( 'range' ),
			(string) $req->get_param( 'from' ),
			(string) $req->get_param( 'to' )
		);
	}

	private static function ok( mixed $data ): \WP_REST_Response {
		return new \WP_REST_Response( $data, 200 );
	}

	private static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'snn_a_error', $message, [ 'status' => $status ] );
	}

	/** Runs a write and turns validation errors into a 400 response. */
	private static function attempt( callable $fn ): mixed {
		try {
			return $fn();
		} catch ( \InvalidArgumentException $e ) {
			return self::error( $e->getMessage() );
		}
	}

	// --------------------------------------------------------------- stats

	public static function overview( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::overview( self::range( $req ) ) );
	}

	public static function pages( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::pages( self::range( $req ), [
			'search'   => (string) $req->get_param( 'search' ),
			'orderby'  => (string) $req->get_param( 'orderby' ),
			'order'    => (string) $req->get_param( 'order' ),
			'page'     => max( 1, (int) $req->get_param( 'page' ) ),
			'per_page' => (int) $req->get_param( 'per_page' ),
		] ) );
	}

	public static function page_series( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::page_series( (int) $req->get_param( 'id' ), self::range( $req ) ) );
	}

	public static function referrers( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::referrers( self::range( $req ) ) );
	}

	public static function geo( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::geo( self::range( $req ) ) );
	}

	public static function devices( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::devices( self::range( $req ) ) );
	}

	public static function campaigns( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::campaigns( self::range( $req ) ) );
	}

	public static function recent( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::recent( (int) $req->get_param( 'limit' ) ) );
	}

	public static function realtime(): \WP_REST_Response {
		return self::ok( Stats::realtime() );
	}

	public static function journeys( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Stats::journeys( self::range( $req ), max( 1, (int) $req->get_param( 'page' ) ), (int) $req->get_param( 'per_page' ) ) );
	}

	public static function journey( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$data = Stats::journey( (int) $req->get_param( 'id' ) );

		return $data === null ? self::error( 'Session not found.', 404 ) : self::ok( $data );
	}

	// --------------------------------------------------------------- goals

	public static function goals( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Goals::all( self::range( $req ) ) );
	}

	public static function save_goal( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		return self::attempt( static fn() => self::ok( [ 'id' => Goals::save( self::body( $req ) ) ] ) );
	}

	public static function delete_goal( \WP_REST_Request $req ): \WP_REST_Response {
		Goals::delete( (int) ( self::body( $req )['id'] ?? 0 ) );

		return self::ok( [ 'deleted' => true ] );
	}

	public static function funnels( \WP_REST_Request $req ): \WP_REST_Response {
		return self::ok( Goals::funnels() );
	}

	public static function save_funnel( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		return self::attempt( static fn() => self::ok( [ 'id' => Goals::save_funnel( self::body( $req ) ) ] ) );
	}

	public static function delete_funnel( \WP_REST_Request $req ): \WP_REST_Response {
		Goals::delete_funnel( (int) ( self::body( $req )['id'] ?? 0 ) );

		return self::ok( [ 'deleted' => true ] );
	}

	public static function funnel_report( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$report = Goals::funnel_report( (int) $req->get_param( 'id' ), self::range( $req ) );

		return $report === null ? self::error( 'Funnel not found.', 404 ) : self::ok( $report );
	}

	// -------------------------------------------------------- preferences

	public static function get_layout(): \WP_REST_Response {
		return self::ok( self::layout() );
	}

	public static function save_layout( \WP_REST_Request $req ): \WP_REST_Response {
		$allowed = Admin::CARDS;
		$body    = self::body( $req );

		$order  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $body['order'] ?? [] ) ), static fn( $c ) => in_array( $c, $allowed, true ) ) );
		$hidden = array_values( array_filter( array_map( 'sanitize_key', (array) ( $body['hidden'] ?? [] ) ), static fn( $c ) => in_array( $c, $allowed, true ) ) );

		$sizes = [];
		foreach ( (array) ( $body['sizes'] ?? [] ) as $card => $size ) {
			$card = sanitize_key( (string) $card );
			if ( in_array( $card, $allowed, true ) && in_array( $size, [ 'half', 'full' ], true ) ) {
				$sizes[ $card ] = $size;
			}
		}

		update_user_meta( get_current_user_id(), 'snn_a_dashboard_layout', [ 'order' => $order, 'hidden' => $hidden, 'sizes' => $sizes ] );

		return self::ok( self::layout() );
	}

	private static function layout(): array {
		$saved = get_user_meta( get_current_user_id(), 'snn_a_dashboard_layout', true );

		return [
			'order'  => is_array( $saved ) && ! empty( $saved['order'] ) ? $saved['order'] : Admin::CARDS,
			'hidden' => is_array( $saved ) ? ( $saved['hidden'] ?? [] ) : [],
			'sizes'  => is_array( $saved ) ? ( $saved['sizes'] ?? [] ) : [],
		];
	}

	// -------------------------------------------------------------- admin

	public static function rebuild(): \WP_REST_Response {
		$first = Rollup::first_day();
		if ( $first !== null ) {
			Rollup::refresh_range( $first, Ranges::day_of( time() ) );
		}

		return self::ok( [ 'days' => $first === null ? 0 : Ranges::day_of( time() ) - $first + 1 ] );
	}

	public static function delete_data( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		if ( (string) ( self::body( $req )['confirm'] ?? '' ) !== 'DELETE' ) {
			return self::error( 'Type DELETE to confirm.' );
		}

		Maintenance::delete_all_data();

		return self::ok( [ 'deleted' => true ] );
	}

	public static function geo_status(): \WP_REST_Response {
		return self::ok( Geo::status() );
	}

	public static function geo_download(): \WP_REST_Response {
		Geo::queue_download();

		return self::ok( Geo::status() );
	}

	public static function geo_remove(): \WP_REST_Response {
		Geo::remove_city_db();

		return self::ok( Geo::status() );
	}

	public static function import_status(): \WP_REST_Response {
		return self::ok( [ 'detect' => Importer::detect(), 'status' => Importer::status() ] );
	}

	public static function import_start(): \WP_REST_Response {
		return self::ok( Importer::start() );
	}

	public static function import_step(): \WP_REST_Response {
		return self::ok( Importer::step() );
	}

	public static function import_cancel(): \WP_REST_Response {
		return self::ok( Importer::cancel() );
	}

	/** JSON body or form fields, whichever the request used. */
	private static function body( \WP_REST_Request $req ): array {
		$json = $req->get_json_params();

		return is_array( $json ) ? $json : (array) $req->get_body_params();
	}
}
