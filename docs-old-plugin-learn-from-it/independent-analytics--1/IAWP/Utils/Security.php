<?php

namespace IAWP\Utils;

/** @internal */
class Security
{
    public static function json_encode($object)
    {
        return \json_encode($object, \JSON_HEX_QUOT | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS);
    }
    public static function string($string)
    {
        return \trim(\sanitize_text_field($string));
    }
    public static function attr($att)
    {
        return \esc_attr($att);
    }
    public static function hex($hex)
    {
        return \sanitize_hex_color($hex);
    }
    public static function html($html)
    {
        return \wp_kses_post($html);
    }
    public static function array($array)
    {
        if (\is_string($array)) {
            return self::string($array);
        }
        if (\is_array($array)) {
            foreach ($array as $key => $value) {
                $sanitized_key = \is_string($key) ? self::string($key) : $key;
                // Sanitize key if it's a string
                $array[$sanitized_key] = self::array($value);
                // Recursively sanitize value
                if ($sanitized_key !== $key) {
                    unset($array[$key]);
                    // Remove old key if changed
                }
            }
        }
        return $array;
    }
    public static function form($html)
    {
        return \wp_kses($html, ['div' => ['class' => [], 'style' => []], 'select' => ['class' => []], 'option' => ['class' => [], 'value' => [], 'data-datatype' => []], 'input' => ['class' => [], 'type' => [], 'data-css' => [], 'data-dow' => [], 'data-format' => [], 'readonly'], 'button' => ['class' => []], 'span' => ['class' => [], 'style' => []]]);
    }
    public static function svg($html)
    {
        return \wp_kses($html, ['svg' => ['height' => [], 'width' => [], 'fill' => [], 'viewbox' => [], 'style' => []], 'path' => ['d' => []]]);
    }
}
