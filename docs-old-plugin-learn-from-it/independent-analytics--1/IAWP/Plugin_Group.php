<?php

namespace IAWP;

use IAWP\Form_Submissions\Form;
/** @internal */
class Plugin_Group
{
    private $id;
    private $name;
    private $requires_pro;
    private $has_active_group_plugins;
    private $upgrade_message;
    private $upgrade_link;
    private $activate_message;
    private $activate_link;
    private $no_tracked_data_message;
    /**
     * @param array{id: string, name: string, requires_pro?: bool, has_active_group_plugins?: bool, upgrade_message?: string, upgrade_link?: string, activate_message?: string, activate_link?: string, no_tracked_data_message?: string} $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = $attributes['id'];
        $this->name = $attributes['name'];
        $this->requires_pro = $attributes['requires_pro'] ?? \false;
        $this->has_active_group_plugins = $attributes['has_active_group_plugins'] ?? \true;
        $this->upgrade_message = $attributes['upgrade_message'] ?? null;
        $this->upgrade_link = $attributes['upgrade_link'] ?? null;
        $this->activate_message = $attributes['activate_message'] ?? null;
        $this->activate_link = $attributes['activate_link'] ?? null;
        $this->no_tracked_data_message = $attributes['no_tracked_data_message'] ?? null;
    }
    public function id() : string
    {
        return $this->id;
    }
    public function name() : string
    {
        return $this->name;
    }
    public function requires_pro() : bool
    {
        return $this->requires_pro;
    }
    public function has_active_group_plugins() : bool
    {
        return $this->has_active_group_plugins;
    }
    public function upgrade_message() : ?string
    {
        return $this->upgrade_message;
    }
    public function upgrade_link() : ?string
    {
        return $this->upgrade_link;
    }
    public function activate_message() : ?string
    {
        return $this->activate_message;
    }
    public function activate_link() : ?string
    {
        return $this->activate_link;
    }
    public function no_tracked_data_message() : ?string
    {
        return $this->no_tracked_data_message;
    }
    public function has_tracked_data() : bool
    {
        global $wpdb;
        $form_submissions_table = \IAWP\Query::get_table_name(\IAWP\Query::FORM_SUBMISSIONS);
        switch ($this->id) {
            case 'forms':
                $value = $wpdb->get_var("SELECT EXISTS (SELECT 1 FROM {$form_submissions_table})");
                return $value === "1";
            default:
                return \true;
        }
    }
    public static function get_plugin_group(string $plugin_group_id) : \IAWP\Plugin_Group
    {
        $plugin_groups = self::get_plugin_groups();
        foreach ($plugin_groups as $plugin_group) {
            if ($plugin_group->id() == $plugin_group_id) {
                return $plugin_group;
            }
        }
        // Added to satisfy PHPStan
        return $plugin_groups[0];
    }
    /**
     * @return Plugin_Group[]
     */
    public static function get_plugin_groups() : array
    {
        return [new self(['id' => 'general', 'name' => \__('General', 'independent-analytics')]), new self(['id' => 'conversions', 'name' => \__('Conversions', 'independent-analytics')]), new self(['id' => 'campaigns', 'name' => \__('Campaigns', 'independent-analytics')]), new self(['id' => 'ecommerce', 'name' => self::get_ecommerce_label(), 'requires_pro' => \true, 'has_active_group_plugins' => \IAWPSCOPED\iawp()->is_ecommerce_support_enabled(), 'upgrade_message' => \__('Upgrade to Independent Analytics Pro to get eCommerce stats.', 'independent-analytics'), 'upgrade_link' => 'https://independentwp.com/features/woocommerce-analytics/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Stat+Toggle+Link', 'activate_message' => \__('Activate a supported eCommerce plugin to display these stats.', 'independent-analytics'), 'activate_link' => 'https://independentwp.com/knowledgebase/woocommerce/supported-ecommerce-plugins/']), new self(['id' => 'forms', 'name' => \__('Forms', 'independent-analytics'), 'requires_pro' => \true, 'has_active_group_plugins' => \IAWPSCOPED\iawp()->is_form_submission_support_enabled(), 'upgrade_message' => self::form_group_upgrade_message(), 'upgrade_link' => 'https://independentwp.com/features/form-tracking/?utm_source=User+Dashboard&utm_medium=WP+Admin&utm_campaign=Stat+Toggle+Link', 'activate_message' => \__('Activate a supported form plugin to display these stats.', 'independent-analytics'), 'activate_link' => 'https://independentwp.com/knowledgebase/form-tracking/track-form-submissions/', 'no_tracked_data_message' => \__('Your forms will show up here once a submission has been recorded.', 'independent-analytics')])];
    }
    private static function get_ecommerce_label() : string
    {
        if (\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'WooCommerce';
        }
        if (\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'SureCart';
        }
        if (\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'Easy Digital Downloads';
        }
        if (\IAWPSCOPED\iawp()->is_pmpro_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            return 'Paid Memberships Pro';
        }
        if (\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled() && !\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && !\IAWPSCOPED\iawp()->is_surecart_support_enabled() && !\IAWPSCOPED\iawp()->is_edd_support_enabled() && !\IAWPSCOPED\iawp()->is_pmpro_support_enabled()) {
            return 'FluentCart';
        }
        return \__('eCommerce', 'independent-analytics');
    }
    private static function form_group_upgrade_message() : string
    {
        if (Form::has_active_form_plugin()) {
            return \sprintf(\_x('Upgrade to Independent Analytics Pro to track %s submissions.', 'Plugin name e.g. WPForms submissions', 'independent-analytics'), Form::get_first_active_form_plugin_name());
        }
        return \__('Upgrade to Independent Analytics Pro to track form submissions.', 'independent-analytics');
    }
}
