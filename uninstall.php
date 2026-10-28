<?php
/**
 * Runs when the plugin is deleted in WordPress. Scheduled jobs and the capability are always
 * removed. Statistics are removed only when the admin opted in under Settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$snn_a_includes = __DIR__ . '/includes/';
foreach ( [ 'class-store', 'class-settings', 'class-db', 'class-geo', 'class-maintenance' ] as $snn_a_file ) {
	require_once $snn_a_includes . $snn_a_file . '.php';
}

wp_clear_scheduled_hook( SNN_Analytics\Rollup::HOOK );
wp_clear_scheduled_hook( SNN_Analytics\Maintenance::HOOK );
wp_clear_scheduled_hook( SNN_Analytics\Geo::HOOK );

$snn_a_role = get_role( 'administrator' );
if ( $snn_a_role ) {
	$snn_a_role->remove_cap( 'snn_a_view' );
}

$snn_a_settings = SNN_Analytics\Store::read( SNN_Analytics\Settings::SETTINGS );

if ( ! empty( $snn_a_settings['delete_on_uninstall'] ) ) {
	SNN_Analytics\Maintenance::uninstall();

	// Remove the city database folder, which holds the downloaded file and its protection files.
	$snn_a_dir = trailingslashit( wp_upload_dir()['basedir'] ) . SNN_Analytics\Geo::DIR;
	if ( is_dir( $snn_a_dir ) ) {
		foreach ( glob( $snn_a_dir . '/*' ) ?: [] as $snn_a_item ) {
			if ( is_file( $snn_a_item ) ) {
				wp_delete_file( $snn_a_item );
			}
		}
		foreach ( glob( $snn_a_dir . '/.*' ) ?: [] as $snn_a_item ) {
			if ( is_file( $snn_a_item ) ) {
				wp_delete_file( $snn_a_item );
			}
		}
		rmdir( $snn_a_dir );
	}
}
