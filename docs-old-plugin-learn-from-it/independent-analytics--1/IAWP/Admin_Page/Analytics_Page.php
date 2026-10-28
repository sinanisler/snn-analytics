<?php

namespace IAWP\Admin_Page;

use IAWP\Capability_Manager;
use IAWP\Chart;
use IAWP\Dashboard_Options;
use IAWP\Database;
use IAWP\Env;
use IAWP\Examiner\Header;
use IAWP\Map;
use IAWP\Map_Data;
use IAWP\Overview\Overview;
use IAWP\Plugin_Conflict_Detector;
use IAWP\Quick_Stats;
use IAWP\Real_Time;
use IAWP\Report;
use IAWP\Report_Finder;
use IAWP\Tables\Table;
use IAWP\Tables\Table_Journeys;
use IAWP\Utils\Request;
use IAWP\Utils\Security;
use IAWPSCOPED\Illuminate\Support\Arr;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Analytics_Page extends \IAWP\Admin_Page\Admin_Page
{
    protected function render_page()
    {
        $options = Dashboard_Options::getInstance();
        $date_rage = $options->get_date_range();
        $tab = (new Env())->get_tab();
        $report = Report_Finder::new()->fetch_current_report();
        $is_showing_skeleton_ui = $report instanceof Report && $report->has_filters();
        // Real-time is its own thing
        if ($tab === 'real-time') {
            $real_time = new Real_Time();
            $real_time->render_real_time_analytics();
            return;
        }
        // Overview is its own thing
        if ($tab === 'overview') {
            $overview = new Overview();
            echo $overview->get_report_html();
            $this->notices();
            return;
        }
        // User journeys is a very difference animal
        if ($tab === 'journeys') {
            $this->user_journey_interface();
            return;
        }
        $table_class = Env::get_table($tab);
        $table = new $table_class($options->group());
        $sort_configuration = $table->sanitize_sort_parameters($options->sort_column(), $options->sort_direction());
        $hide_unfiltered_statistics = \false;
        $rows = null;
        $examiner_model = null;
        if ($options->is_examiner()) {
            $rows_class = $table->group()->rows_class();
            $rows = new $rows_class($options->get_date_range(), $sort_configuration);
            $id = Request::query_int('examiner');
            $rows->limit_to($id);
            $examiner_model = $rows->rows()[0];
            $hide_unfiltered_statistics = \true;
        }
        $statistics_class = $table->group()->statistics_class();
        $statistics = new $statistics_class($date_rage, $rows, $options->chart_interval());
        $stats = new Quick_Stats($statistics, \false, $is_showing_skeleton_ui, $hide_unfiltered_statistics);
        // Never show the map when loading the geo examiner. It'll only ever show a single country anyway.
        if ($tab === 'geo' && !$options->is_examiner()) {
            $table_data_class = $table->group()->rows_class();
            $geo_data = new $table_data_class($date_rage, $table->sanitize_sort_parameters());
            $map_data = new Map_Data($geo_data->rows());
            $chart = new Map($map_data->get_country_data(), null, $is_showing_skeleton_ui);
        } else {
            $chart = new Chart($statistics, \false, $is_showing_skeleton_ui);
        }
        $this->interface($table, $stats, $chart, $examiner_model);
    }
    private function user_journey_interface()
    {
        $options = Dashboard_Options::getInstance();
        $table = new Table_Journeys();
        $sort_configuration = $table->sanitize_sort_parameters('created_at', 'DESC');
        $header = \IAWPSCOPED\iawp_blade()->run('partials.report-header', ['report' => Report_Finder::new()->fetch_current_report(), 'can_edit' => Capability_Manager::can_edit()]);
        ?>
        <div data-controller="report"
             data-report-is-examiner-value="<?php 
        echo $options->is_examiner() ? '1' : '0';
        ?>"
             data-report-name-value="<?php 
        echo Security::string($options->report_name());
        ?>"
             data-report-report-name-value="<?php 
        echo Security::string($table->group()->singular());
        ?>"
             data-report-relative-range-id-value="<?php 
        echo Security::attr($options->relative_range_id());
        ?>"
             data-report-exact-start-value="<?php 
        echo Security::attr($options->start());
        ?>"
             data-report-exact-end-value="<?php 
        echo Security::attr($options->end());
        ?>"
             data-report-group-value="<?php 
        echo Security::attr($table->group()->id());
        ?>"
             data-report-filters-value="<?php 
        echo \esc_attr(Security::json_encode($options->raw_filters()));
        ?>"
             data-report-filter-logic-value="<?php 
        echo Security::attr($options->filter_logic());
        ?>"
             data-report-chart-interval-value="<?php 
        echo Security::attr($options->chart_interval()->id());
        ?>"
             data-report-sort-column-value="<?php 
        echo Security::attr($sort_configuration->column());
        ?>"
             data-report-sort-direction-value="<?php 
        echo Security::attr($sort_configuration->direction());
        ?>"
             data-report-columns-value="<?php 
        echo \esc_attr(Security::json_encode($table->visible_column_ids()));
        ?>"
             data-report-quick-stats-value="<?php 
        echo \esc_attr(Security::json_encode($options->visible_quick_stats()));
        ?>"
             data-report-primary-chart-metric-id-value="<?php 
        echo \esc_attr($options->primary_chart_metric_id());
        ?>"
             data-report-secondary-chart-metric-id-value="<?php 
        echo \esc_attr($options->secondary_chart_metric_id());
        ?>"
        >
            <div id="report-header-container" class="report-header-container">
                <?php 
        echo $header;
        ?>
                <?php 
        $table->output_report_toolbar();
        ?>
                <div class="modal-background"></div>
            </div>
            <?php 
        echo $table->get_table_toolbar_markup();
        ?>
            <div class="user-journeys">
                <?php 
        echo $table->get_table_markup($sort_configuration->column(), $sort_configuration->direction());
        ?>
            </div>
        </div>
        <?php 
        $this->notices();
    }
    private function interface(Table $table, $stats, $chart, $examiner_model = null)
    {
        $options = Dashboard_Options::getInstance();
        $sort_configuration = $table->sanitize_sort_parameters($options->sort_column(), $options->sort_direction());
        $header = \IAWPSCOPED\iawp_blade()->run('partials.report-header', ['report' => Report_Finder::new()->fetch_current_report(), 'can_edit' => Capability_Manager::can_edit()]);
        $examiner_tabs = '';
        if ($examiner_model) {
            $header = Header::html($table, $examiner_model);
            $available_tabs = Collection::make($this->examiner_tabs())->filter(function (array $table) use($examiner_model) {
                return $table['table_type'] !== $examiner_model->table_type();
            })->values()->all();
            $current = Arr::first($available_tabs);
            $examiner_tabs = \IAWPSCOPED\iawp_blade()->run('examiner.table-tabs', ['tables' => $available_tabs, 'active' => $current['table_type']]);
            $table_class = Env::get_table($current['table_type']);
            $table = new $table_class();
        }
        ?>
        <div data-controller="report"
             data-report-is-examiner-value="<?php 
        echo $options->is_examiner() ? '1' : '0';
        ?>"
             data-report-name-value="<?php 
        echo Security::string($options->report_name());
        ?>"
             data-report-report-name-value="<?php 
        echo Security::string($table->group()->singular());
        ?>"
             data-report-relative-range-id-value="<?php 
        echo Security::attr($options->relative_range_id());
        ?>"
             data-report-exact-start-value="<?php 
        echo Security::attr($options->start());
        ?>"
             data-report-exact-end-value="<?php 
        echo Security::attr($options->end());
        ?>"
             data-report-group-value="<?php 
        echo Security::attr($table->group()->id());
        ?>"
             data-report-filters-value="<?php 
        echo \esc_attr(Security::json_encode($options->raw_filters()));
        ?>"
             data-report-filter-logic-value="<?php 
        echo Security::attr($options->filter_logic());
        ?>"
             data-report-chart-interval-value="<?php 
        echo Security::attr($options->chart_interval()->id());
        ?>"
             data-report-sort-column-value="<?php 
        echo Security::attr($sort_configuration->column());
        ?>"
             data-report-sort-direction-value="<?php 
        echo Security::attr($sort_configuration->direction());
        ?>"
             data-report-columns-value="<?php 
        echo \esc_attr(Security::json_encode($table->visible_column_ids()));
        ?>"
             data-report-quick-stats-value="<?php 
        echo \esc_attr(Security::json_encode($options->visible_quick_stats()));
        ?>"
             data-report-primary-chart-metric-id-value="<?php 
        echo \esc_attr($options->primary_chart_metric_id());
        ?>"
             data-report-secondary-chart-metric-id-value="<?php 
        echo \esc_attr($options->secondary_chart_metric_id());
        ?>"
        >
            <div id="report-header-container" class="report-header-container">
                <?php 
        echo $header;
        ?>
                <?php 
        $table->output_report_toolbar();
        ?>
                <div class="modal-background"></div>
            </div>
            <?php 
        echo $stats->get_html();
        ?>
            <?php 
        echo $chart->get_html();
        ?>
            <?php 
        echo $examiner_tabs;
        ?>
            <?php 
        echo $table->get_table_toolbar_markup();
        ?>
            <?php 
        echo $table->get_table_markup($sort_configuration->column(), $sort_configuration->direction());
        ?>
        </div>
        <?php 
        if (!$options->is_examiner()) {
            ?>
            <div id="iawp-examiner-modal" aria-hidden="true" class="mm micromodal-slide" data-controller="examiner">
                <div tabindex="-1" class="mm__overlay mm__overlay--full-screen" data-action="click->examiner#close:self" >
                    <div role="dialog" aria-modal="true" class="mm__container examiner examiner--loading">
                        <div data-examiner-target="content" class="examiner-content"></div>
                        <?php 
            echo \IAWPSCOPED\iawp_blade()->run('examiner.skeleton');
            ?>
                    </div>
                </div>
            </div>
        <?php 
        }
        ?>
        <?php 
        if (Env::get_tab() === 'geo') {
            echo '<div class="geo-ip-attribution">';
            echo \esc_html_x('Geolocation data powered by', 'Following text is a noun: DB-IP', 'independent-analytics') . ' ' . '<a href="https://db-ip.com" target="_blank">DB-IP</a>.';
            echo '</div>';
        }
        $this->notices();
    }
    private function notices()
    {
        $plugin_conflict_detector = new Plugin_Conflict_Detector();
        $requires_logged_in_tracking = $plugin_conflict_detector->plugin_requiring_logged_in_tracking();
        $show_logged_in_tracking_notice = $requires_logged_in_tracking && !\get_option('iawp_track_authenticated_users') && !\get_option('iawp_need_clear_cache') && !\get_option('iawp_logged_in_tracking_notice');
        ?><div class="iawp-notices"><?php 
        if (Capability_Manager::can_edit()) {
            if ($plugin_conflict_detector->has_conflict()) {
                echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => $plugin_conflict_detector->get_error(), 'button_text' => \false, 'id' => 'plugin-conflict', 'notice' => 'iawp-error', 'plugin' => $plugin_conflict_detector->get_plugin(), 'url' => 'https://independentwp.com/knowledgebase/tracking/secure-rest-api/']);
            } elseif (\IAWPSCOPED\iawp_is_pro() && \is_plugin_active('better-wp-security/better-wp-security.php')) {
                $settings = \get_option('itsec-storage');
                if (\array_key_exists('system-tweaks', $settings)) {
                    if (\array_key_exists('plugins_php', $settings['system-tweaks'])) {
                        if ($settings['system-tweaks']['plugins_php']) {
                            echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => \__('The "Solid Security" plugin is disabling PHP execution in the plugins folder, and this is preventing click tracking from working. Please visit the Security > Settings page, click on the Advanced section, click on System Tweaks Settings, uncheck the "Disable PHP Plugins" option, and then save.', 'independent-analytics'), 'button_text' => \false, 'id' => 'plugin-conflict', 'notice' => 'iawp-error', 'url' => 'https://independentwp.com/knowledgebase/click-tracking/allow-php-execution-plugins-folder/']);
                        }
                    }
                }
            }
            if (\get_option('iawp_need_clear_cache')) {
                echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => \__('Please clear your cache to ensure tracking works properly.', 'independent-analytics'), 'button_text' => \__('I\'ve cleared the cache', 'independent-analytics'), 'id' => 'iawp_need_clear_cache', 'notice' => 'iawp-warning', 'url' => 'https://independentwp.com/knowledgebase/common-questions/views-not-recording/']);
            }
            if ($show_logged_in_tracking_notice) {
                echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => '<strong>' . \sprintf(\_x('%s compatibility:', 'Variable is the name of a plugin', 'independent-analytics'), $requires_logged_in_tracking) . '</strong> ' . \__('We recommend you enable tracking for logged-in visitors.', 'independent-analytics'), 'button_text' => \__('Dismiss', 'independent-analytics'), 'id' => 'enable-logged-in-tracking', 'notice' => 'iawp-warning', 'url' => 'https://independentwp.com/knowledgebase/tracking/how-to-track-logged-in-visitors/']);
            }
            if (\IAWPSCOPED\iawp_db_version() > 0 && !Database::has_correct_database_privileges()) {
                echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => \__('Your site is missing the following critical database permissions:', 'independent-analytics') . ' ' . \implode(', ', Database::missing_database_privileges()) . '. ' . \__('There is no issue at this time, but you will need to enable the missing permissions before updating the plugin to a newer version to ensure an error is avoided. Please click this link to read our tutorial:', 'independent-analytics'), 'button_text' => \false, 'id' => 'missing-permissions', 'notice' => 'iawp-error', 'url' => 'https://independentwp.com/knowledgebase/common-questions/missing-database-permissions/']);
            }
            if (Env::get_tab() === 'clicks') {
                if (!\get_option('iawp_clicks_sync_notice')) {
                    echo \IAWPSCOPED\iawp_blade()->run('notices.notice', ['notice_text' => \__('Click data syncs every 60 seconds. Please allow for this delay when testing clicks on new links.', 'independent-analytics'), 'button_text' => \__('Dismiss', 'independent-analytics'), 'id' => 'iawp_clicks_sync_notice', 'notice' => 'iawp-warning', 'url' => 'https://independentwp.com/knowledgebase/click-tracking/click-tracking-update-process/']);
                }
            }
        }
        ?>
        </div><?php 
        if (\get_option('iawp_show_gsg') === '1' && !\get_option('iawp_need_clear_cache') && !$show_logged_in_tracking_notice && !$plugin_conflict_detector->has_conflict() && (Env::get_tab() !== 'clicks' || Env::get_tab() === 'clicks' && \get_option('iawp_clicks_sync_notice'))) {
            echo \IAWPSCOPED\iawp_blade()->run('notices.getting-started');
        }
    }
    private function examiner_tabs() : array
    {
        return [['table_type' => 'views', 'name' => \__('Pages', 'independent-analytics')], ['table_type' => 'referrers', 'name' => \__('Referrers', 'independent-analytics')], ['table_type' => 'geo', 'name' => \__('Geographic', 'independent-analytics')], ['table_type' => 'devices', 'name' => \__('Devices', 'independent-analytics')], ['table_type' => 'campaigns', 'name' => \__('Campaigns', 'independent-analytics')], ['table_type' => 'clicks', 'name' => \__('Clicks', 'independent-analytics')]];
    }
}
