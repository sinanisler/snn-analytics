<?php

namespace IAWP\Admin_Page;

use IAWP\Capability_Manager;
use IAWP\Integrations\Integrations;
/** @internal */
class Integrations_Pages extends \IAWP\Admin_Page\Admin_Page
{
    protected function render_page()
    {
        $integrations = new Integrations();
        if (Capability_Manager::show_branded_ui()) {
            echo \IAWPSCOPED\iawp_blade()->run('integrations.integrations', ['integrations' => $integrations]);
        } else {
            echo '<p class="permission-blocked">' . \esc_html__('You do not have permission to view this page.', 'independent-analytics') . '</p>';
        }
    }
}
