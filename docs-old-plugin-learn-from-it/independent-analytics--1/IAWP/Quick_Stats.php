<?php

namespace IAWP;

use IAWP\Statistics\Statistic;
use IAWP\Statistics\Statistics;
/** @internal */
class Quick_Stats
{
    private $statistics;
    private $is_dashboard_widget;
    private $is_showing_skeleton_ui;
    private $hide_unfiltered_statistics;
    /**
     * @param Statistics $statistics
     * @param bool $is_dashboard_widget
     */
    public function __construct(Statistics $statistics, bool $is_dashboard_widget = \false, bool $is_showing_skeleton_ui = \false, bool $hide_unfiltered_statistics = \false)
    {
        $this->statistics = $statistics;
        $this->is_dashboard_widget = $is_dashboard_widget;
        $this->is_showing_skeleton_ui = $is_showing_skeleton_ui;
        $this->hide_unfiltered_statistics = $hide_unfiltered_statistics;
    }
    public function get_html() : string
    {
        $statistics = $this->statistics->get_statistics();
        $visible_quick_stats_count = \count(\array_filter($statistics, function (Statistic $statistic) : bool {
            return $statistic->is_visible() && $statistic->is_group_plugin_enabled();
        }));
        $quick_stats_html_class = "quick-stats";
        if ($this->statistics->has_filters()) {
            $quick_stats_html_class .= ' filtered';
        }
        if ($this->is_showing_skeleton_ui) {
            $quick_stats_html_class .= ' skeleton-ui';
        }
        return \IAWPSCOPED\iawp_blade()->run('quick-stats', ['is_dashboard_widget' => $this->is_dashboard_widget, 'hide_unfiltered_statistics' => $this->hide_unfiltered_statistics, 'quick_stats_html_class' => $quick_stats_html_class, 'statistics' => $statistics, 'plugin_groups' => \IAWP\Plugin_Group::get_plugin_groups(), 'total_stats' => $visible_quick_stats_count]);
    }
}
