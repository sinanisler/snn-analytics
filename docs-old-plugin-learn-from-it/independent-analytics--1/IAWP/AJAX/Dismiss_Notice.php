<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
/** @internal */
class Dismiss_Notice extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_dismiss_notice';
    }
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            return;
        }
        $id = $this->get_field('id');
        if ($id == 'iawp_need_clear_cache') {
            \update_option('iawp_need_clear_cache', \false, \true);
        } elseif ($id == 'iawp_show_gsg') {
            \update_option('iawp_show_gsg', '0', \true);
        } elseif ($id == 'iawp_clicks_sync_notice') {
            \update_option('iawp_clicks_sync_notice', \true, \true);
        } elseif ($id == 'enable-logged-in-tracking') {
            \update_option('iawp_logged_in_tracking_notice', \true, \true);
        }
        return;
    }
}
