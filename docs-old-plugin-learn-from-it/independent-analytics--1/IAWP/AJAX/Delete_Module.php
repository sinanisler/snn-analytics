<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Delete_Module extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_delete_module';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['module_id'];
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
        $module = Module::get_saved_module($this->get_field('module_id'));
        if ($module === null) {
            \wp_send_json_error(['error' => 'module_not_found'], 404);
        }
        if ($module->delete()) {
            \wp_send_json_success();
        } else {
            \wp_send_json_error(['error' => 'unable_to_delete'], 400);
        }
    }
}
