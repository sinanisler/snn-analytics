<?php

namespace IAWP\Integrations;

use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Integrations
{
    private ?Collection $cached_integrations = null;
    /**
     * @return Integration[]
     */
    public function ecommerce_integrations() : array
    {
        return $this->integrations(function (\IAWP\Integrations\Integration $integration) {
            return $integration->is_ecommerce_plugin();
        })->sortBy(function (\IAWP\Integrations\Integration $integration) {
            return !$integration->activated();
        })->all();
    }
    public function is_using_ecommerce_plugin() : bool
    {
        return Collection::make($this->ecommerce_integrations())->contains(function (\IAWP\Integrations\Integration $integration) {
            return $integration->activated();
        });
    }
    public function active_ecommerce_plugin() : ?\IAWP\Integrations\Integration
    {
        if (!$this->is_using_ecommerce_plugin()) {
            return null;
        }
        $ecommerce_plugins = $this->ecommerce_integrations();
        return $ecommerce_plugins[\array_key_first($ecommerce_plugins)];
    }
    /**
     * @return Integration[]
     */
    public function form_integrations() : array
    {
        return $this->integrations(function (\IAWP\Integrations\Integration $integration) {
            return $integration->is_form_plugin();
        })->sortBy(function (\IAWP\Integrations\Integration $integration) {
            return !$integration->activated();
        })->all();
    }
    public function is_using_form_plugin() : bool
    {
        return Collection::make($this->form_integrations())->contains(function (\IAWP\Integrations\Integration $integration) {
            return $integration->activated();
        });
    }
    public function active_form_plugin() : ?\IAWP\Integrations\Integration
    {
        if (!$this->is_using_form_plugin()) {
            return null;
        }
        $form_plugins = $this->form_integrations();
        return $form_plugins[\array_key_first($form_plugins)];
    }
    private function integrations(?callable $where) : Collection
    {
        if (\is_null($this->cached_integrations)) {
            $this->cached_integrations = $this->config()->map(function ($config) {
                return new \IAWP\Integrations\Integration($config);
            });
        }
        return $this->cached_integrations->when(\is_callable($where), function (Collection $collection) use($where) {
            return $collection->filter(function (\IAWP\Integrations\Integration $integration) use($where) {
                return $where($integration);
            })->values();
        });
    }
    private function config() : Collection
    {
        // Template
        // [
        //     'name'        => 'WooCommerce', // Any string
        //     'category'    => 'ecommerce',   // 'ecommerce' or 'form'
        //     'plugin'      => ['plugin/plugin.php'], // A string or an array of strings
        //     // 'theme'    => ['theme-slug'], // A string or an array of strings
        //     'description' => 'A text blurb'
        // ],
        return Collection::make([
            // Example
            // [
            //     'name'        => 'WooCommerce',
            //     'category'    => 'ecommerce',
            //     'plugin'      => ['woocommerce/woocommerce.php'],
            //     'description' => 'Some text blurb...',
            // ],
            // eCommerce
            ['name' => 'WooCommerce', 'category' => 'ecommerce', 'plugin' => ['woocommerce/woocommerce.php']],
            ['name' => 'FluentCart', 'category' => 'ecommerce', 'plugin' => ['fluent-cart/fluent-cart.php', 'fluent-cart-pro/fluent-cart-pro.php']],
            ['name' => 'SureCart', 'category' => 'ecommerce', 'plugin' => ['surecart/surecart.php']],
            ['name' => 'Easy Digital Downloads', 'category' => 'ecommerce', 'plugin' => ['easy-digital-downloads/easy-digital-downloads.php', 'easy-digital-downloads-pro/easy-digital-downloads.php'], 'filename' => 'edd'],
            ['name' => 'Paid Memberships Pro', 'category' => 'ecommerce', 'plugin' => ['paid-memberships-pro/paid-memberships-pro.php', 'paid-memberships-pro-dev/paid-memberships-pro.php'], 'filename' => 'pmpro'],
            // Forms
            ['name' => 'Contact Form 7', 'category' => 'form', 'plugin' => ['contact-form-7/wp-contact-form-7.php']],
            ['name' => 'WPForms', 'category' => 'form', 'plugin' => ['wpforms-lite/wpforms.php', 'wpforms/wpforms.php']],
            ['name' => 'Gravity Forms', 'category' => 'form', 'plugin' => ['gravityforms/gravityforms.php']],
            ['name' => 'Fluent Forms', 'category' => 'form', 'plugin' => ['fluentform/fluentform.php']],
            ['name' => 'MailChimp for WordPress', 'category' => 'form', 'plugin' => ['mailchimp-for-wp/mailchimp-for-wp.php']],
            ['name' => 'MailPoet', 'category' => 'form', 'plugin' => ['mailpoet/mailpoet.php']],
            ['name' => 'Ninja Forms', 'category' => 'form', 'plugin' => ['ninja-forms/ninja-forms.php']],
            ['name' => 'Bit Form', 'category' => 'form', 'plugin' => ['bit-form/bitforms.php']],
            ['name' => 'Kadence', 'category' => 'form', 'plugin' => ['kadence-blocks/kadence-blocks.php']],
            ['name' => 'Newsletter', 'category' => 'form', 'plugin' => ['newsletter/plugin.php']],
            ['name' => 'Formidable Forms', 'category' => 'form', 'plugin' => ['formidable/formidable.php']],
            ['name' => 'Bricks Builder', 'category' => 'form', 'theme' => 'bricks'],
            ['name' => 'Divi', 'category' => 'form', 'theme' => 'Divi'],
            ['name' => 'Elementor Pro', 'category' => 'form', 'plugin' => ['elementor-pro/elementor-pro.php']],
            ['name' => 'Avada', 'category' => 'form', 'plugin' => ['fusion-builder/fusion-builder.php', 'fusion-core/fusion-core.php']],
            ['name' => 'MailOptin', 'category' => 'form', 'plugin' => ['mailoptin/mailoptin.php']],
            ['name' => 'SureForms', 'category' => 'form', 'plugin' => ['sureforms/sureforms.php']],
            ['name' => 'Forminator', 'category' => 'form', 'plugin' => ['forminator/forminator.php']],
            ['name' => 'WS Form', 'category' => 'form', 'plugin' => ['ws-form/ws-form.php', 'ws-form-pro/ws-form.php']],
            ['name' => 'Convert Pro', 'category' => 'form', 'plugin' => ['convertpro/convertpro.php']],
            ['name' => 'Everest Forms', 'category' => 'form', 'plugin' => ['everest-forms/everest-forms.php']],
            ['name' => 'Hustle', 'category' => 'form', 'plugin' => ['wordpress-popup/popover.php', 'hustle/opt-in.php']],
            ['name' => 'JetFormBuilder', 'category' => 'form', 'plugin' => ['jetformbuilder/jet-form-builder.php']],
            ['name' => 'Kali Forms', 'category' => 'form', 'plugin' => ['kali-forms/kali-forms.php']],
            ['name' => 'Thrive Leads', 'category' => 'form', 'plugin' => ['thrive-leads/thrive-leads.php']],
            ['name' => 'Amelia', 'category' => 'form', 'plugin' => ['ameliabooking/ameliabooking.php']],
            ['name' => 'ARForms', 'category' => 'form', 'plugin' => ['arforms-form-builder/arforms-form-builder.php']],
            ['name' => 'WP Store Locator', 'category' => 'form', 'plugin' => ['wp-store-locator/wp-store-locator.php']],
        ]);
    }
}
