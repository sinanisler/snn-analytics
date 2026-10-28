<?php

namespace IAWP\Integrations;

use Error;
use IAWPSCOPED\Illuminate\Support\Collection;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Integration
{
    private array $attributes;
    public function __construct(array $attributes)
    {
        // An integration can only belong to a plugin or a theme, not both.
        if (isset($attributes['plugin']) && isset($attributes['theme'])) {
            throw new Error('An integration cannot define both "plugin" and "theme"');
        }
        $this->attributes = $attributes;
    }
    public function name() : string
    {
        return $this->attributes['name'];
    }
    public function description() : string
    {
        return $this->attributes['description'];
    }
    public function feature_page_url()
    {
        if ($this->is_ecommerce_plugin()) {
            return 'https://independentwp.com/features/woocommerce-analytics/';
        } elseif ($this->is_form_plugin()) {
            return 'https://independentwp.com/features/form-tracking/';
        }
    }
    public function label()
    {
        if ($this->is_ecommerce_plugin()) {
            return 'eCommerce';
        } elseif ($this->is_form_plugin()) {
            return 'Form';
        }
    }
    public function icon() : string
    {
        if (\array_key_exists('filename', $this->attributes)) {
            return \IAWPSCOPED\iawp_icon($this->attributes['filename']);
        }
        $file = $this->name();
        $file = Str::replace(' ', '_', $file);
        $file = Str::lower($file);
        return \IAWPSCOPED\iawp_icon($file);
    }
    public function activated() : bool
    {
        return $this->activated_plugin() || $this->activated_theme();
    }
    public function is_ecommerce_plugin() : bool
    {
        return $this->attributes['category'] === 'ecommerce';
    }
    public function is_form_plugin() : bool
    {
        return $this->attributes['category'] === 'form';
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_blade()->run('integrations.integration', ['integration' => $this]);
    }
    private function activated_plugin() : bool
    {
        if (!isset($this->attributes['plugin'])) {
            return \false;
        }
        $slugs = $this->attributes['plugin'];
        if (\is_string($slugs)) {
            $slugs = [$slugs];
        }
        return Collection::make($slugs)->contains(function ($slug) {
            return \is_plugin_active($slug);
        });
    }
    private function activated_theme() : bool
    {
        if (!isset($this->attributes['theme'])) {
            return \false;
        }
        $themes = $this->attributes['theme'];
        if (\is_string($themes)) {
            $themes = [$themes];
        }
        return Collection::make($themes)->contains(function ($slug) {
            return \strtolower(\get_template()) === \strtolower($slug);
        });
    }
}
