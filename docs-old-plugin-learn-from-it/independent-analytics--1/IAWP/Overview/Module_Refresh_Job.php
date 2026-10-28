<?php

namespace IAWP\Overview;

use IAWP\Cron_Job;
/** @internal */
class Module_Refresh_Job extends Cron_Job
{
    protected $name = 'iawp_module_refresh';
    protected $interval = 'hourly';
    public function handle() : void
    {
        \update_option('iawp_should_refresh_modules', '1', \true);
    }
}
