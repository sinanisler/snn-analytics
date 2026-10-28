<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class Sort_Links extends \IAWP\AJAX\AJAX
{
    /**
     * @return array
     */
    protected function action_required_fields() : array
    {
        return ['ids'];
    }
    /**
     * @return string
     */
    protected function action_name() : string
    {
        return 'iawp_sort_links';
    }
    /**
     * @return void
     */
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            \wp_send_json_error([], 400);
        }
        foreach ($this->get_field('ids') as $index => $id) {
            Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $id)->update(['position' => $index]);
        }
        \wp_send_json_success();
    }
}
