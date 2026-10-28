<?php

namespace IAWP;

use IAWP\Custom_WordPress_Columns\Views_Column;
use IAWP\Utils\Dir;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Database_Manager
{
    public function reset_analytics() : void
    {
        // Empty all analytics tables while preserving config tables
        $this->get_tables()->where('type', 'analytics')->each(function ($table) {
            global $wpdb;
            $wpdb->query('TRUNCATE ' . $table['name']);
        });
        // Recreate the saved reports
        \IAWP\Report_Finder::insert_default_reports();
        \IAWP\Report_Finder::insert_default_user_journey_reports();
        $this->delete_overview_report_data();
        $this->delete_all_post_meta();
    }
    public function delete_all_data() : void
    {
        $this->delete_all_iawp_options();
        $this->delete_all_iawp_user_metadata();
        $this->delete_all_iawp_tables();
        $this->delete_all_post_meta();
        $this->delete_cached_favicons();
    }
    private function delete_all_iawp_options() : void
    {
        global $wpdb;
        $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name LIKE %s", 'iawp_%'));
        foreach ($options as $option) {
            \delete_option($option->option_name);
        }
    }
    private function delete_overview_report_data() : void
    {
        global $wpdb;
        $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name LIKE %s", 'iawp_module_%'));
        foreach ($options as $option) {
            \delete_option($option->option_name);
        }
    }
    private function delete_all_iawp_user_metadata() : void
    {
        global $wpdb;
        $metadata = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'iawp_%'));
        foreach ($metadata as $metadata) {
            \delete_user_meta($metadata->user_id, $metadata->meta_key);
        }
    }
    private function delete_cached_favicons() : void
    {
        try {
            Dir::delete(\IAWPSCOPED\iawp_upload_path_to('iawp-favicons/'));
        } catch (\Throwable $error) {
        }
    }
    private function delete_all_iawp_tables() : void
    {
        $this->get_tables()->each(function ($table) {
            global $wpdb;
            $wpdb->query('DROP TABLE ' . $table['name']);
        });
    }
    private function get_tables() : Collection
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT table_name AS name FROM information_schema.tables WHERE TABLE_SCHEMA = %s AND table_name LIKE %s", $wpdb->dbname, $wpdb->prefix . 'independent_analytics_%'));
        $config_tables = [$wpdb->prefix . 'independent_analytics_campaign_urls', $wpdb->prefix . 'independent_analytics_link_rules'];
        $tables = Collection::make($rows)->map(function ($row) use($config_tables) {
            return ['name' => $row->name, 'type' => \in_array($row->name, $config_tables) ? 'config' : 'analytics'];
        });
        return $tables;
    }
    private function delete_all_post_meta() : void
    {
        \delete_post_meta_by_key(Views_Column::$meta_key);
    }
    public static function register_actions() : void
    {
        \add_action('iawp_reset_analytics', function () {
            $manager = new self();
            $manager->reset_analytics();
        });
        \add_action('iawp_reset_database', function () {
            $db_version = \intval(\get_option('iawp_db_version', '0'));
            $manager = new self();
            $manager->delete_all_data();
            \update_option('iawp_db_version', $db_version);
        });
    }
}
