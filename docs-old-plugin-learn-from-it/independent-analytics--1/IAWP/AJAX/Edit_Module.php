<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Edit_Module extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_edit_module';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['module_id', 'fields'];
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
        $module_attributes = $this->get_array_field('fields');
        if (!\is_array($module_attributes)) {
            \wp_send_json_error(['error' => 'invalid_module'], 400);
        }
        $module = Module::get_saved_module($this->get_field('module_id'));
        if ($module === null) {
            \wp_send_json_error(['error' => 'module_not_found'], 404);
        }
        $module->update($module_attributes);
        if ($module->save()) {
            \wp_send_json_success(['module_html' => $module->get_module_html()]);
        } else {
            \wp_send_json_error(['error' => 'invalid_module'], 400);
        }
    }
}
