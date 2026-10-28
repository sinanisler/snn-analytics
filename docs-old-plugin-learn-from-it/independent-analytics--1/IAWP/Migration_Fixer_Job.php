<?php

namespace IAWP;

use IAWPSCOPED\Carbon\CarbonImmutable;
/** @internal */
class Migration_Fixer_Job extends \IAWP\Cron_Job
{
    protected $name = 'iawp_migration_fixer';
    protected $interval = 'hourly';
    public function handle() : void
    {
        // Not migrating? Do nothing.
        $is_migrating = \get_option('iawp_is_migrating', '0') === '1';
        if (!$is_migrating) {
            return;
        }
        // Not a valid timestamp? Do nothing.
        $timestamp = \get_option('iawp_migration_started_at', '0');
        if (!(\is_string($timestamp) && \ctype_digit($timestamp) && $timestamp !== '0')) {
            return;
        }
        // Running for less than an hour? Bail.
        $date = CarbonImmutable::createFromTimestamp($timestamp);
        $minutes_running = $date->diffInMinutes();
        if ($minutes_running < 60) {
            return;
        }
        // Was there an error? Do nothing.
        $has_error = \get_option('iawp_migration_error_query', null) !== null && \get_option('iawp_migration_error', null) !== null;
        if ($has_error) {
            return;
        }
        // Dangerous migrations are the ones we believe could result in data loss if automatically
        // restarted. Do nothing when one of these is seen.
        $dangerous_migrations = [23, 45, 46, 47, 48, 49, 51];
        $running_migration = \intval(\get_option('iawp_db_version', '0')) + 1;
        // Did it fail on a dangerous migration? Do nothing.
        if (\in_array($running_migration, $dangerous_migrations)) {
            return;
        }
        // Have we already tried to automatically fix the migration? Do nothing.
        if (\get_option('iawp_migration_auto_fixed', '0') === '1') {
            return;
        }
        // Track that the migration is rerunning because of an auto fix
        \update_option('iawp_migration_auto_fixed', '1', \true);
        // Reset the migration
        \update_option('iawp_is_migrating', '0', \true);
        \delete_option('iawp_migration_started_at');
        \delete_option('iawp_last_finished_migration_step');
        \delete_option('iawp_migration_error');
        \delete_option('iawp_migration_error_query');
    }
}
