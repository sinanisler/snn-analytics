<?php

namespace IAWP;

/** @internal */
class Capability_Manager
{
    /**
     * @return array
     */
    public static function all_capabilities() : array
    {
        return ['iawp_read_only_authored_access' => \esc_html__('View analytics for authored content', 'independent-analytics'), 'iawp_read_only_access' => \esc_html__('View all analytics', 'independent-analytics'), 'iawp_full_access' => \esc_html__('View all analytics and edit settings', 'independent-analytics')];
    }
    public static function reset_capabilities() : void
    {
        $roles = \wp_roles()->roles;
        $edits = [];
        foreach ($roles as $role => $role_details) {
            $edits[$role] = null;
        }
        self::edit_all_capabilities($edits);
    }
    /**
     * Accepts an array of key value pairs where the key is the role and the value is the capability to use
     *
     * @param $capabilities
     * @return void
     */
    public static function edit_all_capabilities($capabilities) : void
    {
        foreach ($capabilities as $role => $capability) {
            if ($role === 'administrator') {
                continue;
            }
            $user_role = \get_role($role);
            // For the role, remove all previous capabilities
            foreach (self::all_capabilities() as $possible_capability => $label) {
                $user_role->remove_cap($possible_capability);
            }
            if (\array_key_exists($capability, self::all_capabilities())) {
                $user_role->add_cap($capability);
            }
        }
    }
    public static function can_view() : bool
    {
        return self::is_admin_user() || \current_user_can('iawp_read_only_authored_access') || \current_user_can('iawp_read_only_access') || \current_user_can('iawp_full_access');
    }
    public static function can_only_view_authored_analytics() : bool
    {
        return !self::is_admin_user() && \current_user_can('iawp_read_only_authored_access');
    }
    public static function can_view_all_analytics() : bool
    {
        return self::is_admin_user() || \current_user_can('iawp_read_only_access') || \current_user_can('iawp_full_access');
    }
    public static function can_edit() : bool
    {
        return self::is_admin_user() || \current_user_can('iawp_full_access');
    }
    /**
     * Returns a capability string for admin menu pages. Admins can always see pages. Other roles
     * can only see pages if they have the special capability applied.
     *
     * @return string
     */
    public static function menu_page_capability_string() : string
    {
        if (self::is_admin_user()) {
            return 'manage_options';
        }
        if (self::can_edit()) {
            return 'iawp_full_access';
        }
        if (self::can_only_view_authored_analytics()) {
            return 'iawp_read_only_authored_access';
        }
        if (self::can_view()) {
            return 'iawp_read_only_access';
        }
        return 'manage_options';
    }
    public static function show_branded_ui() : bool
    {
        if (self::is_admin_user()) {
            return \true;
        }
        if (\get_option('iawp_white_label') === '1') {
            return \false;
        }
        return \true;
    }
    public static function show_white_labeled_ui() : bool
    {
        return !self::show_branded_ui();
    }
    /**
     * @return bool
     */
    private static function is_admin_user() : bool
    {
        if (\current_user_can('setup_network') || \in_array('administrator', \wp_get_current_user()->roles)) {
            return \true;
        }
        return \false;
    }
}
