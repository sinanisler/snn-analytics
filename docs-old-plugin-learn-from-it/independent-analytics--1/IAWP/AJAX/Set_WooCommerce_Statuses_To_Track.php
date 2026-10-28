<?php

namespace IAWP\AJAX;

use IAWP\Ecommerce\WooCommerce_Status_Manager;
/** @internal */
class Set_WooCommerce_Statuses_To_Track extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_set_woocommerce_statuses_to_track';
    }
    protected function action_callback() : void
    {
        $statuses_to_track = $this->get_field('statusesToTrack');
        $reset_to_default = $this->get_boolean_field('resetToDefault');
        $status_manager = new WooCommerce_Status_Manager();
        if ($reset_to_default) {
            $status_manager->reset_tracked_statuses();
            $status_manager->update_order_records_based_on_tracked_statuses();
        } elseif (\is_array($statuses_to_track)) {
            $status_manager->set_tracked_statuses($statuses_to_track);
            $status_manager->update_order_records_based_on_tracked_statuses();
        }
        \wp_send_json_success(['statusesToTrack' => $status_manager->get_statuses()]);
    }
}
