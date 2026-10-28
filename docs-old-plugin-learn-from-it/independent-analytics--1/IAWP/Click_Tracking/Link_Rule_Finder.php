<?php

namespace IAWP\Click_Tracking;

use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\URL;
use IAWPSCOPED\Illuminate\Support\Collection;
use IAWPSCOPED\Illuminate\Support\Str;
// TODO - This for more link a matcher and not a finder...
/** @internal */
class Link_Rule_Finder
{
    private $protocol;
    private $href;
    private $classes;
    private $ids;
    private static $database_records = null;
    public function __construct(?string $protocol, ?string $href, string $classes, string $ids)
    {
        $this->protocol = $protocol;
        $this->href = $href;
        $this->classes = $classes;
        $this->ids = $ids;
    }
    public function links() : ?Collection
    {
        return self::get_database_records()->filter(function ($link_rule) {
            return $link_rule->is_active();
        })->filter(function ($link_rule) {
            return $this->is_match($link_rule);
        })->values();
    }
    private function is_match($link_rule) : bool
    {
        switch ($link_rule->type()) {
            case 'class':
                return $this->is_matching_class($link_rule);
            case 'id':
                return $this->is_matching_id($link_rule);
            case 'domain':
                return $this->is_matching_domain($link_rule);
            case 'extension':
                return $this->is_matching_extension($link_rule);
            case 'subdirectory':
                return $this->is_matching_subdirectory($link_rule);
            case 'protocol':
                return $this->is_matching_protocol($link_rule);
            case 'external':
                return $this->is_matching_external($link_rule);
            default:
                return \false;
        }
    }
    private function is_matching_class($link_rule) : bool
    {
        if ($this->classes === "") {
            return \false;
        }
        return Collection::make(\explode(' ', $this->classes))->contains(function ($value, $key) use($link_rule) {
            return $link_rule->value() === $value;
        });
    }
    private function is_matching_id($link_rule) : bool
    {
        if ($this->ids === "") {
            return \false;
        }
        return Collection::make(\explode(' ', $this->ids))->contains(function ($value, $key) use($link_rule) {
            return $link_rule->value() === $value;
        });
    }
    private function is_matching_domain($link_rule) : bool
    {
        if (\is_null($this->href)) {
            return \false;
        }
        $url = new URL($this->href);
        if (!$url->is_valid_url()) {
            return \false;
        }
        $domain = $url->get_domain();
        $domains = [$domain];
        if (Str::startsWith($domain, 'www.')) {
            $domains[] = Str::after($domain, 'www.');
        } else {
            $domains[] = 'www.' . $domain;
        }
        return \in_array($link_rule->value(), $domains);
    }
    private function is_matching_extension($link_rule) : bool
    {
        if (\is_null($this->href)) {
            return \false;
        }
        $url = new URL($this->href);
        if (!$url->is_valid_url()) {
            return \false;
        }
        return $link_rule->value() === $url->get_extension();
    }
    private function is_matching_subdirectory($link_rule) : bool
    {
        if (\is_null($this->href)) {
            return \false;
        }
        $url = URL::new($this->href);
        $site_url = URL::new(\get_site_url());
        if (!$url->is_valid_url() || $url->get_domain() !== $site_url->get_domain()) {
            return \false;
        }
        $path = $url->get_path();
        $path_parts = Collection::make(\explode('/', $path))->filter()->values();
        if ($path_parts->isEmpty()) {
            return \false;
        }
        return $link_rule->value() === $path_parts->first();
    }
    private function is_matching_protocol($link_rule) : bool
    {
        if (\is_null($this->href)) {
            return \false;
        }
        return $this->protocol === $link_rule->value();
    }
    private function is_matching_external($link_rule) : bool
    {
        if (\is_null($this->href)) {
            return \false;
        }
        $site_url = URL::new(\get_site_url());
        $link_url = URL::new($this->href);
        // Only track valid http/https URLs and not other protocols like mailto:, tel:, etc
        if (!$link_url->is_valid_url()) {
            return \false;
        }
        return $link_url->get_domain() !== $site_url->get_domain();
    }
    public static function new(?string $protocol, ?string $href, string $classes, ?string $id) : self
    {
        return new self($protocol, $href, $classes, $id);
    }
    public static function link_rules() : Collection
    {
        return self::get_database_records();
    }
    public static function active_link_rules() : Collection
    {
        return self::get_database_records()->filter(function ($link_rule) {
            return $link_rule->is_active();
        })->values();
    }
    public static function inactive_link_rules() : Collection
    {
        return self::get_database_records()->filter(function ($link_rule) {
            return !$link_rule->is_active();
        })->values();
    }
    public static function cached_link_rules() : array
    {
        $cached_rules = \get_option('iawp_link_rules', \false);
        if (!\is_array($cached_rules)) {
            $link_rules = \IAWP\Click_Tracking\Link_Rule_Finder::active_link_rules()->map(function (\IAWP\Click_Tracking\Link_Rule $link_rule) {
                return ['type' => $link_rule->type(), 'value' => $link_rule->value()];
            });
            \update_option('iawp_link_rules', $link_rules->all());
        }
        return \get_option('iawp_link_rules');
    }
    public static function require_cleared_cache()
    {
        \delete_option('iawp_click_tracking_cache_cleared');
        \delete_option('iawp_link_rules');
    }
    private static function get_database_records() : Collection
    {
        if (\is_null(static::$database_records)) {
            $records = Illuminate_Builder::new()->from(Tables::link_rules())->orderBy('position')->orderByDesc('created_at')->get();
            static::$database_records = $records->map(function ($link_rule) {
                return new \IAWP\Click_Tracking\Link_Rule($link_rule);
            });
        }
        return static::$database_records;
    }
}
