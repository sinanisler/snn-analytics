<?php

namespace IAWP;

/** @internal */
class Tables
{
    public static function views() : string
    {
        return self::prefix('views');
    }
    public static function sessions() : string
    {
        return self::prefix('sessions');
    }
    public static function clicks() : string
    {
        return self::prefix('clicks');
    }
    public static function click_targets() : string
    {
        return self::prefix('click_targets');
    }
    public static function clicked_links() : string
    {
        return self::prefix('clicked_links');
    }
    public static function link_rules() : string
    {
        return self::prefix('link_rules');
    }
    public static function links() : string
    {
        return self::prefix('links');
    }
    public static function resources() : string
    {
        return self::prefix('resources');
    }
    public static function reports() : string
    {
        return self::prefix('reports');
    }
    public static function orders() : string
    {
        return self::prefix('orders');
    }
    public static function forms() : string
    {
        return self::prefix('forms');
    }
    public static function form_submissions() : string
    {
        return self::prefix('form_submissions');
    }
    public static function countries() : string
    {
        return self::prefix('countries');
    }
    public static function cities() : string
    {
        return self::prefix('cities');
    }
    public static function device_browsers() : string
    {
        return self::prefix('device_browsers');
    }
    public static function device_types() : string
    {
        return self::prefix('device_types');
    }
    public static function device_oss() : string
    {
        return self::prefix('device_oss');
    }
    public static function campaigns() : string
    {
        return self::prefix('campaigns');
    }
    public static function landing_pages() : string
    {
        return self::prefix('landing_pages');
    }
    public static function utm_sources() : string
    {
        return self::prefix('utm_sources');
    }
    public static function utm_mediums() : string
    {
        return self::prefix('utm_mediums');
    }
    public static function utm_campaigns() : string
    {
        return self::prefix('utm_campaigns');
    }
    public static function referrers() : string
    {
        return self::prefix('referrers');
    }
    public static function referrer_types() : string
    {
        return self::prefix('referrer_types');
    }
    private static function prefix(string $name)
    {
        global $wpdb;
        return $wpdb->prefix . 'independent_analytics_' . $name;
    }
}
