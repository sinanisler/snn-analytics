<?php

namespace IAWP;

use IAWP\Data_Pruning\Pruning_Scheduler;
use IAWP\Ecommerce\WooCommerce_Status_Manager;
use IAWP\Email_Reports\Interval_Factory;
use IAWP\Utils\Request;
/** @internal */
class Settings
{
    public function __construct()
    {
        \add_action('admin_init', [$this, 'register_settings']);
        \add_action('admin_init', [$this, 'register_view_counter_settings']);
        \add_action('admin_init', [$this, 'register_blocked_ip_settings']);
        \add_action('admin_init', [$this, 'register_block_by_role_settings']);
        if (\IAWPSCOPED\iawp_is_pro()) {
            \add_action('admin_init', [$this, 'register_email_report_settings']);
        }
    }
    public function render_settings()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.index');
        if (\IAWPSCOPED\iawp_is_pro()) {
            $default_colors = $this->email_report_colors();
            $saved = \IAWPSCOPED\iawp()->get_option('iawp_email_report_colors', $default_colors);
            // There were 6 colors and now 9, so there is a saved value but 7-9 don't exist
            $input_defaults = $default_colors;
            for ($i = 0; $i < \count($saved); $i++) {
                $input_defaults[$i] = $saved[$i];
            }
            $interval = Interval_Factory::from_option();
            echo \IAWPSCOPED\iawp_blade()->run('settings.email-reports', ['is_scheduled' => \wp_next_scheduled('iawp_send_email_report'), 'scheduled_date' => \IAWPSCOPED\iawp()->email_reports->next_email_at_for_humans(), 'is_paused' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_paused', '0') === '1', 'interval' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_interval', 'monthly'), 'time' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_time', 9), 'emails' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_email_addresses', []), 'from' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_from_address', \get_option('admin_email')), 'reply_to' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_reply_to_address', \get_option('admin_email')), 'footer_text' => \IAWPSCOPED\iawp()->get_option('iawp_email_report_footer', $this->email_footer()), 'default_colors' => $default_colors, 'input_default' => $input_defaults, 'timestamp' => $interval->next_interval_start()->getTimestamp()]);
        }
        $ips = \IAWPSCOPED\iawp()->get_option('iawp_blocked_ips', []);
        echo \IAWPSCOPED\iawp_blade()->run('settings.block-ips', ['current_ip' => Request::ip(), 'ip_is_blocked' => Request::is_ip_address_blocked(), 'ips' => $ips]);
        echo \IAWPSCOPED\iawp_blade()->run('settings.block-by-role', ['roles' => \wp_roles()->roles, 'blocked' => \IAWPSCOPED\iawp()->get_option('iawp_blocked_roles', ['administrator']), 'ignore_cookie' => \IAWPSCOPED\iawp()->get_option('iawp_ignore_via_cookie', \false)]);
        echo \IAWPSCOPED\iawp_blade()->run('settings.capabilities', ['editable_roles' => $this->get_editable_roles(), 'capabilities' => \IAWP\Capability_Manager::all_capabilities()]);
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter');
        if (\IAWPSCOPED\iawp()->is_woocommerce_support_enabled()) {
            echo \IAWPSCOPED\iawp_blade()->run('settings.woocommerce', ['statuses' => new WooCommerce_Status_Manager()]);
        }
        echo \IAWPSCOPED\iawp_blade()->run('settings.export-reports', ['report_finder' => \IAWP\Report_Finder::new()]);
        echo \IAWPSCOPED\iawp_blade()->run('settings.pruner', ['pruner' => new Pruning_Scheduler()]);
        echo \IAWPSCOPED\iawp_blade()->run('settings.delete', ['site_name' => \get_bloginfo('name'), 'site_url' => \site_url(), 'is_pro' => \IAWPSCOPED\iawp_is_pro()]);
    }
    public function register_settings()
    {
        \add_settings_section('iawp-settings-section', \esc_html__('Basic Settings', 'independent-analytics'), function () {
        }, 'independent-analytics-settings');
        \register_setting('iawp_settings', 'iawp_appearance', ['type' => 'string', 'default' => \IAWP\Appearance::get_default_appearance(), 'sanitize_callback' => function ($input) {
            return \array_key_exists($input, \IAWP\Appearance::options()) ? $input : \IAWP\Appearance::get_appearance();
        }]);
        \add_settings_field('iawp_appearance', \esc_html__('Color scheme', 'independent-analytics'), [$this, 'appearance_callback'], 'independent-analytics-settings', 'iawp-settings-section', ['class' => 'appearance']);
        $boolean_options = ['type' => 'boolean', 'default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean'];
        \register_setting('iawp_settings', 'iawp_track_authenticated_users', $boolean_options);
        \add_settings_field('iawp_track_authenticated_users', \esc_html__('Track logged in users', 'independent-analytics'), [$this, 'track_authenticated_users_callback'], 'independent-analytics-settings', 'iawp-settings-section', ['class' => 'logged-in']);
        \register_setting('iawp_settings', 'iawp_disable_admin_toolbar_analytics', $boolean_options);
        \add_settings_field('iawp_disable_admin_toolbar_analytics', \esc_html__('Admin toolbar stats', 'independent-analytics'), [$this, 'disable_admin_toolbar_analytics_callback'], 'independent-analytics-settings', 'iawp-settings-section');
        \register_setting('iawp_settings', 'iawp_disable_widget', $boolean_options);
        \add_settings_field('iawp_disable_widget', \esc_html__('Dashboard widget', 'independent-analytics'), [$this, 'disable_widget_callback'], 'independent-analytics-settings', 'iawp-settings-section');
        \register_setting('iawp_settings', 'iawp_disable_views_column', $boolean_options);
        \add_settings_field('iawp_disable_views_column', \esc_html__('Views column', 'independent-analytics'), [$this, 'disable_views_column_callback'], 'independent-analytics-settings', 'iawp-settings-section');
        $boolean_options = ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'];
        \register_setting('iawp_settings', 'iawp_dow', $boolean_options);
        \add_settings_field('iawp_dow', \esc_html__('First day of week', 'independent-analytics'), [$this, 'starting_dow_callback'], 'independent-analytics-settings', 'iawp-settings-section', ['class' => 'dow']);
        // Salt refresh interval
        \register_setting('iawp_settings', 'iawp_visitor_salt_refresh_interval', ['type' => 'int', 'default' => \IAWP\VisitorSaltRefreshInterval::default_interval(), 'sanitize_callback' => function ($input) {
            return \array_key_exists($input, \IAWP\VisitorSaltRefreshInterval::options()) ? $input : \IAWP\VisitorSaltRefreshInterval::interval();
        }]);
        \add_settings_field('iawp_visitor_salt_refresh_interval', \esc_html__('Salt refresh rate', 'independent-analytics'), [$this, 'visitor_salt_refresh_interval_callback'], 'independent-analytics-settings', 'iawp-settings-section', ['class' => 'visitor_salt_refresh_interval']);
    }
    public function appearance_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.appearance', ['appearance' => \IAWP\Appearance::get_appearance(), 'options' => \IAWP\Appearance::options()]);
    }
    public function visitor_salt_refresh_interval_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.visitor-salt-refresh-interval', ['interval' => \IAWP\VisitorSaltRefreshInterval::interval(), 'options' => \IAWP\VisitorSaltRefreshInterval::options()]);
    }
    public function track_authenticated_users_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.track-authenticated-users', ['track_authenticated_users' => \IAWPSCOPED\iawp()->get_option('iawp_track_authenticated_users', \false)]);
    }
    public function disable_admin_toolbar_analytics_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.disable-admin-toolbar-analytics', ['value' => \IAWPSCOPED\iawp()->get_option('iawp_disable_admin_toolbar_analytics', \false)]);
    }
    public function disable_widget_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.disable-widget', ['value' => \IAWPSCOPED\iawp()->get_option('iawp_disable_widget', \false)]);
    }
    public function disable_views_column_callback() : void
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.disable-views-column', ['value' => \IAWPSCOPED\iawp()->get_option('iawp_disable_views_column', \false)]);
    }
    public function starting_dow_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.first-day-of-week', ['day_of_week' => \IAWPSCOPED\iawp()->get_option('iawp_dow', 0), 'days' => [0 => \esc_html__('Sunday', 'independent-analytics'), 1 => \esc_html__('Monday', 'independent-analytics'), 2 => \esc_html__('Tuesday', 'independent-analytics'), 3 => \esc_html__('Wednesday', 'independent-analytics'), 4 => \esc_html__('Thursday', 'independent-analytics'), 5 => \esc_html__('Friday', 'independent-analytics'), 6 => \esc_html__('Saturday', 'independent-analytics')]]);
    }
    public function register_view_counter_settings()
    {
        \add_settings_section('iawp-view-counter-settings-section', \esc_html__('Public View Counter', 'independent-analytics'), function () {
        }, 'independent-analytics-view-counter-settings');
        $args = ['type' => 'boolean', 'default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean'];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_enable', $args);
        \add_settings_field('iawp_view_counter_enable', \esc_html__('Enable the view counter', 'independent-analytics'), [$this, 'view_counter_enable_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'enable']);
        $args = ['type' => 'array', 'default' => [], 'sanitize_callback' => [$this, 'sanitize_view_counter_post_types']];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_post_types', $args);
        \add_settings_field('iawp_view_counter_post_types', \esc_html__('Display on these post types', 'independent-analytics'), [$this, 'view_counter_post_types_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'post-types']);
        // Position
        $args = ['type' => 'string', 'default' => 'after', 'sanitize_callback' => [$this, 'sanitize_view_counter_position']];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_position', $args);
        \add_settings_field('iawp_view_counter_position', \esc_html__('Show it in this location', 'independent-analytics'), [$this, 'view_counter_position_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'position']);
        // Views to count
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_views_to_count', ['type' => 'string', 'default' => 'total', 'sanitize_callback' => [$this, 'sanitize_view_counter_views_to_count']]);
        \add_settings_field('iawp_view_counter_views_to_count', \esc_html__('Date range to count views', 'independent-analytics'), [$this, 'view_counter_views_to_count_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'views-to-count']);
        // Exclude
        $args = ['type' => 'string', 'default' => '', 'sanitize_callback' => [$this, 'sanitize_view_counter_exclude']];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_exclude', $args);
        \add_settings_field('iawp_view_counter_exclude', \esc_html__('Exclude these pages', 'independent-analytics'), [$this, 'view_counter_exclude_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'exclude']);
        // Minimum threshold
        $args = ['type' => 'int', 'default' => 0, 'sanitize_callback' => 'absint'];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_threshold', $args);
        \add_settings_field('iawp_view_counter_threshold', \esc_html__('Minimum views required', 'independent-analytics'), [$this, 'view_counter_threshold_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'threshold']);
        // Label
        $default = \function_exists('IAWPSCOPED\\pll__') ? pll__('Views:', 'independent-analytics') : \__('Views:', 'independent-analytics');
        $args = ['type' => 'string', 'default' => $default, 'sanitize_callback' => 'sanitize_text_field'];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_label', $args);
        \add_settings_field('iawp_view_counter_label', \esc_html__('Edit the label', 'independent-analytics'), [$this, 'view_counter_label_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'counter-label']);
        // Hide Label
        $args = ['type' => 'boolean', 'default' => \true, 'sanitize_callback' => 'rest_sanitize_boolean'];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_label_show', $args);
        \add_settings_field('iawp_view_counter_label_show', \esc_html__('Display the label', 'independent-analytics'), [$this, 'view_counter_label_show_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'hide-label']);
        // Icon
        $args = ['type' => 'boolean', 'default' => \true, 'sanitize_callback' => 'rest_sanitize_boolean'];
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_icon', $args);
        \add_settings_field('iawp_view_counter_icon', \esc_html__('Display the icon', 'independent-analytics'), [$this, 'view_counter_icon_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'icon']);
        // Private
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_private', ['type' => 'boolean', 'default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean']);
        \add_settings_field('iawp_view_counter_private', \esc_html__('Make the view counter private?', 'independent-analytics'), [$this, 'view_counter_private_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'private']);
        // Allow manual adjustment
        \register_setting('iawp_view_counter_settings', 'iawp_view_counter_manual_adjustment', ['type' => 'boolean', 'default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean']);
        \add_settings_field('iawp_view_counter_manual_adjustment', \esc_html__('Allow manual adjustment?', 'independent-analytics'), [$this, 'view_counter_manual_adjustment_callback'], 'independent-analytics-view-counter-settings', 'iawp-view-counter-settings-section', ['class' => 'manual-adjustment']);
    }
    public function view_counter_enable_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.enable', ['enable' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_enable', \false)]);
    }
    public function view_counter_post_types_callback()
    {
        $site_post_types = \get_post_types(['public' => \true], 'objects');
        $counter = 0;
        foreach ($site_post_types as $post_type) {
            echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.post-types', ['counter' => $counter, 'post_type' => $post_type, 'saved' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_post_types', [])]);
            $counter++;
        }
        ?>
        <p class="description"><?php 
        \esc_html_e('Uncheck all boxes to only show view count manually. See shortcode documentation below for details.', 'independent-analytics');
        ?></p>
        <?php 
    }
    public function view_counter_position_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.position', ['position' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_position', 'after')]);
    }
    public function view_counter_views_to_count_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.views-to-count', ['value' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_views_to_count', 'total')]);
    }
    public function view_counter_exclude_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.exclude', ['exclude' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_exclude', '')]);
    }
    public function view_counter_threshold_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.threshold', ['threshold' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_threshold', 0)]);
    }
    public function view_counter_label_callback()
    {
        $default = \function_exists('IAWPSCOPED\\pll__') ? pll__('Views:', 'independent-analytics') : \__('Views:', 'independent-analytics');
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.label', ['label' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_label', $default)]);
    }
    public function view_counter_label_show_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.label-show', ['show' => \get_option('iawp_view_counter_label_show', \true)]);
    }
    public function view_counter_icon_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.icon', ['icon' => \get_option('iawp_view_counter_icon', \true)]);
    }
    public function view_counter_private_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.private', ['private' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_private', \false)]);
    }
    public function view_counter_manual_adjustment_callback()
    {
        echo \IAWPSCOPED\iawp_blade()->run('settings.view-counter.manual-adjustment', ['value' => \IAWPSCOPED\iawp()->get_option('iawp_view_counter_manual_adjustment', \false)]);
    }
    public function register_blocked_ip_settings()
    {
        \add_settings_section('iawp-blocked-ips-settings-section', \esc_html__('Block IP Addresses', 'independent-analytics'), function () {
        }, 'iawp-blocked-ips-settings');
        $args = ['type' => 'array', 'default' => [], 'sanitize_callback' => [$this, 'sanitize_blocked_ips']];
        \register_setting('iawp_blocked_ip_settings', 'iawp_blocked_ips', $args);
    }
    public function register_email_report_settings()
    {
        \add_settings_section('iawp-email-report-settings-section', \esc_html__('Scheduled Email Reports', 'independent-analytics'), function () {
        }, 'iawp-email-report-settings');
        \register_setting('iawp_email_report_settings', 'iawp_email_report_interval', ['type' => 'string', 'default' => 'monthly', 'sanitize_callback' => [$this, 'sanitize_email_report_interval']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_time', ['type' => 'number', 'default' => 9, 'sanitize_callback' => [$this, 'sanitize_email_report_time']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_email_addresses', ['type' => 'array', 'default' => [], 'sanitize_callback' => [$this, 'sanitize_email_addresses']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_colors', ['type' => 'array', 'default' => $this->email_report_colors(), 'sanitize_callback' => [$this, 'sanitize_email_report_colors']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_from_address', ['type' => 'string', 'default' => \get_option('admin_email'), 'sanitize_callback' => [$this, 'sanitize_email_address']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_reply_to_address', ['type' => 'string', 'default' => \get_option('admin_email'), 'sanitize_callback' => [$this, 'sanitize_email_address']]);
        \register_setting('iawp_email_report_settings', 'iawp_email_report_footer', ['type' => 'string', 'default' => $this->email_footer(), 'sanitize_callback' => 'sanitize_text_field']);
    }
    public function register_block_by_role_settings()
    {
        \add_settings_section('iawp-block-by-role-settings-section', \esc_html__('Block by User Role', 'independent-analytics'), function () {
        }, 'iawp-block-by-role-settings');
        $args = ['type' => 'array', 'default' => ['administrator'], 'sanitize_callback' => [$this, 'sanitize_blocked_roles']];
        \register_setting('iawp_block_by_role_settings', 'iawp_blocked_roles', $args);
        $args = ['type' => 'boolean', 'default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean'];
        \register_setting('iawp_block_by_role_settings', 'iawp_ignore_via_cookie', $args);
    }
    public function sanitize_view_counter_post_types($user_input)
    {
        if (\is_null($user_input)) {
            return [];
        }
        $site_post_types = \get_post_types(['public' => \true]);
        $to_save = [];
        foreach ($user_input as $post_type) {
            if (\in_array($post_type, $site_post_types)) {
                $to_save[] = $post_type;
            }
        }
        return $to_save;
    }
    public function sanitize_view_counter_position($user_input)
    {
        if (\in_array($user_input, ['before', 'after', 'both'])) {
            return $user_input;
        } else {
            return 'after';
        }
    }
    public function sanitize_view_counter_views_to_count($user_input)
    {
        if (\in_array($user_input, ['total', 'today', 'yesterday', 'this_week', 'last_week', 'last_seven', 'last_thirty', 'last_sixty', 'last_ninety', 'this_month', 'last_month', 'last_three_months', 'last_six_months', 'last_twelve_months', 'this_year', 'last_year'])) {
            return $user_input;
        } else {
            return 'total';
        }
    }
    public function sanitize_view_counter_exclude($user_input)
    {
        $user_input = \explode(',', $user_input);
        $to_save = [];
        foreach ($user_input as $id) {
            $save = \absint($id);
            if ($save != 0) {
                $to_save[] = $save;
            }
        }
        $to_save = \implode(',', $to_save);
        return $to_save;
    }
    public function sanitize_blocked_ips($ips)
    {
        $valid_ips = [];
        foreach ($ips as $ip) {
            $address = \IAWPSCOPED\IPLib\Factory::parseRangeString($ip);
            // Skip invalid ip address ranges
            if ($address === null) {
                continue;
            }
            $valid_ips[] = $ip;
        }
        return $valid_ips;
    }
    public function sanitize_email_address($email)
    {
        $save = '';
        $cleaned = \sanitize_email($email);
        if (\is_email($cleaned)) {
            $save = $cleaned;
        }
        return $save;
    }
    public function sanitize_email_addresses($emails)
    {
        $to_save = [];
        foreach ($emails as $email) {
            $cleaned = \sanitize_email($email);
            if (\is_email($cleaned)) {
                $to_save[] = $cleaned;
            }
        }
        return $to_save;
    }
    public function sanitize_email_report_interval($input)
    {
        if (\in_array($input, ['monthly', 'weekly', 'daily'])) {
            return $input;
        } else {
            return 'monthly';
        }
    }
    public function sanitize_email_report_time($user_time)
    {
        $accepted_times = [];
        for ($i = 0; $i < 24; $i++) {
            $accepted_times[] = $i;
        }
        if (\in_array($user_time, $accepted_times)) {
            return $user_time;
        } else {
            return 9;
        }
    }
    public function sanitize_blocked_roles($blocked_roles)
    {
        $to_save = [];
        $user_roles = \array_keys(\wp_roles()->roles);
        foreach ($blocked_roles as $blocked) {
            if (\in_array($blocked, $user_roles)) {
                $to_save[] = $blocked;
            }
        }
        return $to_save;
    }
    public function sanitize_email_report_colors($colors)
    {
        $to_save = [];
        // No idea why WP sometimes returns an array, so this is for my sanity
        if (\is_string($colors)) {
            $colors = \explode(',', $colors);
        }
        foreach ($colors as $color) {
            $to_save[] = \sanitize_hex_color($color);
        }
        return $to_save;
    }
    /**
     * @return array
     */
    private function get_editable_roles() : array
    {
        $editable_roles = [];
        $wp_roles = \wp_roles()->roles;
        \array_walk($wp_roles, function ($role, $role_key) use(&$editable_roles) {
            if ($role_key === 'administrator') {
                return;
            }
            $capability = null;
            foreach (\IAWP\Capability_Manager::all_capabilities() as $key => $name) {
                if (\array_key_exists($key, $role['capabilities'])) {
                    $capability = $key;
                }
            }
            $editable_roles[] = ['key' => $role_key, 'name' => $role['name'], 'capability' => $capability];
        });
        return $editable_roles;
    }
    private function email_report_colors()
    {
        return ['#5123a0', '#fafafa', '#3a1e6b', '#fafafa', '#5123a0', '#a985e6', '#ece9f2', '#f7f5fa', '#ece9f2', '#dedae6'];
    }
    private function email_footer()
    {
        return \sprintf(\esc_html__('This email was generated and delivered by %s', 'independent-analytics'), \esc_url(\get_site_url()));
    }
}
