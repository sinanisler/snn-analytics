<?php

namespace IAWP\AJAX;

/** @internal */
class Pause_Email_Reports extends \IAWP\AJAX\AJAX
{
    /**
     * @return string
     */
    protected function action_name() : string
    {
        return 'iawp_pause_email_reports';
    }
    /**
     * @return void
     */
    protected function action_callback() : void
    {
        $paused = $this->get_boolean_field('paused') === \true;
        if ($paused) {
            \update_option('iawp_email_report_paused', '1', \true);
        } else {
            \update_option('iawp_email_report_paused', '0', \true);
        }
    }
}
