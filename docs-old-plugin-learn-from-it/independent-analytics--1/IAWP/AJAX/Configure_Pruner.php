<?php

namespace IAWP\AJAX;

use IAWP\Data_Pruning\Pruner;
use IAWP\Data_Pruning\Pruning_Scheduler;
/** @internal */
class Configure_Pruner extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_configure_pruner';
    }
    protected function action_callback() : void
    {
        $cutoff = $this->get_field('pruningCutoff');
        $is_confirmed = $this->get_boolean_field('isConfirmed');
        $pruning_scheduler = new Pruning_Scheduler();
        if ($cutoff !== 'disabled' && !$is_confirmed) {
            \wp_send_json_error(['confirmationText' => $pruning_scheduler->get_pruning_description($cutoff)]);
        }
        $was_updated = $pruning_scheduler->update_pruning_cutoff($cutoff);
        if ($was_updated && $pruning_scheduler->is_enabled()) {
            Pruner::prune();
        }
        if ($was_updated) {
            \wp_send_json_success(['isEnabled' => $pruning_scheduler->is_enabled(), 'statusMessage' => $pruning_scheduler->status_message()]);
        } else {
            \wp_send_json_error([]);
        }
    }
}
