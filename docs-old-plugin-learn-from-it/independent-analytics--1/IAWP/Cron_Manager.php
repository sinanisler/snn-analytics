<?php

namespace IAWP;

use IAWP\Utils\Salt;
use IAWP\Utils\Timezone;
/** @internal */
class Cron_Manager
{
    public function __construct()
    {
        \add_action('update_option_iawp_visitor_salt_refresh_interval', [$this, 'schedule'], 10, 0);
        \add_action('add_option_iawp_visitor_salt_refresh_interval', [$this, 'schedule'], 10, 0);
        \add_action('iawp_refresh_salt', [$this, 'handle']);
    }
    public function schedule()
    {
        \wp_unschedule_hook('iawp_refresh_salt');
        $interval = \IAWP\VisitorSaltRefreshInterval::interval();
        if ($interval === 'never') {
            return;
        }
        $refresh_time = new \DateTime('tomorrow midnight', Timezone::site_timezone());
        \wp_schedule_event($refresh_time->getTimestamp(), $interval, 'iawp_refresh_salt');
    }
    public function handle()
    {
        Salt::refresh_visitor_token_salt();
    }
}
