<?php

namespace IAWP;

use IAWP\Click_Tracking\Link_Rule_Finder;
use IAWP\Utils\Link_Validator;
/** @internal */
class Click_Tracking
{
    public static function render_menu()
    {
        $show_click_tracking_cache_message = \get_option('iawp_click_tracking_cache_cleared', \false) === \false;
        echo \IAWPSCOPED\iawp_blade()->run('click-tracking.menu', ['active_links' => Link_Rule_Finder::active_link_rules()->map(function ($link_rule) {
            return $link_rule->to_array();
        })->all(), 'inactive_links' => Link_Rule_Finder::inactive_link_rules()->map(function ($link_rule) {
            return $link_rule->to_array();
        })->all(), 'types' => self::types(), 'extensions' => self::extensions(), 'protocols' => self::protocols(), 'error_messages' => Link_Validator::error_messages(), 'show_click_tracking_cache_message' => $show_click_tracking_cache_message]);
    }
    public static function types() : array
    {
        return ['class' => \__('Class', 'independent-analytics'), 'id' => \__('ID', 'independent-analytics'), 'extension' => \__('Extension', 'independent-analytics'), 'domain' => \__('Domain', 'independent-analytics'), 'external' => \__('External', 'independent-analytics'), 'subdirectory' => \__('Subdirectory', 'independent-analytics'), 'protocol' => \__('Protocol', 'independent-analytics')];
    }
    public static function extensions()
    {
        return ['aif', 'aifc', 'aiff', 'avi', 'csv', 'doc', 'docx', 'epub', 'exe', 'gif', 'jpeg', 'jpg', 'mov', 'mp3', 'mp4', 'm4a', 'pdf', 'png', 'ppt', 'pptx', 'psd', 'rtf', 'txt', 'wav', 'wmv', 'xls', 'xlsx', 'zip'];
    }
    public static function protocols()
    {
        return ['mailto', 'tel', 'sms'];
    }
}
