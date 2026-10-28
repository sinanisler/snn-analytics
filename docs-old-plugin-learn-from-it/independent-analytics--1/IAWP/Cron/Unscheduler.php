<?php

namespace IAWP\Cron;

use IAWPSCOPED\Illuminate\Support\Collection;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Unscheduler
{
    /**
     * Unschedule all cron events for Independent Analytics
     *
     * @return void
     */
    public static function unschedule_all_events()
    {
        $prefix = 'iawp_';
        $raw_cron_data = \get_option('cron');
        if (!\is_array($raw_cron_data)) {
            return;
        }
        $event_names = Collection::make();
        foreach ($raw_cron_data as $timestamp => $events) {
            if (!\is_int($timestamp) || !\is_array($events)) {
                continue;
            }
            foreach ($events as $name => $details) {
                if (!Str::startsWith($name, $prefix)) {
                    continue;
                }
                $event_names->push($name);
            }
        }
        $event_names->unique()->values()->each(function ($event_name) {
            \wp_unschedule_hook($event_name);
        });
    }
}
