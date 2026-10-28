<?php

namespace IAWP;

use Throwable;
use ZipArchive;
/** @internal */
class Geo_Database_Manager
{
    // 🚨🚨 Updating the database? Follow the wiki: 🚨🚨
    // https://github.com/andrewjmead/independent-analytics/wiki/Update-the-Geo-Database
    private $zip_download_url = 'https://assets.independentwp.com/iawp-geo-db-8.mmdb.zip';
    private $raw_download_url = 'https://assets.independentwp.com/iawp-geo-db-8.mmdb';
    private $database_checksum = '1810ceaf24034a74a3f4e9368a52aa30';
    public function health_check() : void
    {
        if ($this->is_geo_tracking_disabled()) {
            $this->delete_database();
            return;
        }
        if ($this->is_database_valid()) {
            return;
        }
        $this->download_database();
    }
    public function download_database() : void
    {
        $this->delete_database();
        $success = $this->download_zip_database_and_extract();
        if (!$success) {
            $this->download_raw_database();
        }
    }
    public function delete_database() : void
    {
        \wp_delete_file(self::path_to_database_zip());
        \wp_delete_file(self::path_to_database());
    }
    private function is_database_valid() : bool
    {
        if (!\file_exists(self::path_to_database())) {
            return \false;
        }
        $calculated_checksum = \md5_file(self::path_to_database());
        if ($calculated_checksum !== $this->database_checksum) {
            return \false;
        }
        return \true;
    }
    private function is_geo_tracking_disabled() : bool
    {
        // Have they disabled geo tracking in wp-config.php?
        if (\defined('IAWP_DISABLE_GEO_TRACKING') && \IAWP_DISABLE_GEO_TRACKING === \true) {
            return \true;
        }
        return \false;
    }
    private function download_zip_database_and_extract() : bool
    {
        $response = \wp_remote_get($this->zip_download_url, ['stream' => \true, 'filename' => self::path_to_database_zip(), 'timeout' => 60]);
        if (\is_wp_error($response)) {
            if (\file_exists(self::path_to_database_zip())) {
                \unlink(self::path_to_database_zip());
            }
            return \false;
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open(self::path_to_database_zip()) === \true) {
                $zip->extractTo(\IAWPSCOPED\iawp_upload_path_to('', \true));
                $zip->close();
            }
        } catch (Throwable $e) {
            // It's ok to fail
        }
        \wp_delete_file(self::path_to_database_zip());
        return $this->is_database_valid();
    }
    private function download_raw_database() : bool
    {
        $response = \wp_remote_get($this->raw_download_url, ['stream' => \true, 'filename' => self::path_to_database(), 'timeout' => 60]);
        if (\is_wp_error($response)) {
            if (\file_exists(self::path_to_database())) {
                \unlink(self::path_to_database());
            }
            return \false;
        }
        return $this->is_database_valid();
    }
    public static function path_to_database() : string
    {
        return \IAWPSCOPED\iawp_upload_path_to('iawp-geo-db.mmdb', \true);
    }
    private static function path_to_database_zip() : string
    {
        return \IAWPSCOPED\iawp_upload_path_to('iawp-geo-db.zip', \true);
    }
}
