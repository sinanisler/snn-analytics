<?php

namespace IAWP\Overview;

use IAWP\Overview\Modules\Module;
/** @internal */
class Overview
{
    public function __construct()
    {
    }
    public function get_report_html() : string
    {
        // Get modules first, so defaults can be set before last_refreshed_at is called...
        $modules = Module::get_saved_modules();
        return \IAWPSCOPED\iawp_render('overview.overview', ['overview' => $this, 'last_refreshed_at' => Module::last_refreshed_at(), 'saved_modules' => $modules, 'template_modules' => Module::get_template_modules()]);
    }
}
