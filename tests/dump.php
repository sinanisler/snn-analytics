<?php
defined( 'ABSPATH' ) || exit;
// Test helper: prints, as JSON, every session of the visitor that a given IP + user agent hashes to.
use SNN_Analytics\DB;
use SNN_Analytics\Settings;
use SNN_Analytics\UA;

global $wpdb;
[ $ip, $ua ] = [ $args[0], base64_decode( $args[1] ) ];
$info = UA::classify( $ua );
$vh   = DB::key( Settings::salt() . '|' . $ip . '|' . $info['browser'] . '|' . $info['os'] . '|' . $info['device'] . '|' . Settings::get()['site_host'] );
$t    = fn( $n ) => DB::table( $n );

$out = [];
$vid = $wpdb->get_var( 'SELECT id FROM ' . $t( 'visitors' ) . ' WHERE hash = ' . DB::hex( $vh ) );
if ( $vid ) {
	$sessions = $wpdb->get_results( $wpdb->prepare(
		'SELECT s.id, s.views, s.events, s.is_bounce, s.duration, r.domain AS ref, s.campaign_id, ep.path AS entry, xp.path AS exit_path
		 FROM ' . $t( 'sessions' ) . ' s LEFT JOIN ' . $t( 'referrers' ) . ' r ON r.id = s.referrer_id
		 JOIN ' . $t( 'pages' ) . ' ep ON ep.id = s.entry_page_id JOIN ' . $t( 'pages' ) . ' xp ON xp.id = s.exit_page_id
		 WHERE s.visitor_id = %d ORDER BY s.id', $vid ), ARRAY_A );
	foreach ( $sessions as &$s ) {
		if ( $s['campaign_id'] ) {
			$s['utm_source'] = $wpdb->get_var( 'SELECT u.value FROM ' . $t( 'campaigns' ) . ' c JOIN ' . $t( 'utm_values' ) . ' u ON u.id = c.source_id WHERE c.id = ' . (int) $s['campaign_id'] );
		}
		$s['pages'] = $wpdb->get_results( 'SELECT p.path, v.seq, v.duration FROM ' . $t( 'views' ) . ' v JOIN ' . $t( 'pages' ) . ' p ON p.id = v.page_id WHERE v.session_id = ' . (int) $s['id'] . ' ORDER BY v.id', ARRAY_A );
		$s['ev']    = $wpdb->get_results( 'SELECT e.type, et.value FROM ' . $t( 'events' ) . ' e JOIN ' . $t( 'event_targets' ) . ' et ON et.id = e.target_id WHERE e.session_id = ' . (int) $s['id'] . ' ORDER BY e.id', ARRAY_A );
	}
	$out = $sessions;
}
echo wp_json_encode( [ 'sessions' => $out, 'err' => $wpdb->last_error ] );
