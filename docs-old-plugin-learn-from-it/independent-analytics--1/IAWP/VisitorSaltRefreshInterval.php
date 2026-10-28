<?php

namespace IAWP;

/** @internal */
class VisitorSaltRefreshInterval
{
    public static function options() : array
    {
        return ['never' => \__('Never', 'independent-analytics'), 'daily' => \__('Daily', 'independent-analytics'), 'weekly' => \__('Weekly', 'independent-analytics'), 'monthly' => \__('Monthly', 'independent-analytics')];
    }
    public static function interval() : string
    {
        // Since iawp_visitor_salt_refresh_interval is a setting, the register_setting default will apply
        $saved_interval = \get_option('iawp_visitor_salt_refresh_interval', \false);
        if (\is_string($saved_interval) && \array_key_exists($saved_interval, self::options())) {
            return $saved_interval;
        }
        return self::default_interval();
    }
    public static function default_interval() : string
    {
        // Did they have salt refresh inabled with the old option
        if (\get_option('iawp_refresh_salt', '0') == '1') {
            return 'daily';
        }
        return 'never';
    }
}
