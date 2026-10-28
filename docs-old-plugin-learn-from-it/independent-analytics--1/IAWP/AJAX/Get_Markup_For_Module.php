<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Get_Markup_For_Module extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_get_markup_for_module';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['id'];
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
        $module = Module::get_saved_module($this->get_field('id'));
        if ($module === null) {
            \wp_send_json_error(['error' => 'module_not_found'], 404);
        }
        \wp_send_json_success(['id' => $module->id(), 'editor_html' => $module->get_editor_html(), 'module_html' => $module->get_module_html(), 'has_dataset' => $module->has_dataset()]);
    }
}
