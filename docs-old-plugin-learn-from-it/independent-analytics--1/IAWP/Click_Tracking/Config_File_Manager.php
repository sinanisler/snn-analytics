<?php

namespace IAWP\Click_Tracking;

use IAWP\Utils\Request;
use IAWP\Utils\Salt;
/** @internal */
class Config_File_Manager
{
    /**
     * Recreate the click tracking config file to account for changes in pro status, visitor token
     * salt, or a custom IP address header.
     *
     * @return void
     */
    public static function recreate() : void
    {
        self::delete_file(self::config_file_path());
        self::ensure();
    }
    /**
     * Ensure the click tracking config file exists.
     *
     * @return void
     */
    public static function ensure() : void
    {
        self::remove_legacy_files();
        if (\is_file(self::config_file_path())) {
            return;
        }
        $data = ['is_pro' => \IAWPSCOPED\iawp_is_pro(), 'visitor_token_salt' => Salt::visitor_token_salt(), 'avoid_temporary_directory' => \defined('IAWP_AVOID_TEMPORARY_DIRECTORY') ? \IAWP_AVOID_TEMPORARY_DIRECTORY : \false];
        if (\is_string(Request::custom_ip_header())) {
            $data['custom_ip_header'] = Request::custom_ip_header();
        }
        $contents = "<?php exit; ?>\n";
        $contents .= \json_encode($data);
        \file_put_contents(self::config_file_path(), $contents);
    }
    /**
     * For a couple releases, click tracking files were in the uploads folder. This method is responsible for
     * cleaning them up if they are still there.
     *
     * @return void
     */
    private static function remove_legacy_files() : void
    {
        self::delete_file(\IAWPSCOPED\iawp_upload_path_to('/iawp-click-endpoint.php'));
        self::delete_file(\IAWPSCOPED\iawp_upload_path_to('/iawp-click-config.php'));
        self::delete_file(\IAWPSCOPED\iawp_upload_path_to('/iawp-click-data.php'));
    }
    private static function config_file_path() : string
    {
        return \IAWPSCOPED\iawp_path_to('/iawp-click-config.php');
    }
    private static function delete_file(string $file) : void
    {
        if (\is_file($file)) {
            \unlink($file);
        }
    }
}
