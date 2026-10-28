<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Save_Module extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_save_module';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['module'];
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
        $module_attributes = $this->get_array_field('module');
        if (!\is_array($module_attributes)) {
            \wp_send_json_error(['error' => 'invalid_module'], 400);
        }
        $module = Module::new($module_attributes['module_type'] ?? '', $module_attributes);
        if ($module->save()) {
            $this->delete_module_to_swap();
            \wp_send_json_success(['module_html' => $module->get_module_html()]);
        } else {
            \wp_send_json_error(['error' => 'invalid_module'], 400);
        }
    }
    private function delete_module_to_swap()
    {
        $module_id = $this->get_field('moduleToSwap');
        if (\strlen($module_id) === 0) {
            return;
        }
        $module = Module::get_saved_module($module_id);
        if (\is_object($module)) {
            $module->delete();
        }
    }
}
