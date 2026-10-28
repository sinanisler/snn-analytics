<?php

namespace IAWP\Admin_Page;

use IAWP\Capability_Manager;
use IAWP\Click_Tracking;
/** @internal */
class Click_Tracking_Page extends \IAWP\Admin_Page\Admin_Page
{
    protected function render_page()
    {
        if (Capability_Manager::can_edit()) {
            Click_Tracking::render_menu();
        } else {
            echo '<p class="permission-blocked">' . \esc_html__('You do not have permission to edit the click tracking settings.', 'independent-analytics') . '</p>';
        }
    }
}
