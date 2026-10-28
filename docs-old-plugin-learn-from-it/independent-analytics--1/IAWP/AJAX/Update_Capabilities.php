<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
/** @internal */
class Update_Capabilities extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_update_capabilities';
    }
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            return;
        }
        $capabilities = $this->get_field('capabilities');
        Capability_Manager::edit_all_capabilities($capabilities);
        \update_option('iawp_white_label', $this->get_boolean_field('white_label'), \true);
    }
}
