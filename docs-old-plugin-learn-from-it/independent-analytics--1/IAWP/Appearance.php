<?php

namespace IAWP;

/** @internal */
class Appearance
{
    public static function is_light()
    {
        return self::get_appearance() === 'light';
    }
    public static function is_dark()
    {
        return self::get_appearance() === 'dark';
    }
    public static function is_system()
    {
        return self::get_appearance() === 'system';
    }
    public static function get_appearance() : string
    {
        // Since iawp_appearance is a setting, the register_setting default will apply
        $current_value = \get_option('iawp_appearance');
        if (\array_key_exists($current_value, self::options())) {
            return $current_value;
        } else {
            return self::get_default_appearance();
        }
    }
    public static function options() : array
    {
        return ['light' => \__('Light', 'independent-analytics'), 'dark' => \__('Dark', 'independent-analytics'), 'system' => \__('System', 'independent-analytics')];
    }
    public static function get_default_appearance() : string
    {
        // Did the prefer dark mode in the past
        if (\get_option('iawp_dark_mode', '0') === '1') {
            return 'dark';
        }
        return 'light';
    }
}
