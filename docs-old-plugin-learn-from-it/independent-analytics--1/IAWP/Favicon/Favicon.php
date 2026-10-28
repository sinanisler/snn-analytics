<?php

namespace IAWP\Favicon;

use IAWP\Known_Referrers;
/** @internal */
class Favicon
{
    public string $domain;
    public function __construct(string $domain)
    {
        $group = Known_Referrers::get_group_for($domain);
        if (\is_array($group)) {
            $this->domain = $group['domain'];
        } else {
            $this->domain = $domain;
        }
    }
    public function file_name() : string
    {
        $sanitized = \preg_replace('/[^a-z0-9]/', '-', \strtolower($this->domain));
        if (\strlen($sanitized) > 100) {
            $sanitized = \substr($sanitized, 0, 100);
        }
        $hash = \substr(\md5($this->domain), 0, 6);
        return $sanitized . '-' . $hash . '.png';
    }
    public function exists() : bool
    {
        return $this->url() !== null;
    }
    public function url() : ?string
    {
        $path_in_plugin = 'img/favicons/' . $this->file_name();
        if (\file_exists(\IAWPSCOPED\iawp_path_to($path_in_plugin))) {
            return \IAWPSCOPED\iawp_url_to($path_in_plugin);
        }
        $path_in_uploads = 'iawp-favicons/' . $this->file_name();
        if (\file_exists(\IAWPSCOPED\iawp_upload_path_to($path_in_uploads))) {
            return \IAWPSCOPED\iawp_upload_url_to($path_in_uploads);
        }
        return null;
    }
    public static function for(string $domain) : self
    {
        return new self($domain);
    }
}
