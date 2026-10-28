<?php
/**
 * Tracking endpoint. Loads only the WordPress database layer (SHORTINIT),
 * so a hit skips plugins, the theme and the REST stack.
 */

// Anything printed by accident (a PHP notice, a BOM in wp-config.php) would turn the 204 into a 200,
// and the browser would send the hit again on the next route. The buffer is discarded before answering.
ob_start();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'SHORTINIT', true );

	// Normally wp-content/plugins/snn-analytics/t.php, three levels below the WordPress root.
	// A symlinked or junctioned plugin resolves __FILE__ outside the site, so the
	// requested path and the document root are tried too.
	$snn_a_roots = [
		dirname( (string) ( $_SERVER['SCRIPT_FILENAME'] ?? '' ), 3 ),
		dirname( __FILE__, 3 ),
		rtrim( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ), '/\\' ),
	];
	foreach ( $snn_a_roots as $snn_a_root ) {
		if ( $snn_a_root !== '' && $snn_a_root !== '.' && file_exists( $snn_a_root . '/wp-load.php' ) ) {
			require $snn_a_root . '/wp-load.php';
			break;
		}
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	ob_end_clean();
	http_response_code( 503 ); // WordPress could not be loaded. The browser falls back to REST.
	exit;
}

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-validator.php';
require_once __DIR__ . '/includes/class-ua.php';
require_once __DIR__ . '/includes/class-bot.php';
require_once __DIR__ . '/includes/class-mmdb.php';
require_once __DIR__ . '/includes/class-geo.php';
require_once __DIR__ . '/includes/class-goals.php';
require_once __DIR__ . '/includes/class-tracker.php';

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
	ob_end_clean();
	http_response_code( 405 );
	exit;
}

$snn_a_raw = (string) file_get_contents( 'php://input', false, null, 0, SNN_Analytics\Tracker::MAX_BODY + 1 );

// Write first, then answer. The tracker holds its pings and events until the page view's answer
// arrives, so the answer must mean the view row exists. The request is async, so no visitor waits on it.
SNN_Analytics\Tracker::process( $snn_a_raw, $_SERVER );

while ( ob_get_level() > 0 ) {
	ob_end_clean();
}

http_response_code( 204 );
header( 'Cache-Control: no-store' );
