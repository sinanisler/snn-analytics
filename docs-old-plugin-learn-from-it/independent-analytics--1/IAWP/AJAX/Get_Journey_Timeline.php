<?php

namespace IAWP\AJAX;

use IAWP\Illuminate_Builder;
use IAWP\Journey\Timeline;
use IAWP\Tables;
/** @internal */
class Get_Journey_Timeline extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_get_journey_timeline';
    }
    protected function action_required_fields() : array
    {
        return ['session_id'];
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    protected function action_callback() : void
    {
        $timeline = new Timeline($this->get_int_field('session_id'));
        $html = \IAWPSCOPED\iawp_render('journeys.timeline', ['timeline' => $timeline]);
        \wp_send_json_success(['html' => $html]);
    }
    private function session()
    {
        $id = $this->get_int_field('session_id');
        if (!\is_int($id)) {
            return null;
        }
        return Illuminate_Builder::new()->select('*')->from(Tables::sessions())->where('session_id', '=', $id)->first();
    }
}
