<?php
/**
 * Reads and writes JSON options straight from the options table.
 *
 * Used by the tracking endpoint, which runs under SHORTINIT, so it avoids the
 * object cache and the serializer. Values are JSON, never PHP-serialized.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Store {

	public static function read( string $name ): array {
		global $wpdb;

		$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;

		return is_array( $data ) ? $data : [];
	}

	public static function write( string $name, array $data, bool $autoload ): void {
		global $wpdb;

		$json = (string) json_encode( $data );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)
			 ON DUPLICATE KEY UPDATE option_value = %s",
			$name,
			$json,
			$autoload ? 'yes' : 'no',
			$json
		) );
	}
}
