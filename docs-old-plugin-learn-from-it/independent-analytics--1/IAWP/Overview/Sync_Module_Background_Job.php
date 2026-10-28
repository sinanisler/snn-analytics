<?php

namespace IAWP\Overview;

use IAWP\Overview\Modules\Module;
use IAWP\Utils\Security;
use IAWP\Utils\WP_Async_Request;
/** @internal */
class Sync_Module_Background_Job extends WP_Async_Request
{
    protected $action = 'iawp_sync_module_background_job';
    protected function handle($data = [])
    {
        $ids = Security::array($data['ids']);
        foreach ($ids as $id) {
            $module = Module::get_saved_module($id);
            // Unable to find a module to sync
            if ($module === null) {
                continue;
            }
            $module->refresh_dataset();
        }
    }
}
