<?php

namespace IAWP;

use IAWP\Tables\Table_Campaigns;
use IAWP\Tables\Table_Clicks;
use IAWP\Tables\Table_Devices;
use IAWP\Tables\Table_Geo;
use IAWP\Tables\Table_Journeys;
use IAWP\Tables\Table_Pages;
use IAWP\Tables\Table_Referrers;
/** @internal */
class Env
{
    public function is_free() : bool
    {
        return \IAWPSCOPED\iawp_is_free();
    }
    public function is_pro() : bool
    {
        return \IAWPSCOPED\iawp_is_pro();
    }
    /**
     * @param string|int $id An int for a saved report. A string for a default-report.
     *
     * @return bool
     */
    public function is_favorite($id = null) : bool
    {
        if (\is_int($id)) {
            $raw_favorite_id = \get_user_meta(\get_current_user_id(), 'iawp_favorite_report_id', \true);
            $favorite_id = \filter_var($raw_favorite_id, \FILTER_VALIDATE_INT);
            return $favorite_id === $id;
        } elseif (\is_string($id)) {
            $raw_favorite_type = \get_user_meta(\get_current_user_id(), 'iawp_favorite_report_type', \true);
            $favorite_type = \filter_var($raw_favorite_type, \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            return $favorite_type === $id;
        } else {
            return \false;
        }
    }
    /**
     * @param string|int $id An int for a saved report. A string for a default-report.
     *
     * @return bool
     */
    public function is_currently_viewed($id = null) : bool
    {
        if (\is_int($id)) {
            $report_id = \array_key_exists('report', $_GET) ? \sanitize_text_field($_GET['report']) : null;
            return $id === \intval($report_id);
        } elseif (\is_string($id)) {
            return $id === self::get_tab();
        } else {
            return \false;
        }
    }
    public function is_white_labeled() : bool
    {
        return \IAWP\Capability_Manager::show_white_labeled_ui();
    }
    public function can_write() : bool
    {
        return \IAWP\Capability_Manager::can_edit();
    }
    public static function get_page() : ?string
    {
        if (!\is_admin()) {
            return null;
        }
        $page = $_GET['page'] ?? null;
        $valid_pages = ['independent-analytics', 'independent-analytics-settings', 'independent-analytics-campaign-builder', 'independent-analytics-click-tracking', 'independent-analytics-support-center', 'independent-analytics-integrations', 'independent-analytics-updates', 'independent-analytics-debug', 'independent-analytics-visitor'];
        if (\in_array($page, $valid_pages)) {
            return $page;
        }
        return null;
    }
    public static function get_tab() : ?string
    {
        if (self::get_page() !== 'independent-analytics') {
            return null;
        }
        $default_tab = 'views';
        $valid_tabs = ['views', 'referrers', 'geo', 'devices'];
        if (\IAWPSCOPED\iawp_is_pro()) {
            $valid_tabs = \array_merge($valid_tabs, ['campaigns', 'clicks', 'real-time']);
        }
        if (\IAWPSCOPED\iawp_is_pro() && \IAWP\Capability_Manager::can_view_all_analytics()) {
            $default_tab = 'overview';
            $valid_tabs = \array_merge($valid_tabs, ['journeys', 'overview']);
        }
        $tab = \array_key_exists('tab', $_GET) ? \stripslashes(\sanitize_text_field($_GET['tab'])) : \false;
        $is_valid = \in_array($tab, $valid_tabs);
        if ($is_valid) {
            return $tab;
        } else {
            return $default_tab;
        }
    }
    public static function get_table(?string $table_type = null) : string
    {
        if (null === $table_type && \array_key_exists('tab', $_GET)) {
            $table_type = \stripslashes(\sanitize_text_field($_GET['tab']));
        }
        if (null === $table_type && \array_key_exists('table_type', $_POST)) {
            $table_type = \stripslashes(\sanitize_text_field($_POST['table_type']));
        }
        switch ($table_type) {
            case 'views':
            default:
                return Table_Pages::class;
            case 'referrers':
                return Table_Referrers::class;
            case 'geo':
                return Table_Geo::class;
            case 'devices':
                return Table_Devices::class;
            case 'campaigns':
                return Table_Campaigns::class;
            case 'clicks':
                return Table_Clicks::class;
            case 'journeys':
                return Table_Journeys::class;
        }
    }
}
