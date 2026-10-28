<?php

namespace IAWP;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWPSCOPED\Carbon\CarbonInterval;
use IAWP\Admin_Page\Analytics_Page;
use IAWP\Admin_Page\Campaign_Builder_Page;
use IAWP\Admin_Page\Click_Tracking_Page;
use IAWP\Admin_Page\Debug_Page;
use IAWP\Admin_Page\Integrations_Pages;
use IAWP\Admin_Page\Settings_Page;
use IAWP\Admin_Page\Support_Page;
use IAWP\Admin_Page\Updates_Page;
use IAWP\Admin_Page\Visitor_Page;
use IAWP\AJAX\AJAX_Manager;
use IAWP\Click_Tracking\Click_Processing_Job;
use IAWP\Data_Pruning\Pruner;
use IAWP\Ecommerce\EDD_Order;
use IAWP\Ecommerce\Fluent_Cart_Order;
use IAWP\Ecommerce\PMPro_Order;
use IAWP\Ecommerce\SureCart_Event_Sync_Job;
use IAWP\Ecommerce\SureCart_Order;
use IAWP\Ecommerce\SureCart_Store;
use IAWP\Ecommerce\WooCommerce_Order;
use IAWP\Email_Reports\Email_Reports;
use IAWP\Form_Submissions\Form;
use IAWP\Form_Submissions\Submission_Listener;
use IAWP\Journey\JourneyStatisticsJob;
use IAWP\Migrations\Migrations;
use IAWP\Overview\Module_Refresh_Job;
use IAWP\Utils\Plugin;
use IAWP\Utils\Request;
use IAWP\Utils\Singleton;
use IAWP\Utils\Timezone;
use IAWP\WooCommerceOrderMetaBox\WooCommerceOrderMetaBox;
use IAWPSCOPED\Illuminate\Support\Carbon;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Independent_Analytics
{
    use Singleton;
    public $settings;
    public $email_reports;
    public $cron_manager;
    private $is_woocommerce_support_enabled;
    private $is_surecart_support_enabled;
    private $is_edd_support_enabled;
    private $is_pmpro_support_enabled;
    private $is_fluent_cart_support_enabled;
    private $is_form_submission_support_enabled;
    // This is where we attach functions to WP hooks
    private function __construct()
    {
        $this->configure_carbon();
        $this->settings = new \IAWP\Settings();
        new \IAWP\REST_API();
        new \IAWP\Dashboard_Widget();
        new \IAWP\View_Counter();
        new Submission_Listener();
        Pruner::register_hook();
        AJAX_Manager::getInstance();
        if (!Migrations::is_migrating()) {
            new \IAWP\Track_Resource_Changes();
            \IAWP\Admin_Bar_Stats::register();
            WooCommerce_Order::register_hooks();
            EDD_Order::register_hooks();
            PMPro_Order::register_hooks();
            SureCart_Order::register_hooks();
            Fluent_Cart_Order::register_hooks();
        }
        \IAWP\Cron_Job::register_custom_intervals();
        \IAWP\Database_Manager::register_actions();
        $this->cron_manager = new \IAWP\Cron_Manager();
        (new SureCart_Event_Sync_Job())->register_handler();
        (new JourneyStatisticsJob())->register_handler();
        (new \IAWP\FetchFaviconsJob())->register_handler();
        (new Click_Processing_Job())->register_handler();
        (new Module_Refresh_Job())->register_handler();
        (new \IAWP\Migration_Fixer_Job())->register_handler();
        (new \IAWP\Geo_Database_Health_Check_Job())->register_handler();
        if (\IAWPSCOPED\iawp_is_pro()) {
            $this->email_reports = new Email_Reports();
            new \IAWP\Campaign_Builder();
            WooCommerceOrderMetaBox::register();
        }
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts_and_styles'], 110);
        // Called at 110 to dequeue other scripts
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts_and_styles_front_end']);
        \add_action('admin_menu', [$this, 'add_admin_menu_pages']);
        \add_action('admin_init', [$this, 'remove_freemius_pricing_menu']);
        \add_filter('plugin_action_links_independent-analytics/iawp.php', [$this, 'plugin_action_links']);
        \add_action('init', [$this, 'polylang_translations']);
        \add_action('init', [$this, 'load_text_domain']);
        // Freemius adjustments
        \IAWP_FS()->add_filter('pricing_url', [$this, 'change_freemius_pricing_url'], 10);
        \IAWP_FS()->add_filter('show_affiliate_program_notice', '__return_false');
        \IAWP_FS()->add_filter('show_deactivation_feedback_form', function () {
            return \false;
        });
        \IAWP_FS()->add_filter('connect-header', [$this, 'freemius_optin_heading']);
        \IAWP_FS()->add_filter('connect_message', [$this, 'freemius_optin_text']);
        if (\function_exists('IAWPSCOPED\\fs_override_i18n')) {
            fs_override_i18n(['opt-in-connect' => \__('Subscribe & Go To Analytics', 'independent-analytics'), 'skip' => \__('No, thanks', 'independent-analytics')], 'independent-analytics');
        }
        // Other hooks
        \add_action('admin_init', [$this, 'maybe_delete_mu_plugin']);
        \add_action('admin_body_class', [$this, 'add_body_class']);
        \add_filter('sgs_whitelist_wp_content', [$this, 'whitelist_click_endpoint']);
        \add_filter('cmplz_whitelisted_script_tags', [$this, 'whitelist_script_tag_for_complianz']);
        \add_filter('plugin_action_links_independent-analytics/iawp.php', [$this, 'add_upgrade_link_in_plugins_menu'], 999);
        \add_filter('plugin_row_meta', [$this, 'add_docs_link_in_plugins_menu'], 10, 2);
        \add_action('admin_footer', [$this, 'attach_system_appearance_script'], 99, 0);
        \add_filter('sgo_javascript_combine_excluded_inline_content', [$this, 'exclude_tracking_script_from_speed_optimizer'], 10, 1);
        \add_action('admin_init', [$this, 'maybe_add_cookie']);
    }
    public function add_upgrade_link_in_plugins_menu($links)
    {
        if (\IAWPSCOPED\iawp_is_pro()) {
            return $links;
        }
        $upgrade_link = '<a target="_blank" style="color:#36B366;font-weight:700;"
            href="https://independentwp.com/pro/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Plugin+Settings+Link"
            >' . \esc_html__('Upgrade to Pro', 'independent-analytics') . '</a>';
        \array_unshift($links, $upgrade_link);
        return $links;
    }
    public function add_docs_link_in_plugins_menu($plugin_meta, $plugin_file)
    {
        if ($plugin_file == 'independent-analytics/iawp.php' || $plugin_file == 'independent-analytics-pro/iawp.php') {
            $plugin_meta[] = '<a target="_blank" href="https://independentwp.com/knowledgebase/">' . \esc_html__('Knowledge Base', 'independent-analytics') . '</a>';
        }
        return $plugin_meta;
    }
    /**
     * Attach some JavaScript to manage the color scheme for admin pages.
     *
     * @return void
     */
    public function attach_system_appearance_script() : void
    {
        ?>
        <script>
            ;(() => {
                try {
                    const isLightMode = document.body.classList.contains('iawp-light-mode')
                    const isDarkMode = document.body.classList.contains('iawp-dark-mode')
                    const isSystem = !isLightMode && !isDarkMode

                    // Stop if they're not using the systems color scheme
                    if(!isSystem) {
                        return
                    }

                    // Switch to dark mode if dark is the system color scheme preference
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.body.classList.add('iawp-dark-mode')
                    }

                    // Watching for changes is a nice idea, but it's more complicated than just
                    // adding or removing a class from the body. There are elements like
                    // charts that cannot adapt without a rendered. Holding off on this.

                    // window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
                    //     if(event.matches) {
                    //         document.body.classList.remove('iawp-light-mode')
                    //         document.body.classList.add('iawp-dark-mode')
                    //     } else {
                    //         document.body.classList.remove('iawp-dark-mode')
                    //         document.body.classList.add('iawp-light-mode')
                    //     }
                    // });
                } catch (error) {

                }
            })();

        </script>
        <?php 
    }
    public function add_body_class(string $classes) : string
    {
        $newClasses = [];
        if (\IAWP\Appearance::is_light()) {
            $newClasses[] = 'iawp-light-mode';
        } elseif (\IAWP\Appearance::is_dark()) {
            $newClasses[] = 'iawp-dark-mode';
        }
        $page = \IAWP\Env::get_page();
        if (\is_string($page)) {
            $newClasses[] = $page;
        }
        if (\array_key_exists('examiner', $_GET)) {
            $newClasses[] = 'iawp-in-examiner';
        }
        return $classes . ' ' . \implode(' ', $newClasses) . ' ';
    }
    /**
     * At one point in time, there was a must-use plugin that was created. The plugin file and the
     * option need to get cleaned up.
     * @return void
     */
    public function maybe_delete_mu_plugin()
    {
        $already_attempted = \get_option('iawp_attempted_to_delete_mu_plugin', '0');
        if ($already_attempted === '1') {
            return;
        }
        if (\get_option('iawp_must_use_directory_not_writable', '0') === '1') {
            \delete_option('iawp_must_use_directory_not_writable');
        }
        $mu_plugin_file = \trailingslashit(\WPMU_PLUGIN_DIR) . 'iawp-performance-boost.php';
        if (\file_exists($mu_plugin_file)) {
            \unlink($mu_plugin_file);
        }
        \update_option('iawp_attempted_to_delete_mu_plugin', '1', \true);
    }
    public function load_text_domain()
    {
        \load_plugin_textdomain('independent-analytics', \false, \IAWP_LANGUAGES_DIRECTORY);
        // Freemius overrides must happen after the text domain is loaded
        \IAWP_FS()->override_i18n(['yee-haw' => \__('Success', 'independent-analytics')]);
    }
    public function polylang_translations()
    {
        if (\function_exists('IAWPSCOPED\\pll_register_string')) {
            pll_register_string('view_counter', 'Views:', 'Independent Analytics');
        }
    }
    // Changes the URL for the "Upgrade" tab in the Account menu
    public function change_freemius_pricing_url()
    {
        return 'https://independentwp.com/pro/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Upgrade+to+Pro&utm_content=Account';
    }
    public function freemius_optin_heading()
    {
        return '<h2>' . \esc_html__('Congratulations on Your New Analytics! 🎉', 'independent-analytics') . '</h2>';
    }
    public function freemius_optin_text()
    {
        $html = '<p>' . \esc_html__("You've just taken the first step towards building a better website.", "independent-analytics") . '</p>';
        $html .= '<br />';
        $html .= '<p>' . \esc_html__("If you signup for our free email course, we’ll walk you through each feature in Independent Analytics, so you can learn how to analyze and optimize your website for growth.", "independent-analytics") . '</p>';
        $html .= '<br />';
        $html .= '<p><strong>' . \esc_html_x("Subscribe now and you'll receive our:", "What follows is a list of benefits", "independent-analytics") . '</strong></p>';
        $html .= '<ul>';
        $html .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__('Free email tutorial series', 'independent-analytics') . '</li>';
        $html .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__('Subscriber-only sales announcements', 'independent-analytics') . '</li>';
        $html .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__('New feature updates', 'independent-analytics') . '</li>';
        $html .= '<li><span class="dashicons dashicons-yes-alt"></span> ' . \esc_html__('Security alerts', 'independent-analytics') . '</li>';
        $html .= '</ul>';
        $html .= '<p>' . \esc_html('Subscribing will also share some basic info about your WordPress site with us, which helps us make Independent Analytics an even better plugin for everyone.', 'independent-analytics') . '</p>';
        $html .= '<p class="rating">' . \sprintf(\esc_html__('Rated %s and trusted by over 100,000+ WordPress websites.', 'independent-analytics'), '<span class="star-container"><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span></span>') . '</p>';
        $html .= '<img src="' . \IAWPSCOPED\iawp_url_to('img/email-review.png') . '" />';
        return $html;
    }
    public function add_admin_menu_pages()
    {
        $title = \IAWP\Capability_Manager::show_white_labeled_ui() ? \esc_html__('Analytics', 'independent-analytics') : 'Independent Analytics';
        \add_menu_page($title, \esc_html__('Analytics', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics', function () {
            $analytics_page = new Analytics_Page();
            $analytics_page->render();
        }, $this->get_menu_icon(), 3);
        if (\IAWP\Capability_Manager::can_edit()) {
            \add_submenu_page('independent-analytics', \esc_html__('Settings', 'independent-analytics'), \esc_html__('Settings', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-settings', function () {
                $settings_page = new Settings_Page();
                $settings_page->render(\false);
            });
        }
        if (\IAWPSCOPED\iawp_is_pro()) {
            \add_submenu_page('independent-analytics', \esc_html__('Campaign Builder', 'independent-analytics'), \esc_html__('Campaign Builder', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-campaign-builder', function () {
                $campaign_builder_page = new Campaign_Builder_Page();
                $campaign_builder_page->render(\false);
            });
            if (\IAWP\Capability_Manager::can_edit()) {
                \add_submenu_page('independent-analytics', \esc_html__('Click Tracking', 'independent-analytics'), \esc_html__('Click Tracking', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-click-tracking', function () {
                    $click_tracking_page = new Click_Tracking_Page();
                    $click_tracking_page->render(\false);
                });
            }
        }
        if (\IAWP\Capability_Manager::show_branded_ui()) {
            \add_submenu_page('independent-analytics', \esc_html__('Help & Support', 'independent-analytics'), \esc_html__('Help & Support', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-support-center', function () {
                $support_page = new Support_Page();
                $support_page->render(\false);
            });
        }
        if (\IAWP\Capability_Manager::show_branded_ui()) {
            \add_submenu_page('independent-analytics', \esc_html__('Integrations', 'independent-analytics'), \esc_html__('Integrations', 'independent-analytics'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-integrations', function () {
                $integrations_page = new Integrations_Pages();
                $integrations_page->render(\false);
            });
        }
        if (\IAWP\Capability_Manager::show_branded_ui()) {
            $menu_html = '<span class="menu-name">' . \esc_html__('Changelog', 'independent-analytics') . '</span>';
            $menu_html = $this->changelog_viewed_since_update() ? $menu_html . ' <span class="menu-counter">' . \esc_html__('New', 'independent-analytics') . '</span>' : $menu_html;
            \add_submenu_page('independent-analytics', \esc_html__('Changelog', 'independent-analytics'), $menu_html, \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-updates', function () {
                $updates_page = new Updates_Page();
                $updates_page->render(\false);
            });
        }
        if (\IAWPSCOPED\iawp_is_free() && \IAWP\Capability_Manager::show_branded_ui()) {
            \add_submenu_page('independent-analytics', \esc_html__('Upgrade to Pro &rarr;', 'independent-analytics'), '<span style="color: #F69D0A;">' . \esc_html__('Upgrade to Pro &rarr;', 'independent-analytics') . '</span>', \IAWP\Capability_Manager::menu_page_capability_string(), \esc_url('https://independentwp.com/pro/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Upgrade+to+Pro&utm_content=Sidebar'));
        }
        if (\IAWP\Capability_Manager::can_edit()) {
            \add_submenu_page('options.php', \__('Independent Analytics Debug'), \__('Independent Analytics Debug'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-debug', function () {
                $debug_page = new Debug_Page();
                $debug_page->render(\false);
            });
        }
        if (\IAWPSCOPED\iawp_is_pro() && \IAWP\Capability_Manager::can_view()) {
            \add_submenu_page('options.php', \__('Journey'), \__('Journey'), \IAWP\Capability_Manager::menu_page_capability_string(), 'independent-analytics-visitor', function () {
                $page = new Visitor_Page();
                $page->render();
            });
        }
    }
    // The menu link is removed in the SDK setup, but this makes it completely inaccessible
    public function remove_freemius_pricing_menu()
    {
        \remove_submenu_page('independent-analytics', 'independent-analytics-pricing');
    }
    public function register_scripts_and_styles() : void
    {
        \wp_register_style('iawp-styles', \IAWPSCOPED\iawp_url_to('dist/styles/style.css'), [], \IAWP_VERSION);
        \wp_register_style('iawp-dashboard-widget-styles', \IAWPSCOPED\iawp_url_to('dist/styles/dashboard_widget.css'), [], \IAWP_VERSION);
        \wp_register_style('iawp-freemius-notice-styles', \IAWPSCOPED\iawp_url_to('dist/styles/freemius_notice_styles.css'), [], \IAWP_VERSION);
        \wp_register_style('iawp-posts-menu-styles', \IAWPSCOPED\iawp_url_to('dist/styles/posts_menu.css'), [], \IAWP_VERSION);
        \wp_register_style('iawp-wc-order-box-styles', \IAWPSCOPED\iawp_url_to('dist/styles/wc_order_box.css'), [], \IAWP_VERSION);
        \wp_register_script('iawp-javascript', \IAWPSCOPED\iawp_url_to('dist/js/index.js'), ['wp-i18n'], \IAWP_VERSION);
        \wp_set_script_translations('iawp-javascript', 'independent-analytics');
        \wp_register_script('iawp-dashboard-widget-javascript', \IAWPSCOPED\iawp_url_to('dist/js/dashboard_widget.js'), ['wp-i18n'], \IAWP_VERSION);
        \wp_set_script_translations('iawp-dashboard-widget-javascript', 'independent-analytics');
        \wp_register_script('iawp-layout-javascript', \IAWPSCOPED\iawp_url_to('dist/js/layout.js'), ['wp-i18n'], \IAWP_VERSION);
        \wp_set_script_translations('iawp-layout-javascript', 'independent-analytics');
        \wp_register_script('iawp-settings-javascript', \IAWPSCOPED\iawp_url_to('dist/js/settings.js'), ['wp-color-picker', 'wp-i18n'], \IAWP_VERSION);
        \wp_set_script_translations('iawp-settings-javascript', 'independent-analytics');
        \wp_register_script('iawp-click-tracking-menu-javascript', \IAWPSCOPED\iawp_url_to('dist/js/click-tracking-menu.js'), ['wp-i18n'], \IAWP_VERSION);
        \wp_set_script_translations('iawp-click-tracking-menu-javascript', 'independent-analytics');
        if (\IAWP\Admin_Bar_Stats::is_option_enabled()) {
            \wp_register_style('iawp-admin-bar-stats', \IAWPSCOPED\iawp_url_to('dist/styles/admin_bar_stats.css'), [], \IAWP_VERSION);
        }
        if (\is_rtl()) {
            \wp_register_style('iawp-styles-rtl', \IAWPSCOPED\iawp_url_to('dist/styles/rtl.css'), [], \IAWP_VERSION);
        }
    }
    public function enqueue_scripts_and_styles($hook)
    {
        $this->register_scripts_and_styles();
        $page = \IAWP\Env::get_page();
        $this->enqueue_translations();
        $this->enqueue_nonces();
        \wp_enqueue_style('iawp-freemius-notice-styles');
        if (\is_string($page)) {
            \wp_enqueue_style('iawp-styles');
            \wp_enqueue_script('iawp-javascript');
            \wp_enqueue_script('iawp-layout-javascript');
            $this->dequeue_bad_actors();
            $this->maybe_override_adminify_styles();
            if (\is_rtl()) {
                \wp_enqueue_style('iawp-styles-rtl');
            }
        }
        if ($page === 'independent-analytics-settings') {
            \wp_enqueue_style('wp-color-picker');
            \wp_enqueue_script('iawp-settings-javascript');
        } elseif ($page === 'independent-analytics-click-tracking') {
            \wp_enqueue_script('iawp-click-tracking-menu-javascript');
        } elseif ($hook === 'index.php') {
            \wp_enqueue_script('iawp-dashboard-widget-javascript');
            \wp_enqueue_style('iawp-dashboard-widget-styles');
        } elseif ($hook === 'edit.php') {
            \wp_enqueue_style('iawp-posts-menu-styles');
        } elseif (\IAWPSCOPED\iawp_is_pro() && $this->is_woocommerce_support_enabled() && $hook == 'woocommerce_page_wc-orders') {
            \wp_enqueue_style('iawp-wc-order-box-styles');
        }
        if (\IAWP\Admin_Bar_Stats::is_option_enabled()) {
            \wp_enqueue_style('iawp-admin-bar-stats');
        }
    }
    public function enqueue_scripts_and_styles_front_end()
    {
        if (\IAWP\Admin_Bar_Stats::is_option_enabled()) {
            \wp_register_style('iawp-admin-bar-stats', \IAWPSCOPED\iawp_url_to('dist/styles/admin_bar_stats.css'), [], \IAWP_VERSION);
            \wp_enqueue_style('iawp-admin-bar-stats');
        }
    }
    public function enqueue_translations()
    {
        \wp_register_script('iawp-translations', '');
        \wp_enqueue_script('iawp-translations');
        \wp_add_inline_script('iawp-translations', 'const iawpText = ' . \json_encode(['views' => \__('Views', 'independent-analytics'), 'visitors' => \__('Visitors', 'independent-analytics'), 'sessions' => \__('Sessions', 'independent-analytics'), 'exactDates' => \__('Apply Exact Dates', 'independent-analytics'), 'relativeDates' => \__('Apply Relative Dates', 'independent-analytics'), 'copied' => \__('Copied', 'independent-analytics'), 'exportingPages' => \__('Exporting Pages...', 'independent-analytics'), 'exportPages' => \__('Export Pages', 'independent-analytics'), 'exportingReferrers' => \__('Exporting Referrers...', 'independent-analytics'), 'exportReferrers' => \__('Export Referrers', 'independent-analytics'), 'exportingGeolocations' => \__('Exporting Geolocations...', 'independent-analytics'), 'exportGeolocations' => \__('Export Geolocations', 'independent-analytics'), 'exportingDevices' => \__('Exporting Devices...', 'independent-analytics'), 'exportDevices' => \__('Export Devices', 'independent-analytics'), 'exportingCampaigns' => \__('Exporting Campaigns...', 'independent-analytics'), 'exportCampaigns' => \__('Export Campaigns', 'independent-analytics'), 'exportingClicks' => \__('Exporting Clicks', 'independent-analytics'), 'exportClicks' => \__('Export Clicks', 'independent-analytics'), 'invalidReportArchive' => \__('This report archive is invalid. Please export your reports and try again.', 'independent-analytics'), 'openMobileMenu' => \__('Open menu', 'independent-analytics'), 'closeMobileMenu' => \__('Close menu', 'independent-analytics'), 'noComparison' => \__('No Comparison', 'independent-analytics')]), 'before');
    }
    public function enqueue_nonces()
    {
        \wp_register_script('iawp-nonces', '');
        \wp_enqueue_script('iawp-nonces');
        \wp_add_inline_script('iawp-nonces', 'const iawpActions = ' . \json_encode(AJAX_Manager::getInstance()->get_action_signatures()), 'before');
    }
    public function get_option($name, $default)
    {
        $option = \get_option($name, $default);
        return $option === '' ? $default : $option;
    }
    public function date_i18n(string $format, \DateTime $date) : string
    {
        return \date_i18n($format, $date->setTimezone(Timezone::site_timezone())->getTimestamp() + Timezone::site_offset_in_seconds($date));
    }
    public function plugin_action_links($links)
    {
        // Create the link
        $settings_link = '<a class="calendar-link" href="' . \esc_url(\IAWPSCOPED\iawp_dashboard_url()) . '">' . \esc_html__('Analytics Dashboard', 'independent-analytics') . '</a>';
        // Add the link to the start of the array
        \array_unshift($links, $settings_link);
        return $links;
    }
    public function pagination_page_size()
    {
        return 50;
    }
    public function dequeue_bad_actors()
    {
        // https://wordpress.org/plugins/comment-link-remove/
        \wp_dequeue_style('qc_clr_admin_style_css');
        // https://wordpress.org/plugins/webappick-pdf-invoice-for-woocommerce/
        \wp_dequeue_style('woo-invoice');
        // https://wordpress.org/plugins/wp-media-files-name-rename/
        \wp_dequeue_style('wpcmp_bootstrap_css');
        // https://wordpress.org/plugins/morepuzzles/
        \wp_dequeue_style('bscss');
        \wp_dequeue_style('mypluginstyle');
    }
    public function maybe_override_adminify_styles()
    {
        if (\is_plugin_active('adminify/adminify.php')) {
            $settings = \get_option('_wpadminify');
            if ($settings) {
                if (\array_key_exists('admin_ui', $settings)) {
                    if ($settings['admin_ui']) {
                        \wp_register_style('iawp-adminify-styles', \IAWPSCOPED\iawp_url_to('dist/styles/adminify.css'), [], \IAWP_VERSION);
                        \wp_enqueue_style('iawp-adminify-styles');
                    }
                }
            }
        }
    }
    public function maybe_add_cookie()
    {
        if (Request::is_blocked_user_role() && $this->get_option('iawp_ignore_via_cookie', \false)) {
            if (!isset($_COOKIE['iawp_ignore_visitor'])) {
                \setcookie('iawp_ignore_visitor', '1', \time() + 365 * 24 * 60 * 60, \COOKIEPATH, \COOKIE_DOMAIN);
            }
        }
    }
    public function changelog_viewed_since_update() : bool
    {
        if (\number_format(\floatval(\IAWP_VERSION), 1) > \floatval($this->get_option('iawp_last_update_viewed', '0'))) {
            return \true;
        }
        return \false;
    }
    public function is_form_submission_support_enabled() : bool
    {
        if (!\is_bool($this->is_form_submission_support_enabled)) {
            $this->is_form_submission_support_enabled = \IAWPSCOPED\iawp_is_pro() && Form::has_active_form_plugin() && \IAWP\Capability_Manager::can_view_all_analytics();
        }
        return $this->is_form_submission_support_enabled;
    }
    public function is_woocommerce_support_enabled() : bool
    {
        if (!\is_bool($this->is_woocommerce_support_enabled)) {
            $this->is_woocommerce_support_enabled = $this->actually_check_if_woocommerce_support_is_enabled();
        }
        return $this->is_woocommerce_support_enabled;
    }
    public function is_surecart_support_enabled() : bool
    {
        if (!\is_bool($this->is_surecart_support_enabled)) {
            $this->is_surecart_support_enabled = $this->actually_check_if_surecart_support_is_enabled();
        }
        return $this->is_surecart_support_enabled;
    }
    public function is_edd_support_enabled() : bool
    {
        if (!\is_bool($this->is_edd_support_enabled)) {
            $this->is_edd_support_enabled = $this->actually_check_if_edd_support_is_enabled();
        }
        return $this->is_edd_support_enabled;
    }
    public function is_pmpro_support_enabled() : bool
    {
        if (!\is_bool($this->is_pmpro_support_enabled)) {
            $this->is_pmpro_support_enabled = $this->actually_check_if_pmpro_support_is_enabled();
        }
        return $this->is_pmpro_support_enabled;
    }
    public function is_fluent_cart_support_enabled() : bool
    {
        if (!\is_bool($this->is_fluent_cart_support_enabled)) {
            $this->is_fluent_cart_support_enabled = $this->actually_check_if_fluent_cart_support_is_enabled();
        }
        return $this->is_fluent_cart_support_enabled;
    }
    public function is_ecommerce_support_enabled() : bool
    {
        return $this->is_woocommerce_support_enabled() || $this->is_surecart_support_enabled() || $this->is_edd_support_enabled() || $this->is_pmpro_support_enabled() || $this->is_fluent_cart_support_enabled();
    }
    public function get_currency_code() : ?string
    {
        if ($this->is_woocommerce_support_enabled()) {
            return get_woocommerce_currency();
        }
        if ($this->is_surecart_support_enabled()) {
            return SureCart_Store::get_currency_code();
        }
        if ($this->is_edd_support_enabled()) {
            return \edd_get_currency();
        }
        if ($this->is_pmpro_support_enabled()) {
            global $pmpro_default_currency;
            return $pmpro_default_currency;
        }
        if ($this->is_fluent_cart_support_enabled()) {
            $settings = new \FluentCart\Api\StoreSettings();
            return $settings->getCurrency();
        }
        return null;
    }
    // This is for compatibility with the "Lock and Protect System Folders" setting in the Security Optimizer plugin
    public function whitelist_click_endpoint($whitelist)
    {
        if (\IAWPSCOPED\iawp_is_free()) {
            return $whitelist;
        }
        $whitelist[] = 'iawp-click-endpoint.php';
        return $whitelist;
    }
    public function exclude_tracking_script_from_speed_optimizer($exclude_list)
    {
        $exclude_list[] = '// Do not change this comment line otherwise Speed Optimizer won\'t be able to detect this script';
        return $exclude_list;
    }
    // This whitelists our plugin with the "Complianz" plugin
    public function whitelist_script_tag_for_complianz($scripts)
    {
        $scripts[] = '/wp-json/iawp/search';
        return $scripts;
    }
    public function prefers_24_hour_clock() : bool
    {
        $time_format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
        if (Str::contains($time_format, 'a') || Str::contains($time_format, 'A')) {
            return \false;
        }
        return \true;
    }
    private function actually_check_if_woocommerce_support_is_enabled() : bool
    {
        global $wpdb;
        if (\IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        if (\IAWP\Capability_Manager::can_only_view_authored_analytics()) {
            return \false;
        }
        if (!\is_plugin_active('woocommerce/woocommerce.php')) {
            return \false;
        }
        $table_name = $wpdb->prefix . 'wc_order_stats';
        $order_stats_table = $wpdb->get_row($wpdb->prepare('
                SELECT * FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
            ', $wpdb->dbname, $table_name));
        if (\is_null($order_stats_table)) {
            return \false;
        }
        return \true;
    }
    private function actually_check_if_surecart_support_is_enabled() : bool
    {
        if (\IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        if (\IAWP\Capability_Manager::can_only_view_authored_analytics()) {
            return \false;
        }
        return \is_plugin_active('surecart/surecart.php');
    }
    private function actually_check_if_edd_support_is_enabled() : bool
    {
        if (\IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        if (\IAWP\Capability_Manager::can_only_view_authored_analytics()) {
            return \false;
        }
        return \is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') || \is_plugin_active('easy-digital-downloads-pro/easy-digital-downloads.php');
    }
    private function actually_check_if_pmpro_support_is_enabled() : bool
    {
        if (\IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        if (\IAWP\Capability_Manager::can_only_view_authored_analytics()) {
            return \false;
        }
        return \is_plugin_active('paid-memberships-pro-dev/paid-memberships-pro.php') || \is_plugin_active('paid-memberships-pro/paid-memberships-pro.php');
    }
    private function actually_check_if_fluent_cart_support_is_enabled() : bool
    {
        if (\IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        if (\IAWP\Capability_Manager::can_only_view_authored_analytics()) {
            return \false;
        }
        return \is_plugin_active('fluent-cart/fluent-cart.php');
    }
    private function get_menu_icon()
    {
        if (\is_null(\IAWP\Env::get_page())) {
            return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iNTEzcHgiIGhlaWdodD0iMjU0cHgiIHZpZXdCb3g9IjAgMCA1MTMgMjU0IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPHRpdGxlPkljb248L3RpdGxlPgogICAgPGcgaWQ9IlBhZ2UtMSIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPGcgaWQ9Ik1lbnUtSWNvbiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTEyNiwgLTIyOCkiIGZpbGw9IiNBN0FBQUQiPgogICAgICAgICAgICA8ZyBpZD0iV2hpdGUiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDgyLCA1NSkiPgogICAgICAgICAgICAgICAgPGcgaWQ9Ikljb24iIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQ0LjUsIDE3MykiPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTMiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQwMi44MDU3LCAxMDguNjEyNSkgcm90YXRlKDQ1KSB0cmFuc2xhdGUoLTQwMi44MDU3LCAtMTA4LjYxMjUpIiBwb2ludHM9IjM5MS4xNjkzNTYgNTYuMjQ4ODI1OSA0MTQuNDQyMDgzIDU2LjI0ODgyNTkgNDE0LjQ0MjA4MyAxNjAuOTc2MDk5IDM5MS4xNjkzNTYgMTYwLjk3NjA5OSI+PC9wb2x5Z29uPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTIiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI1NiwgMTI1LjY3MjcpIHJvdGF0ZSgtNjApIHRyYW5zbGF0ZSgtMjU2LCAtMTI1LjY3MjcpIiBwb2ludHM9IjI0NC4zNjM2MzYgNzMuMzA5MDkwOSAyNjcuNjM2MzY0IDczLjMwOTA5MDkgMjY3LjYzNjM2NCAxNzguMDM2MzY0IDI0NC4zNjM2MzYgMTc4LjAzNjM2NCI+PC9wb2x5Z29uPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTEiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEwOS41Njk0LCAxNDQuNjg1Mikgcm90YXRlKDQ1KSB0cmFuc2xhdGUoLTEwOS41Njk0LCAtMTQ0LjY4NTIpIiBwb2ludHM9Ijk3LjkzMjk5MjMgOTIuMzIxNTUzMiAxMjEuMjA1NzIgOTIuMzIxNTUzMiAxMjEuMjA1NzIgMTk3LjA0ODgyNiA5Ny45MzI5OTIzIDE5Ny4wNDg4MjYiPjwvcG9seWdvbj4KICAgICAgICAgICAgICAgICAgICA8Y2lyY2xlIGlkPSJQb2ludC00IiBjeD0iNDY4Ljk0NTQ1NSIgY3k9IjQzLjA1NDU0NTUiIHI9IjQzLjA1NDU0NTUiPjwvY2lyY2xlPgogICAgICAgICAgICAgICAgICAgIDxjaXJjbGUgaWQ9IlBvaW50LTMiIGN4PSIzMzYuMjkwOTA5IiBjeT0iMTczLjM4MTgxOCIgcj0iNDMuMDU0NTQ1NSI+PC9jaXJjbGU+CiAgICAgICAgICAgICAgICAgICAgPGNpcmNsZSBpZD0iUG9pbnQtMiIgY3g9IjE3NS43MDkwOTEiIGN5PSI3OS4xMjcyNzI3IiByPSI0My4wNTQ1NDU1Ij48L2NpcmNsZT4KICAgICAgICAgICAgICAgICAgICA8Y2lyY2xlIGlkPSJQb2ludC0xIiBjeD0iNDMuMDU0NTQ1NSIgY3k9IjIxMC42MTgxODIiIHI9IjQzLjA1NDU0NTUiPjwvY2lyY2xlPgogICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICA8L2c+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4=';
        } else {
            return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iNTEzcHgiIGhlaWdodD0iMjU0cHgiIHZpZXdCb3g9IjAgMCA1MTMgMjU0IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPHRpdGxlPkljb248L3RpdGxlPgogICAgPGcgaWQ9IlBhZ2UtMSIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPGcgaWQ9Ik1lbnUtSWNvbiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTc3NSwgLTIyOSkiIGZpbGw9IiNGRkZGRkYiPgogICAgICAgICAgICA8ZyBpZD0iV2hpdGUtQ29weSIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoNzMxLCA1NikiPgogICAgICAgICAgICAgICAgPGcgaWQ9Ikljb24iIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQ0LjUsIDE3MykiPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTMiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQwMi44MDU3LCAxMDguNjEyNSkgcm90YXRlKDQ1KSB0cmFuc2xhdGUoLTQwMi44MDU3LCAtMTA4LjYxMjUpIiBwb2ludHM9IjM5MS4xNjkzNTYgNTYuMjQ4ODI1OSA0MTQuNDQyMDgzIDU2LjI0ODgyNTkgNDE0LjQ0MjA4MyAxNjAuOTc2MDk5IDM5MS4xNjkzNTYgMTYwLjk3NjA5OSI+PC9wb2x5Z29uPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTIiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI1NiwgMTI1LjY3MjcpIHJvdGF0ZSgtNjApIHRyYW5zbGF0ZSgtMjU2LCAtMTI1LjY3MjcpIiBwb2ludHM9IjI0NC4zNjM2MzYgNzMuMzA5MDkwOSAyNjcuNjM2MzY0IDczLjMwOTA5MDkgMjY3LjYzNjM2NCAxNzguMDM2MzY0IDI0NC4zNjM2MzYgMTc4LjAzNjM2NCI+PC9wb2x5Z29uPgogICAgICAgICAgICAgICAgICAgIDxwb2x5Z29uIGlkPSJOZWNrLTEiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEwOS41Njk0LCAxNDQuNjg1Mikgcm90YXRlKDQ1KSB0cmFuc2xhdGUoLTEwOS41Njk0LCAtMTQ0LjY4NTIpIiBwb2ludHM9Ijk3LjkzMjk5MjMgOTIuMzIxNTUzMiAxMjEuMjA1NzIgOTIuMzIxNTUzMiAxMjEuMjA1NzIgMTk3LjA0ODgyNiA5Ny45MzI5OTIzIDE5Ny4wNDg4MjYiPjwvcG9seWdvbj4KICAgICAgICAgICAgICAgICAgICA8Y2lyY2xlIGlkPSJQb2ludC00IiBjeD0iNDY4Ljk0NTQ1NSIgY3k9IjQzLjA1NDU0NTUiIHI9IjQzLjA1NDU0NTUiPjwvY2lyY2xlPgogICAgICAgICAgICAgICAgICAgIDxjaXJjbGUgaWQ9IlBvaW50LTMiIGN4PSIzMzYuMjkwOTA5IiBjeT0iMTczLjM4MTgxOCIgcj0iNDMuMDU0NTQ1NSI+PC9jaXJjbGU+CiAgICAgICAgICAgICAgICAgICAgPGNpcmNsZSBpZD0iUG9pbnQtMiIgY3g9IjE3NS43MDkwOTEiIGN5PSI3OS4xMjcyNzI3IiByPSI0My4wNTQ1NDU1Ij48L2NpcmNsZT4KICAgICAgICAgICAgICAgICAgICA8Y2lyY2xlIGlkPSJQb2ludC0xIiBjeD0iNDMuMDU0NTQ1NSIgY3k9IjIxMC42MTgxODIiIHI9IjQzLjA1NDU0NTUiPjwvY2lyY2xlPgogICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICA8L2c+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4=';
        }
    }
    private function configure_carbon()
    {
        $locale = \get_locale();
        // Carbon will throw an error if de_DE_Formal is used
        if ('de_de_formal' === \strtolower($locale)) {
            $locale = 'de_DE';
        }
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);
        CarbonInterval::setLocale($locale);
    }
}
