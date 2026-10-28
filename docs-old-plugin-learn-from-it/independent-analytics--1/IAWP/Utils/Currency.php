<?php

namespace IAWP\Utils;

use IAWP\Ecommerce\SureCart_Store;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Currency
{
    /**
     * The value for $amount_in_cents is just that, an amount in cents. It's possible that is a float, which is why
     * there's no type defined for $amount_in_cents on the function definition. This supports "500.0" for $5.
     *
     * @param int|float $amount_in_cents
     * @param bool $round_to_whole_dollars
     *
     * @return string
     */
    public static function format($amount_in_cents, bool $round_to_whole_dollars = \true) : string
    {
        $amount_in_cents = \intval($amount_in_cents);
        if ($round_to_whole_dollars) {
            $amount_in_cents = \round($amount_in_cents, -2);
        }
        if (\function_exists('wc_price')) {
            if ($round_to_whole_dollars) {
                $options = ['decimals' => 0];
            } else {
                $options = null;
            }
            // The format function accepts amounts in cents, but wc_price requires them to be floats
            // representing an amount in dollars. This is why the amount is divided by 100 below.
            $formatted_value = \strip_tags(\wc_price($amount_in_cents / 100, $options));
            // wc_price returns HTML. Since there's no function that returns a string, the encoded
            // entities need to be decoded.
            $decoded_value = \html_entity_decode($formatted_value, \ENT_NOQUOTES, 'UTF-8');
            return $decoded_value;
        }
        if (\class_exists('\\SureCart\\Support\\Currency')) {
            $currency_code = SureCart_Store::get_currency_code();
            $formatted_value = \SureCart\Support\Currency::format($amount_in_cents, $currency_code);
            return $round_to_whole_dollars ? Str::before($formatted_value, ".") : $formatted_value;
        }
        if (\function_exists('edd_currency_filter') && \function_exists('edd_format_amount')) {
            // The format function accepts amounts in cents, but edd_format_amount requires them to be floats
            // representing an amount in dollars. This is why the amount is divided by 100 below.
            $formatted_value = \edd_currency_filter(\edd_format_amount($amount_in_cents / 100));
            $rounded_value = $round_to_whole_dollars ? Str::before($formatted_value, ".") : $formatted_value;
            // edd_currency_filter returns HTML. Since there's no function that returns a string, the encoded
            // entities need to be decoded.
            $decoded_value = \html_entity_decode($rounded_value, \ENT_NOQUOTES, 'UTF-8');
            return $decoded_value;
        }
        if (\function_exists('pmpro_formatPrice')) {
            // The format function accepts amounts in cents, but edd_format_amount requires them to be floats
            // representing an amount in dollars. This is why the amount is divided by 100 below.
            $formatted_value = \pmpro_formatPrice($amount_in_cents / 100);
            $decoded_value = \html_entity_decode($formatted_value, \ENT_NOQUOTES, 'UTF-8');
            return $round_to_whole_dollars ? Str::before($decoded_value, ".") : $decoded_value;
        }
        if (\IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            $show_decimals = !$round_to_whole_dollars;
            $formatted_value = \FluentCart\App\Helpers\Helper::toDecimal($amount_in_cents, \true, null, $show_decimals);
            $decoded_value = \html_entity_decode($formatted_value, \ENT_NOQUOTES, 'UTF-8');
            return $decoded_value;
        }
        // Fallback
        return \strval(\intval($amount_in_cents / 100));
    }
}
