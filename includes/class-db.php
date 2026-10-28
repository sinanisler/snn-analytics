<?php
/**
 * Schema, migrations and the small query helpers the tracker uses.
 *
 * Every table is prefixed with {$wpdb->prefix}snn_a_. There are no foreign keys:
 * integrity is handled in code, because constraints are slow and break on shared hosts.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class DB {

	const VERSION = 1;
	const OPTION  = 'snn_a_db_version';

	/** Columns that hold 8 raw bytes. PHP passes them as hex, SQL converts with UNHEX(). */
	private const BINARY_COLUMNS = [ 'hash', 'view_key' ];

	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'snn_a_' . $name;
	}

	/**
	 * 16 hex characters (8 bytes) from a SHA-256. Used for every lookup-table key.
	 */
	public static function key( string $value ): string {
		return substr( hash( 'sha256', $value ), 0, 16 );
	}

	public static function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( self::schema() );
		// Autoloaded so the version check on each request costs no extra query.
		update_option( self::OPTION, self::VERSION, true );
	}

	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Insert a row and return its id. Use for fact tables (sessions, views, events).
	 */
	public static function insert( string $table, array $row ): int {
		global $wpdb;

		[ $cols, $marks, $args ] = self::values( $row );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} ({$cols}) VALUES ({$marks})", ...$args ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert-or-find a dimension row and return its id, in one query.
	 * $refresh lists columns to overwrite when the row already exists.
	 * Check $wpdb->rows_affected right after the call: 1 means a new row was inserted.
	 */
	public static function upsert( string $table, array $row, array $refresh = [] ): int {
		global $wpdb;

		[ $cols, $marks, $args ] = self::values( $row );

		$sets = [ 'id = LAST_INSERT_ID(id)' ];
		foreach ( $refresh as $col => $value ) {
			$sets[] = $col . ' = ' . ( is_int( $value ) ? '%d' : '%s' );
			$args[] = $value;
		}

		$sql = "INSERT INTO {$table} ({$cols}) VALUES ({$marks}) ON DUPLICATE KEY UPDATE " . implode( ', ', $sets );
		$wpdb->query( $wpdb->prepare( $sql, ...$args ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Builds the column list, placeholders and arguments for an INSERT.
	 * Column names come from code, never from input.
	 */
	private static function values( array $row ): array {
		$cols  = [];
		$marks = [];
		$args  = [];

		foreach ( $row as $col => $value ) {
			$cols[] = $col;

			if ( $value === null ) {
				$marks[] = 'NULL';
				continue;
			}

			if ( in_array( $col, self::BINARY_COLUMNS, true ) ) {
				$marks[] = 'UNHEX(%s)';
			} else {
				$marks[] = is_int( $value ) ? '%d' : '%s';
			}
			$args[] = $value;
		}

		return [ implode( ', ', $cols ), implode( ', ', $marks ), $args ];
	}

	private static function schema(): string {
		global $wpdb;

		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'snn_a_';

		// Lookup tables.
		$sql = "CREATE TABLE {$p}pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  type TINYINT UNSIGNED NOT NULL,
  object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  path VARCHAR(512) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  post_type VARCHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash),
  KEY type_object (type, object_id)
) $c;
CREATE TABLE {$p}referrers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  domain VARCHAR(253) NOT NULL DEFAULT '',
  type TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}utm_values (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  value VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}campaigns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  source_id INT UNSIGNED NULL,
  medium_id INT UNSIGNED NULL,
  campaign_id INT UNSIGNED NULL,
  term_id INT UNSIGNED NULL,
  content_id INT UNSIGNED NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}countries (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code CHAR(2) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code)
) $c;
CREATE TABLE {$p}ua (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind TINYINT UNSIGNED NOT NULL,
  name VARCHAR(32) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY kind_name (kind, name)
) $c;
CREATE TABLE {$p}event_targets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  value VARCHAR(512) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}visitors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hash BINARY(8) NOT NULL,
  first_seen INT UNSIGNED NOT NULL,
  last_seen INT UNSIGNED NOT NULL,
  sessions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_id INT UNSIGNED NOT NULL,
  started_at INT UNSIGNED NOT NULL,
  last_seen INT UNSIGNED NOT NULL,
  entry_page_id INT UNSIGNED NOT NULL,
  exit_page_id INT UNSIGNED NOT NULL,
  referrer_id INT UNSIGNED NULL,
  campaign_id INT UNSIGNED NULL,
  country_id SMALLINT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  device TINYINT UNSIGNED NOT NULL DEFAULT 0,
  browser_id SMALLINT UNSIGNED NULL,
  os_id SMALLINT UNSIGNED NULL,
  views SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  events SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  is_bounce TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY started_at (started_at),
  KEY last_seen (last_seen),
  KEY visitor_last (visitor_id, last_seen)
) $c;
CREATE TABLE {$p}views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  view_key BINARY(8) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  duration MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  seq SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY session_seq (session_id, seq),
  KEY created_at (created_at),
  KEY page_created (page_id, created_at),
  KEY view_key (view_key)
) $c;
CREATE TABLE {$p}events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  view_id BIGINT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  type TINYINT UNSIGNED NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  PRIMARY KEY  (id),
  KEY session_id (session_id),
  KEY created_at (created_at),
  KEY target_created (target_id, created_at)
) $c;";

		return $sql;
	}
}
