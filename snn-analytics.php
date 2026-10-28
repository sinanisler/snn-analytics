<?php
/**
 * Plugin Name:       SNN Analytics
 * Description:       Fast, privacy-friendly, self-hosted analytics. No cookies.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * License:           GPL-2.0-or-later
 * Text Domain:       snn-analytics
 */

defined( 'ABSPATH' ) || exit;

define( 'SNN_A_VERSION', '0.1.0' );
define( 'SNN_A_FILE', __FILE__ );

foreach ( [ 'class-store', 'class-settings', 'class-db', 'class-validator', 'class-ua', 'class-bot', 'class-geo', 'class-tracker', 'class-frontend', 'class-rest' ] as $snn_a_file ) {
	require_once __DIR__ . '/includes/' . $snn_a_file . '.php';
}

register_activation_hook( __FILE__, static function (): void {
	SNN_Analytics\DB::install();
	SNN_Analytics\Settings::update( [ 'site_host' => SNN_Analytics\Settings::host_of( home_url( '/' ) ) ] );
} );

add_action( 'update_option_home', static function ( $old, $new ): void {
	SNN_Analytics\Settings::update( [ 'site_host' => SNN_Analytics\Settings::host_of( (string) $new ) ] );
}, 10, 2 );

add_action( 'plugins_loaded', [ SNN_Analytics\DB::class, 'maybe_upgrade' ] );

SNN_Analytics\Frontend::register();
SNN_Analytics\REST::register();
