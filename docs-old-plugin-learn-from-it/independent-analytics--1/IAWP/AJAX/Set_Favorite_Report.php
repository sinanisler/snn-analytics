<?php

namespace IAWP\AJAX;

use IAWP\Report_Finder;
/** @internal */
class Set_Favorite_Report extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_set_favorite_report';
    }
    /**
     * @inheritDoc
     */
    protected function action_callback() : void
    {
        $id = $this->get_field('id');
        $type = $this->get_field('type');
        if (Report_Finder::new()->fetch_report_by_id($id) !== null) {
            \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_id');
            \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_type');
            \update_user_meta(\get_current_user_id(), 'iawp_favorite_report_id', $id);
            \wp_send_json_success([]);
        }
        if (Report_Finder::new()->get_base_report_for_type($type) !== null) {
            \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_id');
            \delete_user_meta(\get_current_user_id(), 'iawp_favorite_report_type');
            \update_user_meta(\get_current_user_id(), 'iawp_favorite_report_type', $type);
            \wp_send_json_success([]);
        }
        \wp_send_json_error([], 400);
    }
}
