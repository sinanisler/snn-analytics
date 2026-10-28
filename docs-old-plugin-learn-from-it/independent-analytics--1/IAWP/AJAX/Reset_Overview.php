<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Overview\Modules\Module;
/** @internal */
class Reset_Overview extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_reset_overview';
    }
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            \wp_send_json_error([], 400);
        }
        $confirmation = $this->get_field('confirmation');
        $valid = \strtolower($confirmation) == 'reset overview report';
        if (!$valid) {
            \wp_send_json_error([], 400);
        }
        Module::reset();
        \wp_send_json_success([]);
    }
}
