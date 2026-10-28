<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Click_Tracking;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class Archive_Link extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_archive_link';
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            return;
        }
        $id = $this->get_field('id');
        $link_rule = Click_Tracking\Link_Rule::find($id);
        if (\is_null($link_rule)) {
            \wp_send_json_error([]);
        }
        Click_Tracking\Link_Rule_Finder::require_cleared_cache();
        $link_rule->toggle_active();
        Illuminate_Builder::new()->from(Tables::link_rules())->where('is_active', '=', $link_rule->is_active() ? 1 : 0)->increment('position');
        Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $link_rule->id())->update(['position' => 0]);
        // Send response
        $response = \IAWPSCOPED\iawp_blade()->run('click-tracking.link', ['link' => $link_rule->to_array(), 'types' => Click_Tracking::types(), 'extensions' => Click_Tracking::extensions(), 'protocols' => Click_Tracking::protocols()]);
        echo \wp_json_encode($response);
    }
}
