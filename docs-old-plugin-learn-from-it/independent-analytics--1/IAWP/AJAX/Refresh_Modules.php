<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Refresh_Modules extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_refresh_modules';
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    /**
     * @inheritDoc
     */
    protected function action_callback() : void
    {
        Module::queue_full_module_refresh();
        $modules = \array_map(function (Module $module) {
            return ['id' => $module->id(), 'html' => $module->get_module_html()];
        }, Module::get_saved_modules());
        \wp_send_json_success(['modulesRefreshedAt' => Module::last_refreshed_at(), 'modules' => $modules]);
    }
}
