<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class Delete_Link extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_delete_link';
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
        $link_rule_id = $this->get_int_field('id');
        if (\is_null($link_rule_id)) {
            \wp_send_json_error([], 400);
        }
        // Delete the link rule
        Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $link_rule_id)->delete();
        // Delete the links
        Illuminate_Builder::new()->from(Tables::links())->where('link_rule_id', '=', $link_rule_id)->delete();
        // Delete orphaned click targets
        $links = Tables::links();
        Illuminate_Builder::new()->from(Tables::click_targets(), 'click_targets')->leftJoin("{$links} AS links", 'links.click_target_id', '=', 'click_targets.click_target_id')->whereNull('links.click_target_id')->delete();
        // Delete orphaned clicked links
        $links = Tables::links();
        Illuminate_Builder::new()->from(Tables::clicked_links(), 'clicked_links')->leftJoin("{$links} AS links", 'links.id', '=', 'clicked_links.link_id')->whereNull('links.click_target_id')->delete();
        // Delete orphaned clicks
        $clicked_links_table = Tables::clicked_links();
        Illuminate_Builder::new()->from(Tables::clicks(), 'clicks')->leftJoin("{$clicked_links_table} AS clicked_links", 'clicked_links.click_id', '=', 'clicks.click_id')->whereNull('clicked_links.click_id')->delete();
        \wp_send_json_success(['id' => $link_rule_id]);
    }
}
