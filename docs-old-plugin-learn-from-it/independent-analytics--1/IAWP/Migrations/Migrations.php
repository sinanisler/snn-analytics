<?php

namespace IAWP\Migrations;

use IAWP\Database;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Tables;
use IAWP\Utils\Dir;
use IAWP\Utils\Server;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Migrations
{
    /**
     * @return void
     */
    public static function create_or_migrate() : void
    {
        if (self::should_migrate()) {
            Server::increase_max_execution_time();
            \update_option('iawp_is_migrating', '1', \true);
            new \IAWP\Migrations\Migration_1_0();
            new \IAWP\Migrations\Migration_1_6();
            new \IAWP\Migrations\Migration_1_8();
            new \IAWP\Migrations\Migration_1_9();
            new \IAWP\Migrations\Migration_2();
            new \IAWP\Migrations\Migration_3();
            new \IAWP\Migrations\Migration_4();
            new \IAWP\Migrations\Migration_5();
            new \IAWP\Migrations\Migration_6();
            new \IAWP\Migrations\Migration_7();
            new \IAWP\Migrations\Migration_8();
            new \IAWP\Migrations\Migration_9();
            new \IAWP\Migrations\Migration_10();
            new \IAWP\Migrations\Migration_11();
            new \IAWP\Migrations\Migration_12();
            new \IAWP\Migrations\Migration_13();
            new \IAWP\Migrations\Migration_14();
            new \IAWP\Migrations\Migration_15();
            new \IAWP\Migrations\Migration_16();
            new \IAWP\Migrations\Migration_17();
            new \IAWP\Migrations\Migration_18();
            new \IAWP\Migrations\Migration_19();
            new \IAWP\Migrations\Migration_20();
            new \IAWP\Migrations\Migration_21();
            $completed = self::run_step_migrations([new \IAWP\Migrations\Migration_22(), new \IAWP\Migrations\Migration_23(), new \IAWP\Migrations\Migration_24(), new \IAWP\Migrations\Migration_25(), new \IAWP\Migrations\Migration_26(), new \IAWP\Migrations\Migration_27(), new \IAWP\Migrations\Migration_28(), new \IAWP\Migrations\Migration_29(), new \IAWP\Migrations\Migration_30(), new \IAWP\Migrations\Migration_31(), new \IAWP\Migrations\Migration_32(), new \IAWP\Migrations\Migration_33(), new \IAWP\Migrations\Migration_34(), new \IAWP\Migrations\Migration_35(), new \IAWP\Migrations\Migration_36(), new \IAWP\Migrations\Migration_37(), new \IAWP\Migrations\Migration_38(), new \IAWP\Migrations\Migration_39(), new \IAWP\Migrations\Migration_40(), new \IAWP\Migrations\Migration_41(), new \IAWP\Migrations\Migration_42(), new \IAWP\Migrations\Migration_43(), new \IAWP\Migrations\Migration_44(), new \IAWP\Migrations\Migration_45(), new \IAWP\Migrations\Migration_46(), new \IAWP\Migrations\Migration_47(), new \IAWP\Migrations\Migration_48(), new \IAWP\Migrations\Migration_49(), new \IAWP\Migrations\Migration_50(), new \IAWP\Migrations\Migration_51(), new \IAWP\Migrations\Migration_52()]);
            if ($completed === \true) {
                \update_option('iawp_is_migrating', '0', \true);
                \delete_option('iawp_migration_started_at');
                \delete_option('iawp_last_finished_migration_step');
                \delete_option('iawp_migration_error');
                \delete_option('iawp_migration_error_query');
            }
        }
    }
    /**
     * is_migrating is serving multiple purposes. It's also being used to stop ajax requests and dashboard
     * widgets from running when the database version is newer than one that comes with the installed version
     * of independent analytics. The probably should be a method called something `database_ready` that serves
     * this purpose more explicitly.
     *
     * @return bool
     */
    public static function is_migrating() : bool
    {
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating') === '1';
        $is_current = \version_compare($db_version, '52', '=');
        $is_outdated = !$is_current;
        return $is_outdated || $is_migrating;
    }
    public static function is_database_ahead_of_plugin() : bool
    {
        $db_version = \get_option('iawp_db_version', '0');
        return \version_compare($db_version, '52', '>');
    }
    public static function is_actually_migrating() : bool
    {
        return \get_option('iawp_is_migrating') === '1';
    }
    /**
     * @return bool
     */
    public static function should_migrate() : bool
    {
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating') === '1';
        $is_current = \version_compare($db_version, '52', '=');
        $is_outdated = !$is_current;
        return $is_outdated && !$is_migrating;
    }
    public static function handle_migration_18_error() : void
    {
        $directory = \trailingslashit(\wp_upload_dir()['basedir']) . 'iawp/';
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        if ($db_version === '17' && $is_migrating && \is_dir($directory)) {
            \update_option('iawp_db_version', '18', \true);
            \update_option('iawp_is_migrating', '0', \true);
            \delete_option('iawp_migration_started_at');
            \delete_option('iawp_last_finished_migration_step');
            \delete_option('iawp_migration_error');
            \delete_option('iawp_migration_error_query');
            try {
                $directory = \trailingslashit(\wp_upload_dir()['basedir']) . 'iawp/';
                Dir::delete($directory);
            } catch (\Throwable $e) {
            }
        }
    }
    public static function handle_migration_22_error() : void
    {
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        $last_finished_step = \get_option('iawp_last_finished_migration_step', '0');
        $has_error = \get_option('iawp_migration_error_query', null) !== null && \get_option('iawp_migration_error', null) !== null;
        $referrers_table = Query::get_table_name(Query::REFERRERS);
        $has_index = Database::has_index($referrers_table, 'referrers_domain_index');
        if ($db_version === '21' && $is_migrating && $last_finished_step === '0' && $has_error && !$has_index) {
            \update_option('iawp_is_migrating', '0', \true);
            \delete_option('iawp_migration_started_at');
            \delete_option('iawp_last_finished_migration_step');
            \delete_option('iawp_migration_error');
            \delete_option('iawp_migration_error_query');
        }
    }
    public static function handle_migration_29_error() : void
    {
        global $wpdb;
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        $last_finished_step = \get_option('iawp_last_finished_migration_step', '0');
        $has_error = \get_option('iawp_migration_error_query', null) !== null && \get_option('iawp_migration_error', null) !== null;
        if ($db_version === '28' && $is_migrating && $last_finished_step === '5' && $has_error) {
            $sessions_table = Query::get_table_name(Query::SESSIONS);
            $wpdb->query("ALTER TABLE {$sessions_table} DROP COLUMN visitor_id");
            $wpdb->query("ALTER TABLE {$sessions_table} CHANGE COLUMN old_visitor_id visitor_id varchar(32)");
            \delete_option('iawp_last_finished_migration_step');
            \delete_option('iawp_migration_error');
            \delete_option('iawp_migration_error_query');
            \update_option('iawp_is_migrating', '0', \true);
            \delete_option('iawp_migration_started_at');
        }
    }
    public static function handle_migration_45_collation_error() : void
    {
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        $has_error = \get_option('iawp_migration_error_query', null) !== null && \get_option('iawp_migration_error', null) !== null;
        if ($db_version !== '44' || !$is_migrating || !$has_error) {
            return;
        }
        $failed_query = \get_option('iawp_migration_error_query', '');
        if (!\is_string($failed_query)) {
            return;
        }
        $failed_query = \strtolower(\trim($failed_query));
        if (!Str::startsWith($failed_query, 'update')) {
            return;
        }
        try {
            $updated_referrer = Illuminate_Builder::new()->from(Tables::referrers())->whereNotNull('referrer_type_id')->first();
            if ($updated_referrer !== null) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        try {
            Illuminate_Builder::new()->select('type')->from(Tables::referrers())->value('type');
        } catch (\Throwable $e) {
            return;
        }
        try {
            $referrers_table = Tables::referrers();
            Illuminate_Builder::get_connection()->statement("ALTER TABLE {$referrers_table} DROP COLUMN referrer_type_id");
        } catch (\Throwable $e) {
            return;
        }
        \delete_option('iawp_last_finished_migration_step');
        \delete_option('iawp_migration_error');
        \delete_option('iawp_migration_error_original_error_message');
        \delete_option('iawp_migration_error_query');
        \delete_option('iawp_migration_started_at');
        \update_option('iawp_is_migrating', '0', \true);
    }
    public static function handle_migration_46_error() : void
    {
        $db_version = \get_option('iawp_db_version', '0');
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        $last_finished_step = \get_option('iawp_last_finished_migration_step', '0');
        $has_error = \get_option('iawp_migration_error_query', null) !== null && \get_option('iawp_migration_error', null) !== null;
        if ($db_version === '45' && $is_migrating && $last_finished_step === '7' && $has_error) {
            \delete_option('iawp_last_finished_migration_step');
            \delete_option('iawp_migration_error');
            \delete_option('iawp_migration_error_original_error_message');
            \delete_option('iawp_migration_error_query');
            \delete_option('iawp_migration_started_at');
            \update_option('iawp_is_migrating', '0', \true);
        }
    }
    /**
     * @param Step_Migration[] $migrations
     *
     * @return bool
     */
    private static function run_step_migrations(array $migrations) : bool
    {
        foreach ($migrations as $migration) {
            $completed = $migration->migrate();
            if (!$completed) {
                return \false;
            }
        }
        return \true;
    }
}
