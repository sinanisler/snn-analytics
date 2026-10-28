<?php
/**
 * Tracking endpoint. Loads only the WordPress database layer (SHORTINIT),
 * so a hit skips plugins, the theme and the REST stack.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'SHORTINIT', true );
	require dirname( __DIR__, 3 ) . '/wp-load.php';
}

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-validator.php';
require_once __DIR__ . '/includes/class-ua.php';
require_once __DIR__ . '/includes/class-bot.php';
require_once __DIR__ . '/includes/class-geo.php';
require_once __DIR__ . '/includes/class-tracker.php';

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
	http_response_code( 405 );
	exit;
}

$snn_a_raw = (string) file_get_contents( 'php://input', false, null, 0, SNN_Analytics\Tracker::MAX_BODY + 1 );

// Answer first, then write. The browser never waits for the database.
http_response_code( 204 );
header( 'Cache-Control: no-store' );
if ( function_exists( 'fastcgi_finish_request' ) ) {
	fastcgi_finish_request();
}

SNN_Analytics\Tracker::process( $snn_a_raw, $_SERVER );
