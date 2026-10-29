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

	const VERSION = 6;
	const OPTION  = 'snn_a_db_version';

	/** Columns that hold 8 raw bytes. PHP passes them as hex, written into the SQL as a hex literal. */
	private const BINARY_COLUMNS = [ 'hash', 'view_key' ];

	/**
	 * Hex as an SQL binary literal, X'0a1b'. Works on MySQL and on the SQLite driver, whose UNHEX() returns
	 * text that never equals a stored binary value. Anything that is not hex becomes NULL, so it cannot reach the SQL.
	 */
	public static function hex( string $hex ): string {
		return preg_match( '/^(?:[0-9a-fA-F]{2})+$/', $hex ) ? "X'" . $hex . "'" : 'NULL';
	}

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
		Settings::refresh_offset();
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
	 * Inserts many rows in one statement and returns the id of the first one.
	 * The rows of one statement get consecutive ids on a single connection, which the
	 * importer relies on to map old ids to new ones.
	 */
	public static function insert_many( string $table, array $rows ): int {
		global $wpdb;

		if ( $rows === [] ) {
			return 0;
		}

		$cols   = array_keys( $rows[0] );
		$groups = [];
		$args   = [];

		foreach ( $rows as $row ) {
			$marks = [];
			foreach ( $cols as $col ) {
				$value = $row[ $col ];
				if ( $value === null ) {
					$marks[] = 'NULL';
					continue;
				}
				if ( in_array( $col, self::BINARY_COLUMNS, true ) ) {
					$marks[] = self::hex( $value );
					continue;
				}
				$marks[] = is_int( $value ) ? '%d' : '%s';
				$args[]  = $value;
			}
			$groups[] = '(' . implode( ', ', $marks ) . ')';
		}

		$sql = "INSERT INTO {$table} (" . implode( ', ', $cols ) . ') VALUES ' . implode( ', ', $groups );
		$wpdb->query( $wpdb->prepare( $sql, ...$args ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert-or-find a dimension row and return its id, in one query.
	 * $refresh lists columns to overwrite when the row already exists.
	 * DB::$inserted is true right after the call when a new row was inserted.
	 */
	public static function upsert( string $table, array $row, array $refresh = [] ): int {
		global $wpdb;

		if ( $wpdb instanceof \WP_SQLite_DB ) {
			return self::upsert_sqlite( $table, $row, $refresh );
		}

		[ $cols, $marks, $args ] = self::values( $row );

		$sets = [ 'id = LAST_INSERT_ID(id)' ];
		foreach ( $refresh as $col => $value ) {
			$sets[] = $col . ' = ' . ( is_int( $value ) ? '%d' : '%s' );
			$args[] = $value;
		}

		$sql = "INSERT INTO {$table} ({$cols}) VALUES ({$marks}) ON DUPLICATE KEY UPDATE " . implode( ', ', $sets );
		$wpdb->query( $wpdb->prepare( $sql, ...$args ) );
		self::$inserted = $wpdb->rows_affected === 1;

		return (int) $wpdb->insert_id;
	}

	/** Set by upsert(): true when the last call inserted a new row. */
	public static bool $inserted = false;

	/**
	 * The SQLite driver (WordPress Studio, Playground) has no LAST_INSERT_ID(expr), and its insert_id and
	 * rows_affected stay stale on a duplicate. So the row is looked up by its unique key instead.
	 */
	private static function upsert_sqlite( string $table, array $row, array $refresh ): int {
		global $wpdb;

		$id = self::find( $table, $row, $refresh );
		if ( $id === 0 ) {
			[ $cols, $marks, $args ] = self::values( $row );
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ({$cols}) VALUES ({$marks})", ...$args ) );
			if ( $wpdb->rows_affected === 1 ) {
				self::$inserted = true;
				return (int) $wpdb->insert_id;
			}
			// Another request inserted the same row a moment ago.
			$id = self::find( $table, $row, $refresh );
		}

		self::$inserted = false;
		if ( $id !== 0 && $refresh !== [] ) {
			$sets = [];
			foreach ( $refresh as $col => $value ) {
				$sets[] = $col . ' = ' . ( is_int( $value ) ? '%d' : '%s' );
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET " . implode( ', ', $sets ) . ' WHERE id = %d', ...array_values( $refresh ), ...[ $id ] ) );
		}

		return $id;
	}

	/** Id of the row with the same unique key: the hash, the country code, or the columns that are not refreshed. */
	private static function find( string $table, array $row, array $refresh ): int {
		global $wpdb;

		$key = isset( $row['hash'] ) ? [ 'hash' ] : ( isset( $row['code'] ) ? [ 'code' ] : array_keys( array_diff_key( $row, $refresh ) ) );
		$where = [];
		$args  = [];
		foreach ( $key as $col ) {
			if ( in_array( $col, self::BINARY_COLUMNS, true ) ) {
				$where[] = $col . ' = ' . self::hex( $row[ $col ] );
				continue;
			}
			$where[] = $col . ' = ' . ( is_int( $row[ $col ] ) ? '%d' : '%s' );
			$args[]  = $row[ $col ];
		}

		$sql = "SELECT id FROM {$table} WHERE " . implode( ' AND ', $where ) . ' LIMIT 1';

		return (int) $wpdb->get_var( $args === [] ? $sql : $wpdb->prepare( $sql, ...$args ) );
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
				$marks[] = self::hex( $value );
				continue;
			}
			$marks[] = is_int( $value ) ? '%d' : '%s';
			$args[]  = $value;
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
  browser_ver SMALLINT UNSIGNED NULL,
  screen_w SMALLINT UNSIGNED NULL,
  screen_h SMALLINT UNSIGNED NULL,
  view_w SMALLINT UNSIGNED NULL,
  view_h SMALLINT UNSIGNED NULL,
  lang VARCHAR(12) NULL,
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
) $c;
CREATE TABLE {$p}heat (
  day MEDIUMINT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  device TINYINT UNSIGNED NOT NULL DEFAULT 0,
  x TINYINT UNSIGNED NOT NULL,
  y SMALLINT UNSIGNED NOT NULL,
  clicks INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, page_id, device, x, y),
  KEY page_day (page_id, day)
) $c;
CREATE TABLE {$p}trails (
  view_id BIGINT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  device TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  samples BLOB NOT NULL,
  PRIMARY KEY  (view_id),
  KEY page_created (page_id, created_at),
  KEY created_at (created_at)
) $c;
CREATE TABLE {$p}pending (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  view_key BINARY(8) NOT NULL,
  at INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY  (id),
  KEY view_key (view_key),
  KEY at (at)
) $c;
CREATE TABLE {$p}debug_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  at INT UNSIGNED NOT NULL,
  reason VARCHAR(40) NOT NULL,
  route VARCHAR(8) NOT NULL,
  ev VARCHAR(8) NOT NULL,
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  vk VARCHAR(16) NOT NULL,
  ua VARCHAR(120) NOT NULL,
  detail VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY  (id),
  KEY vk (vk)
) $c;
CREATE TABLE {$p}cities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  country_id SMALLINT UNSIGNED NOT NULL,
  hash BINARY(8) NOT NULL,
  region VARCHAR(64) NOT NULL DEFAULT '',
  name VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY hash_key (hash)
) $c;
CREATE TABLE {$p}goals (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '',
  type TINYINT UNSIGNED NOT NULL,
  match_type TINYINT UNSIGNED NOT NULL DEFAULT 1,
  value VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY  (id)
) $c;
CREATE TABLE {$p}conversions (
  goal_id SMALLINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY  (goal_id, session_id),
  KEY created_at (created_at),
  KEY session_id (session_id)
) $c;
CREATE TABLE {$p}funnels (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '',
  window_sec INT UNSIGNED NOT NULL DEFAULT 86400,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY  (id)
) $c;
CREATE TABLE {$p}funnel_steps (
  funnel_id SMALLINT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY  (funnel_id, position)
) $c;
CREATE TABLE {$p}d_site (
  day MEDIUMINT UNSIGNED NOT NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  new_visitors INT UNSIGNED NOT NULL DEFAULT 0,
  bounces INT UNSIGNED NOT NULL DEFAULT 0,
  duration_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  events INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day)
) $c;
CREATE TABLE {$p}d_pages (
  day MEDIUMINT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  duration_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
  entrances INT UNSIGNED NOT NULL DEFAULT 0,
  exits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, page_id)
) $c;
CREATE TABLE {$p}d_refs (
  day MEDIUMINT UNSIGNED NOT NULL,
  referrer_id INT UNSIGNED NOT NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  bounces INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, referrer_id)
) $c;
CREATE TABLE {$p}d_geo (
  day MEDIUMINT UNSIGNED NOT NULL,
  country_id SMALLINT UNSIGNED NOT NULL,
  city_id INT UNSIGNED NOT NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, country_id, city_id)
) $c;
CREATE TABLE {$p}d_devices (
  day MEDIUMINT UNSIGNED NOT NULL,
  device TINYINT UNSIGNED NOT NULL,
  browser_id SMALLINT UNSIGNED NOT NULL,
  os_id SMALLINT UNSIGNED NOT NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, device, browser_id, os_id)
) $c;
CREATE TABLE {$p}d_camps (
  day MEDIUMINT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NOT NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 0,
  visitors INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  conversions INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, campaign_id)
) $c;
CREATE TABLE {$p}d_goals (
  day MEDIUMINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NOT NULL,
  conversions INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (day, goal_id)
) $c;";

		return $sql;
	}
}
