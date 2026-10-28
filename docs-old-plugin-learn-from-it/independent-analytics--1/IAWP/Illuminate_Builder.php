<?php

namespace IAWP;

use IAWPSCOPED\Illuminate\Database\Capsule\Manager as Capsule;
use IAWPSCOPED\Illuminate\Database\Connection;
use IAWPSCOPED\Illuminate\Database\ConnectionInterface;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use PDO;
/**
 * Connects to the WordPress database using Illuminate from Laravel
 *
 * Usage:
 *
 * $builder = Illuminate_Builder::get_builder();
 * @internal
 */
class Illuminate_Builder
{
    private static $connection = null;
    public static function get_connection() : ConnectionInterface
    {
        if (self::$connection === null) {
            self::$connection = self::make_connection();
        }
        return self::$connection;
    }
    public static function new() : Builder
    {
        if (self::$connection === null) {
            self::$connection = self::make_connection();
        }
        return new Builder(self::$connection);
    }
    public static function ray(Builder $builder)
    {
        $add_slashes = \str_replace('?', "'?'", $builder->toSql());
        $escape_mysql_format_percentages = \str_replace('%', '%%', $add_slashes);
        $replace_question_marks = \str_replace('?', '%s', $escape_mysql_format_percentages);
        if (\function_exists('IAWPSCOPED\\ray')) {
            return ray(\vsprintf($replace_question_marks, $builder->getBindings()));
        }
    }
    private static function make_connection() : Connection
    {
        global $wpdb;
        $raw_host = $wpdb->dbhost;
        $database_name = $wpdb->dbname;
        $charset = $wpdb->dbcharset ?? 'utf8';
        $username = $wpdb->dbuser;
        $password = $wpdb->dbpassword;
        $host_data = $wpdb->parse_db_host($raw_host);
        list($host, $port, $socket, $is_ipv6) = $host_data;
        if ($is_ipv6 && \extension_loaded('mysqlnd')) {
            $host = "[{$host}]";
        }
        $charset_collate = $wpdb->determine_charset($charset, '');
        $charset = $charset_collate['charset'];
        // $collation       = $charset_collate['collate'];
        // Collation is no longer added due to issue with WP Rocket with testing 1.23.0. In the future,
        // $connection_options should have the collation property added only if it's defined. It should
        // not get set for empty strings.
        $connection_options = ['driver' => 'mysql', 'database' => $database_name, 'username' => $username, 'password' => $password, 'charset' => $charset, 'prefix' => '', 'options' => self::ssl_options()];
        // Ensures that we use an SSL database connection when WordPress is using one.
        // WordPress does SSL, but not with certificate verification. We do the same.
        if (self::should_use_ssl()) {
            $connection_options['options'][PDO::MYSQL_ATTR_SSL_CA] = \true;
            $connection_options['options'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = \false;
        }
        if (isset($socket)) {
            $connection_options['unix_socket'] = $socket;
        } else {
            $connection_options['host'] = $host;
            if (isset($port)) {
                $connection_options['port'] = $port;
            }
        }
        $capsule = new Capsule();
        $capsule->addConnection($connection_options);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = $capsule->getConnection();
        self::mariadb_connection_tweaks($connection);
        return $connection;
    }
    private static function mariadb_connection_tweaks(Connection $connection) : void
    {
        $pdo = $connection->getPdo();
        $version = $pdo->query("SELECT VERSION() AS version")->fetchColumn();
        $is_mariadb = \is_int(\strpos(\strtolower($version), 'mariadb'));
        if (!$is_mariadb) {
            return;
        }
        try {
            // Disable the lateral derived optimization for MariaDB users. This was cause slowdowns when filtering even with few views.
            // https://mariadb.com/kb/en/lateral-derived-optimization/
            $pdo->exec("SET optimizer_switch='split_materialized=off'");
            // Disable ONLY_FULL_GROUP_BY which MariaDB treats differently than MySQL.
            $pdo->exec("SET sql_mode = (SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
        } catch (\Throwable $exception) {
        }
    }
    private static function ssl_options() : array
    {
        if (self::should_use_ssl()) {
            if (!\defined('MYSQL_SSL_CA')) {
                return [PDO::MYSQL_ATTR_SSL_CA => \true, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => \false];
            }
            return [PDO::MYSQL_ATTR_SSL_CA => \MYSQL_SSL_CA, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => \true];
        }
        if (self::should_use_ssl_without_certificate_verification()) {
            return [PDO::MYSQL_ATTR_SSL_CA => \true, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => \false];
        }
        return [];
    }
    private static function should_use_ssl() : bool
    {
        if (!\defined('MYSQL_CLIENT_FLAGS')) {
            return \false;
        }
        if (\MYSQL_CLIENT_FLAGS & \MYSQLI_CLIENT_SSL) {
            return \true;
        }
        return \false;
    }
    private static function should_use_ssl_without_certificate_verification() : bool
    {
        if (!\defined('MYSQL_CLIENT_FLAGS')) {
            return \false;
        }
        if (\MYSQL_CLIENT_FLAGS & \MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT) {
            return \true;
        }
        return \false;
    }
}
