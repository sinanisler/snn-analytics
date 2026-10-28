<?php

namespace IAWP\Utils;

use IAWPSCOPED\Illuminate\Support\Str;
use IAWPSCOPED\League\Uri\Contracts\UriException;
use IAWPSCOPED\League\Uri\Uri;
/** @internal */
class URL
{
    private $url;
    public function __construct(string $url)
    {
        $this->url = $url;
    }
    public function is_valid_url() : bool
    {
        $valid_url = \filter_var($this->url, \FILTER_VALIDATE_URL);
        if (!$valid_url) {
            return \false;
        }
        try {
            // Recommend approach for uri validation: https://uri.thephpleague.com/uri/6.0/rfc3986/#uri-validation
            $components = Uri::createFromString($this->url);
            if (\is_null($components->getHost())) {
                return \false;
            }
            return \true;
        } catch (UriException $e) {
            return \false;
        }
    }
    public function get_url() : ?string
    {
        if (!$this->is_valid_url()) {
            return null;
        }
        return $this->url;
    }
    public function get_domain() : ?string
    {
        if (!$this->is_valid_url()) {
            return null;
        }
        return Uri::createFromString($this->url)->getHost();
    }
    public function get_extension() : ?string
    {
        if (!$this->is_valid_url()) {
            return null;
        }
        $path = Uri::createFromString($this->url)->getPath();
        $file = Str::afterLast($path, '/');
        $extension = Str::afterLast($file, '.');
        return $extension !== "" ? $extension : null;
    }
    public function get_path() : ?string
    {
        if (!$this->is_valid_url()) {
            return null;
        }
        return Uri::createFromString($this->url)->getPath();
    }
    public static function new(string $url) : self
    {
        return new self($url);
    }
}
