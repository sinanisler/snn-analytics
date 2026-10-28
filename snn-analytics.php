<?php
/**    
 * Plugin Name:       SNN Analytics
 * Description:       Fast, privacy-friendly, self-hosted analytics. No cookies.
 * Version:           0.1.6
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            snn
 * Author URI:        https://snn.is
 * License:           GPL-2.0-or-later
 * Text Domain:       snn-analytics
 */

defined( 'ABSPATH' ) || exit;

define( 'SNN_A_VERSION', '0.1.6' );
define( 'SNN_A_FILE', __FILE__ );

foreach ( [
	'class-store', 'class-settings', 'class-db', 'class-validator', 'class-ua', 'class-bot',
	'class-mmdb', 'class-geo', 'class-ranges', 'class-goals', 'class-tracker', 'class-frontend',
	'class-rest', 'class-rollup', 'class-stats', 'class-access', 'class-api', 'class-maintenance', 'class-importer',
	'class-admin', 'class-widget',
] as $snn_a_file ) {
	require_once __DIR__ . '/includes/' . $snn_a_file . '.php';
}

register_activation_hook( __FILE__, static function (): void {
	SNN_Analytics\DB::install();
	SNN_Analytics\Settings::update( [ 'site_host' => SNN_Analytics\Settings::host_of( home_url( '/' ) ) ] );
	SNN_Analytics\Admin::grant_capability();

	if ( ! wp_next_scheduled( SNN_Analytics\Rollup::HOOK ) ) {
		wp_schedule_event( time() + 60, 'snn_a_15min', SNN_Analytics\Rollup::HOOK );
	}
	if ( ! wp_next_scheduled( SNN_Analytics\Maintenance::HOOK ) ) {
		wp_schedule_event( time() + 3600, 'daily', SNN_Analytics\Maintenance::HOOK );
	}
} );

register_deactivation_hook( __FILE__, static function (): void {
	wp_clear_scheduled_hook( SNN_Analytics\Rollup::HOOK );
	wp_clear_scheduled_hook( SNN_Analytics\Maintenance::HOOK );
	wp_clear_scheduled_hook( SNN_Analytics\Geo::HOOK );
} );

add_filter( 'cron_schedules', static function ( array $schedules ): array {
	$schedules['snn_a_15min'] = [ 'interval' => 900, 'display' => 'Every 15 minutes (SNN Analytics)' ];

	return $schedules;
} );

add_action( 'update_option_home', static function ( $old, $new ): void {
	SNN_Analytics\Settings::update( [ 'site_host' => SNN_Analytics\Settings::host_of( (string) $new ) ] );
}, 10, 2 );

foreach ( [ 'update_option_timezone_string', 'update_option_gmt_offset' ] as $snn_a_option ) {
	add_action( $snn_a_option, [ SNN_Analytics\Settings::class, 'refresh_offset' ] );
}

add_action( 'plugins_loaded', [ SNN_Analytics\DB::class, 'maybe_upgrade' ] );
add_action( SNN_Analytics\Rollup::HOOK, [ SNN_Analytics\Rollup::class, 'refresh_recent' ] );
add_action( SNN_Analytics\Maintenance::HOOK, [ SNN_Analytics\Maintenance::class, 'retention' ] );
add_action( SNN_Analytics\Geo::HOOK, [ SNN_Analytics\Geo::class, 'run_download' ] );

SNN_Analytics\Frontend::register();
SNN_Analytics\REST::register();
SNN_Analytics\Access::register();
SNN_Analytics\API::register();

if ( is_admin() ) {
	SNN_Analytics\Admin::register();
	SNN_Analytics\Widget::register();
}
