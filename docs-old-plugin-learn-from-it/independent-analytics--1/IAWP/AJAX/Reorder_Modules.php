<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Reorder_Modules extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_reorder_modules';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['module_ids'];
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
        $module_ids = $this->get_array_field('module_ids');
        if (\is_array($module_ids)) {
            Module::set_module_order($module_ids);
            \wp_send_json_success();
        } else {
            \wp_send_json_error([], 400);
        }
    }
}
