<?php

namespace IAWP\Click_Tracking;

use IAWP\Cron_Job;
use IAWP\Models\Visitor;
use IAWP\Payload_Validator;
use IAWP\Utils\Security;
use IAWPSCOPED\Illuminate\Support\Str;
use IAWPSCOPED\League\Uri\Uri;
/** @internal */
class Click_Processing_Job extends Cron_Job
{
    protected $name = 'iawp_click_processing';
    protected $interval = 'every_minute';
    public function handle() : void
    {
        // Periodically recreate the config file
        \IAWP\Click_Tracking\Config_File_Manager::recreate();
        if (\IAWPSCOPED\iawp_is_free()) {
            self::unschedule();
            return;
        }
        $click_data_file = $this->get_click_data_file();
        if ($click_data_file === null) {
            return;
        }
        $job_file = $this->create_job_file($click_data_file);
        if ($job_file === null) {
            return;
        }
        $job_handle = \fopen($job_file, 'r');
        if ($job_handle === \false) {
            return;
        }
        // The first line for the PHP file is an exit statement to keep the contents private. This
        // should be skipped when parsing the file.
        if (\pathinfo($job_file, \PATHINFO_EXTENSION) === 'php') {
            \fgets($job_handle);
            // Skip first line
        }
        while (($json = \fgets($job_handle)) !== \false) {
            $event = \json_decode($json, \true);
            if (\is_null($event)) {
                continue;
            }
            $payload_validator = Payload_Validator::new($event['payload'], $event['signature']);
            if (!$payload_validator->is_valid() || \is_null($payload_validator->resource())) {
                continue;
            }
            if (\is_string($event['href']) && !$this->is_valid_href($event['href'])) {
                continue;
            }
            $event['href'] = \sanitize_url($event['href']);
            $event['classes'] = Security::string($event['classes']);
            $event['ids'] = Security::string($event['ids']);
            $click = \IAWP\Click_Tracking\Click::new(['href' => $event['href'], 'classes' => $event['classes'], 'ids' => $event['ids'], 'resource_id' => $payload_validator->resource()['id'], 'visitor_id' => Visitor::fetch_visitor_id_by_hash($event['visitor_token']), 'created_at' => \DateTime::createFromFormat('U', $event['created_at'])]);
            $click->track();
        }
        \fclose($job_handle);
        \unlink($job_file);
    }
    private function get_click_data_file() : ?string
    {
        $avoid_temporary_directory = \defined('IAWP_AVOID_TEMPORARY_DIRECTORY') ? \IAWP_AVOID_TEMPORARY_DIRECTORY === \true : \false;
        if (!$avoid_temporary_directory) {
            $text_file = Str::finish(\sys_get_temp_dir(), \DIRECTORY_SEPARATOR) . "iawp-click-data.txt";
            if (\is_file($text_file) && \is_readable($text_file) && \is_writable($text_file)) {
                return $text_file;
            }
        }
        $php_file = \IAWPSCOPED\iawp_path_to('iawp-click-data.php');
        if (\is_file($php_file)) {
            return $php_file;
        }
        return null;
    }
    private function create_job_file(string $file) : ?string
    {
        if (!\is_readable($file) || !\is_writable($file)) {
            return null;
        }
        $job_id = \rand();
        $extension = \pathinfo($file, \PATHINFO_EXTENSION);
        $job_file = Str::finish(\dirname($file), \DIRECTORY_SEPARATOR) . "iawp-click-data-{$job_id}.{$extension}";
        $was_renamed = \rename($file, $job_file);
        if (!$was_renamed || !\is_file($job_file)) {
            return null;
        }
        return $job_file;
    }
    private function is_valid_href(string $href) : bool
    {
        if ($this->has_injection_attempt($href)) {
            return \false;
        }
        $uri = Uri::createFromString($href);
        $scheme = $uri->getScheme();
        if ($scheme === null) {
            return \false;
        }
        $is_http = $scheme === 'http' || $scheme === 'https';
        if ($is_http && \filter_var($href, \FILTER_VALIDATE_URL) === \false) {
            return \false;
        }
        return \true;
    }
    /**
     * The goal of this method is not security. That's handled by the secure and robust functions
     * that PHP and WordPress provide. The goal of this function is to detect when someone tried
     * to use SQL injection so we can ignore the spam clicks.
     *
     * @param string $string
     *
     * @return bool
     */
    private function has_injection_attempt(string $string) : bool
    {
        $patterns = ['/select\\s.*\\sfrom/i', '/waitfor\\sdelay/i', '/pg_sleep/i'];
        foreach ($patterns as $pattern) {
            if (\preg_match($pattern, $string)) {
                return \true;
            }
        }
        return \false;
    }
}
