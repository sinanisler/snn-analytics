<?php

namespace IAWP\Ecommerce;

/** @internal */
class SureCart_Store
{
    public static function cache_currency_code() : void
    {
        if (\class_exists('\\SureCart\\Models\\Account')) {
            try {
                $account = \SureCart\Models\Account::find();
                if (!\is_wp_error($account)) {
                    \update_option('iawp_surecart_currency_code', $account->currency);
                }
            } catch (\Throwable $e) {
                \update_option('iawp_surecart_currency_code', 'usd');
            }
        }
    }
    public static function get_currency_code() : string
    {
        $currency_code = \get_option('iawp_surecart_currency_code', 'usd');
        if (!\class_exists('\\SureCart\\Support\\Currency')) {
            return 'usd';
        }
        if (!\array_key_exists($currency_code, \SureCart\Support\Currency::getSupportedCurrencies())) {
            $currency_code = 'usd';
        }
        return $currency_code;
    }
}
