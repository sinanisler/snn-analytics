<?php

namespace IAWP\Migrations;

use IAWP\Database;
use IAWP\Query;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
abstract class Step_Migration
{
    protected $tables = Tables::class;
    protected abstract function database_version() : int;
    protected abstract function queries() : array;
    public function migrate() : bool
    {
        $current_db_version = \get_option('iawp_db_version', '0');
        if (\version_compare($current_db_version, \strval($this->database_version()), '>=')) {
            return \true;
        }
        \update_option('iawp_migration_started_at', \time(), \true);
        try {
            $completed = $this->run_queries();
        } catch (\Throwable $error) {
            $completed = \false;
            \update_option('iawp_migration_error_original_error_message', 'Unable to generate migration queries: ' . $error->getMessage(), \true);
            \update_option('iawp_migration_error', 'Unable to generate migration queries.', \true);
            \update_option('iawp_migration_error_query', $error->getMessage(), \true);
        }
        if ($completed) {
            \update_option('iawp_db_version', $this->database_version(), \true);
            \delete_option('iawp_migration_auto_fixed');
        }
        return $completed;
    }
    public function character_set() : string
    {
        return Database::character_set();
    }
    public function collation() : string
    {
        return Database::collation();
    }
    protected function drop_table_if_exists(string $table_name) : string
    {
        return "\n            DROP TABLE IF EXISTS {$table_name};\n        ";
    }
    protected function get_collation_statement(?string $from, ?string $to) : string
    {
        if (!$from || !$to) {
            return '';
        }
        if ($from === $to) {
            return '';
        }
        $from_character_set = $this->extract_character_set($from);
        $to_character_set = $this->extract_character_set($to);
        if (!$from_character_set || !$to_character_set || $from_character_set !== $to_character_set) {
            return '';
        }
        return " COLLATE {$from} ";
    }
    private function extract_character_set(string $collation) : ?string
    {
        if (!Str::of($collation)->test('/\\A[a-zA-Z0-9]+_/')) {
            return null;
        }
        return Str::before($collation, '_');
    }
    private function run_queries() : bool
    {
        global $wpdb;
        $queries = $this->queries();
        foreach ($queries as $index => $query) {
            // Skip the step if there is no query to run
            if (\is_null($query)) {
                \update_option('iawp_last_finished_migration_step', $index + 1, \true);
                continue;
            }
            try {
                $initial_response = $wpdb->query($query);
            } catch (\Throwable $error) {
                $max_connections_error = 'SQLSTATE[HY000] [1203]';
                if (Str::startsWith($error->getMessage(), $max_connections_error)) {
                    $initial_response = \false;
                } else {
                    throw $error;
                }
            }
            if ($initial_response === \false) {
                \sleep(1);
                \update_option('iawp_migration_error_original_error_message', \trim($wpdb->last_error), \true);
                $is_connected = $wpdb->check_connection(\false);
                if (!$is_connected) {
                    // There is no database connection at this point, so options cannot be updated
                    return \false;
                }
                $retry_response = $wpdb->query($query);
                if ($retry_response === \false) {
                    // You cannot take these variable values and inline them below. The calls to
                    // update_option use $wpdb, so last_error and last_query will be altered
                    $last_error = \trim($wpdb->last_error);
                    $last_query = \trim($wpdb->last_query);
                    \update_option('iawp_migration_error', $last_error, \true);
                    \update_option('iawp_migration_error_query', $last_query, \true);
                    return \false;
                }
            }
            \update_option('iawp_last_finished_migration_step', $index + 1, \true);
        }
        return \true;
    }
}
