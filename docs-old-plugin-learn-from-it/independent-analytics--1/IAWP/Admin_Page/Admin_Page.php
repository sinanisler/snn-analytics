<?php

namespace IAWP\Admin_Page;

use IAWP\Capability_Manager;
use IAWP\Dashboard_Options;
use IAWP\Env;
use IAWP\Migrations\Migrations;
use IAWP\Report_Finder;
/** @internal */
abstract class Admin_Page
{
    protected abstract function render_page();
    /**
     * @param bool $show_report_sidebar Some admin pages, such as the settings page, don't show the report sidebar
     *
     * @return void
     */
    public function render(bool $show_report_sidebar = \true) : void
    {
        if (!Capability_Manager::can_view()) {
            return;
        }
        if (Migrations::is_migrating()) {
            echo \IAWPSCOPED\iawp_render('interrupt.migration-is-running');
            return;
        }
        $options = Dashboard_Options::getInstance();
        $tab = (new Env())->get_tab();
        $examiner_class = \array_key_exists('examiner', $_GET) ? 'iawp-examiner-parent' : '';
        ?>

        <div id="iawp-parent" class="iawp-parent <?php 
        echo \esc_attr($tab);
        ?> <?php 
        echo \esc_attr($examiner_class);
        ?>">
            <div id="iawp-layout" class="iawp-layout <?php 
        echo $options->is_sidebar_collapsed() ? 'collapsed' : '';
        ?>">
                <?php 
        if ($show_report_sidebar) {
            echo \IAWPSCOPED\iawp_render('partials.sidebar', ['report_finder' => Report_Finder::new(), 'is_white_labeled' => Capability_Manager::show_white_labeled_ui(), 'can_edit_settings' => Capability_Manager::can_edit(), 'can_view_all_analytics' => Capability_Manager::can_view_all_analytics()]);
        }
        ?>
                <div class="iawp-layout-main">
                    <div class="iawp-tab-content">
                        <div id="iawp-dashboard" class="iawp-dashboard">
                            <?php 
        $this->render_page();
        ?>
                        </div>
                    </div>
                    <div class="modal-background"></div>
                    <div id="loading-icon" class="loading-icon">
                        <img src="<?php 
        echo \esc_url(\IAWPSCOPED\iawp_url_to('img/loading.svg'));
        ?>" />
                    </div>
                    <button id="scroll-to-top" class="scroll-to-top"><span class="dashicons dashicons-arrow-up-alt"></span></button>
                </div>
            </div>
        </div>

        <?php 
    }
}
