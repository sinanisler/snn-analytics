<?php

namespace IAWP;

use IAWP\Utils\String_Util;
/** @internal */
class Plugin_Conflict_Detector
{
    private $plugin;
    private $error;
    public function __construct()
    {
        $check = $this->run_conflict_check();
        if (!\is_null($check)) {
            $this->plugin = $check['plugin'];
            $this->error = $check['error'];
        }
    }
    /**
     * Did the health check pass?
     *
     * @return bool
     */
    public function has_conflict() : bool
    {
        return !empty($this->error);
    }
    /**
     * Returns the plugin name, if any
     *
     * @return string|null
     */
    public function get_plugin() : ?string
    {
        return $this->plugin;
    }
    /**
     * Returns the health check error, if any
     *
     * @return string|null
     */
    public function get_error() : ?string
    {
        return $this->error;
    }
    public function plugin_requiring_logged_in_tracking()
    {
        if (\is_plugin_active('woocommerce/woocommerce.php')) {
            return 'WooCommerce';
        } elseif (\is_plugin_active('surecart/surecart.php')) {
            return 'SureCart';
        } elseif (\is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
            return 'Paid Memberships Pro';
        } elseif (\is_plugin_active('ultimate-member/ultimate-member.php')) {
            return 'Ultimate Member';
        } elseif (\is_plugin_active('simple-membership/simple-wp-membership.php')) {
            return 'Simple WordPress Membership';
        } elseif (\is_plugin_active('members/members.php')) {
            return 'Members plugin';
        }
        return \false;
    }
    /**
     * @return string|null Returns a string error message if the health check fails
     */
    private function run_conflict_check() : ?array
    {
        if (\is_plugin_active('disable-wp-rest-api/disable-wp-rest-api.php')) {
            return ['plugin' => 'disable-wp-rest-api', 'error' => \__('The "Disable WP REST API" plugin needs to be deactivated because Independent Analytics uses the REST API to record visits.', 'independent-analytics')];
        }
        if (\is_plugin_active('all-in-one-wp-security-and-firewall/wp-security.php')) {
            $settings = \get_option('aio_wp_security_configs', []);
            if (\is_array($settings) && \array_key_exists('aiowps_disallow_unauthorized_rest_requests', $settings)) {
                if ($settings['aiowps_disallow_unauthorized_rest_requests'] == 1) {
                    return ['plugin' => 'wp-security', 'error' => \__('The "All In One WP Security" plugin is blocking REST API requests, which Independent Analytics needs to record views. Please disable this setting via the WP Security > Miscellaneous menu.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('disable-json-api/disable-json-api.php')) {
            $settings = \get_option('disable_rest_api_options', []);
            if (\is_array($settings) && \array_key_exists('roles', $settings)) {
                if ($settings['roles']['none']['default_allow'] == \false) {
                    if ($settings['roles']['none']['allow_list']['/iawp/search'] == \false) {
                        return ['plugin' => 'disable-json-api', 'error' => \__('The "Disable REST API" plugin is blocking REST API requests for unauthenticated users, which Independent Analytics needs to record views. Please enable the /iawp/search route, so Independent Analytics can track your visitors.', 'independent-analytics')];
                    }
                }
            }
        }
        if (\is_plugin_active('disable-xml-rpc-api/disable-xml-rpc-api.php')) {
            $settings = \get_option('dsxmlrpc-settings');
            if (\is_array($settings) && \array_key_exists('json-rest-api', $settings)) {
                if ($settings['json-rest-api'] == 1) {
                    return ['plugin' => 'disable-xml-rpc-api', 'error' => \__('The "Disable XML-RPC-API" plugin is blocking REST API requests, which Independent Analytics needs to record views. Please visit the Security Settings menu and turn off the "Disable JSON REST API" option, so Independent Analytics can track your visitors.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('wpo-tweaks/wpo-tweaks.php')) {
            return ['plugin' => 'wpo-tweaks', 'error' => \__('The "WPO Tweaks & Optimizations" plugin needs to be deactivated because it is disabling the REST API, which Independent Analytics uses to record visits.', 'independent-analytics')];
        }
        if (\is_plugin_active('all-in-one-intranet/basic_all_in_one_intranet.php')) {
            return ['plugin' => 'basic_all_in_one_intranet', 'error' => \__('The "All-In-One Intranet" plugin needs to be deactivated because it is disabling the REST API, which Independent Analytics uses to record visits. You may want to try the "My Private Site" plugin instead.', 'independent-analytics')];
        }
        if (\is_plugin_active('wp-security-hardening/wp-hardening.php')) {
            $settings = \get_option('whp_fixer_option');
            if (\is_array($settings) && \array_key_exists('disable_json_api', $settings)) {
                if ($settings['disable_json_api'] != 'off') {
                    return ['plugin' => 'wp-hardening', 'error' => \__('The "WP Hardening" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the WP Hardening > Security Fixers menu and turn off the "Disable WP API JSON" option, so Independent Analytics can track your visitors.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('wp-rest-api-authentication/miniorange-api-authentication.php')) {
            $settings = \get_option('mo_api_authentication_protectedrestapi_route_whitelist');
            if (\is_array($settings) && \in_array('/iawp/search', $settings)) {
                return ['plugin' => 'miniorange-api-authentication', 'error' => \__('The "WordPress REST API Authentication" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the miniOrange API Authentication > Protected REST APIs menu and uncheck the "/iawp/search" box to allow Independent Analytics to track your visitors.', 'independent-analytics')];
            }
        }
        if (\is_plugin_active('ninjafirewall/ninjafirewall.php')) {
            $settings = \get_option('nfw_options');
            if (\is_array($settings) && \array_key_exists('no_restapi', $settings)) {
                if ($settings['no_restapi'] == 1) {
                    return ['plugin' => 'ninjafirewall', 'error' => \__('The "NinjaFirewall" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the NinjaFirewall > Firewall Policies menu and uncheck the "Block any access to the API" checkbox to allow Independent Analytics to track your visitors.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('wp-cerber/wp-cerber.php')) {
            // This option has been renamed before. If there's an issue in here, check that it wasn't renamed again.
            $settings = \get_option('cerber_configuration');
            if (!\is_array($settings)) {
                $settings = \get_option('cerber-hardening');
            }
            if (\is_array($settings) && \array_key_exists('norest', $settings)) {
                if ($settings['norest'] === '1') {
                    if (\is_array($settings['restwhite']) && !\in_array('iawp', $settings['restwhite']) || \is_string($settings['restwhite']) && !String_Util::str_contains($settings['restwhite'], 'iawp')) {
                        return ['plugin' => 'wp-cerber', 'error' => \__('The "WP Cerber" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the WP Cerber > Dashboard > Hardening menu and add "iawp" to your allowed namespaces. This will keep the REST API locked down while allowing requests for Independent Analytics.', 'independent-analytics')];
                    }
                }
            }
        }
        if (\is_plugin_active('wp-simple-firewall/icwp-wpsf.php')) {
            // This option has been renamed before. If there's an issue in here, check that it wasn't renamed again.
            $settings = \get_option('icwp_wpsf_opts_all');
            if (!\is_array($settings)) {
                $settings = \get_option('icwp_wpsf_opts_free');
            }
            if (\is_array($settings) && \array_key_exists('lockdown', $settings)) {
                if (\array_key_exists('disable_anonymous_restapi', $settings['lockdown'])) {
                    if ($settings['lockdown']['disable_anonymous_restapi'] == 'Y') {
                        if (\array_key_exists('api_namespace_exclusions', $settings['lockdown'])) {
                            if (!\in_array('iawp', $settings['lockdown']['api_namespace_exclusions'])) {
                                return ['plugin' => 'icwp-wpsf', 'error' => \__('The "Shield Security" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the Shield Security > Config > Lockdown menu and add "iawp" to your allowed namespaces. This will keep the REST API locked down while allowing requests for Independent Analytics.', 'independent-analytics')];
                            }
                        }
                    }
                }
            }
        }
        if (\is_plugin_active('wp-hide-security-enhancer/wp-hide.php')) {
            $settings = \get_option('wph_settings');
            if (\is_array($settings) && \array_key_exists('module_settings', $settings)) {
                if (\array_key_exists('disable_json_rest_v2', $settings['module_settings'])) {
                    if ($settings['module_settings']['disable_json_rest_v2'] == 'yes') {
                        return ['plugin' => 'wp-hide', 'error' => \__('The "WP Hide" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the WP Hide > Rewrite URLs menu and switch the "Disable JSON REST V2 service" option to "No."', 'independent-analytics')];
                    }
                }
                if (\array_key_exists('block_json_rest', $settings['module_settings'])) {
                    if ($settings['module_settings']['block_json_rest'] == 'yes' || $settings['module_settings']['block_json_rest'] == 'non-logged-in') {
                        return ['plugin' => 'wp-hide', 'error' => \__('The "WP Hide" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the WP Hide > Rewrite URLs menu and switch the "Block any JSON REST calls" option to "No."', 'independent-analytics')];
                    }
                }
            }
        }
        if (\is_plugin_active('admin-site-enhancements/admin-site-enhancements.php')) {
            $settings = \get_option('admin_site_enhancements');
            if (\is_array($settings) && \array_key_exists('disable_rest_api', $settings)) {
                if ($settings['disable_rest_api']) {
                    return ['plugin' => 'admin-site-enhancements', 'error' => \__('The "Admin and Site Enhancements" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the Tools > Enhancements menu, click on the "Disable Components" section, and deselect the "Disable REST API" setting to allow Independent Analytics to track your visitors.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('admin-site-enhancements-pro/admin-site-enhancements.php')) {
            $settings = \get_option('admin_site_enhancements');
            if (\is_array($settings) && \array_key_exists('disable_rest_api', $settings)) {
                if ($settings['disable_rest_api']) {
                    if (\is_string($settings['disable_rest_api_excluded_routes']) && !String_Util::str_contains($settings['disable_rest_api_excluded_routes'], 'iawp/search')) {
                        return ['plugin' => 'admin-site-enhancements', 'error' => \__('The "Admin and Site Enhancements Pro" plugin is blocking the REST API, which Independent Analytics needs to record views. Please visit the Tools > Enhancements menu, click on the "Disable Components" section, and then click the "Expand" link under "Disable REST API." You can then enter "iawp/search" into the textarea to whitelist the route used by Independent Analytics.', 'independent-analytics')];
                    }
                }
            }
        }
        if (\is_plugin_active('autoptimize/autoptimize.php')) {
            if (\get_option('autoptimize_js_aggregate') == 'on' && \get_option('autoptimize_js_include_inline') == 'on') {
                return ['plugin' => 'autoptimize', 'error' => \__('A setting in the "Autoptimize" plugin is preventing Independent Analytics from tracking visitors. Please visit the Settings > Autoptimize menu and uncheck the "Also aggregate inline JS" option.', 'independent-analytics')];
            }
        }
        if (\is_plugin_active('patchstack/patchstack.php')) {
            if (\get_option('patchstack_json_is_disabled') == '1') {
                return ['plugin' => 'patchstack', 'error' => \__('The Patchstack Security plugin has disabled the REST API, which Independent Analytics needs to record visitors. Please login to the Patchstack app, navigate to the Hardening menu for this site, and turn off the "Restrict WP REST API access" option.', 'independent-analytics')];
            }
        }
        if (\is_plugin_active('rest-api-toolbox/rest-api-toolbox.php')) {
            $settings = \get_option('rest-api-toolbox-settings-general');
            if (\is_array($settings) && \array_key_exists('disable-rest-api', $settings)) {
                if ($settings['disable-rest-api'] == '1') {
                    return ['plugin' => 'rest-api-toolbox', 'error' => \__('The REST API Toolbox plugin has disabled the REST API, which Independent Analytics needs to record visitors. Please visit the Settings > REST API Toolbox menu and uncheck the "Disable REST API" option to allow Independent Analytics to track your visitors.', 'independent-analytics')];
                }
            }
        }
        if (\is_plugin_active('minify-html-markup/minify-html.php')) {
            // Note: both options enabled by default before anything is saved in the DB. `false` is unset, `no` is saved as no
            if (\get_option('minify_html_active') !== 'no' && \get_option('minify_javascript') !== 'no') {
                return ['plugin' => 'minify-html-markup', 'error' => \__('The Minify HTML plugin is preventing Independent Analytics from tracking visitors. Please visit the Settings > Minify HTML menu and disable the option called "Minify inline JavaScript" to resume tracking.', 'independent-analytics')];
            }
        }
        if (\is_plugin_active('falcon/falcon.php')) {
            $settings = \get_option('falcon');
            $show_warning = \false;
            // Note: REST API is disabled by default without any options saved in the DB
            if ($settings === \false) {
                $show_warning = \true;
            }
            if (\is_array($settings) && \array_key_exists('features', $settings)) {
                if (\in_array('no_rest_api', $settings['features'])) {
                    $show_warning = \true;
                }
            }
            if ($show_warning) {
                return ['plugin' => 'falcon', 'error' => \__('The Falcon plugin has disabled the REST API for anonymous visitors, which Independent Analytics needs to record stats. Please visit the Settings > Falcon menu, open the Security section, and uncheck the "Disable REST API for unauthenticated requests" option to allow Independent Analytics to record stats.', 'independent-analytics')];
            }
        }
        return null;
    }
}
