<?php

namespace IAWP\Utils;

use IAWP\Click_Tracking;
use IAWPSCOPED\Illuminate\Support\Str;
use IAWPSCOPED\League\Uri\Uri;
/** @internal */
class Link_Validator
{
    public static function error_messages()
    {
        return ['empty-name' => \__('Name cannot be empty.', 'independent-analytics'), 'empty-value' => \__('Value cannot be empty.', 'independent-analytics'), 'invalid-type' => \__('Invalid type.', 'independent-analytics'), 'class-number' => \__('Class cannot start with a number or special character.', 'independent-analytics'), 'class-space' => \__('Class cannot contain spaces.', 'independent-analytics'), 'class-characters' => \__('Class cannot contain special characters besides "-" and "_".', 'independent-analytics'), 'id-number' => \__('ID cannot start with a number or special character.', 'independent-analytics'), 'id-space' => \__('ID cannot contain spaces.', 'independent-analytics'), 'id-characters' => \__('ID cannot contain special characters besides "-" and "_".', 'independent-analytics'), 'invalid-extension' => \__('Invalid Extension.', 'independent-analytics'), 'invalid-protocol' => \__('Invalid Protocol.', 'independent-analytics'), 'invalid-domain' => \__('Invalid domain.', 'independent-analytics'), 'invalid-subdirectory' => \__('Invalid characters in subdirectory.', 'independent-analytics')];
    }
    public static function validate(string $property, string $value, string $type)
    {
        if ($property == 'name') {
            if (\trim($value) == '') {
                return 'empty-name';
            }
        } elseif ($property == 'type') {
            if (!\array_key_exists($value, Click_Tracking::types())) {
                return 'invalid-type';
            }
        } elseif ($property == 'value') {
            if ($type === 'external') {
                // There is no value for the external type
                return \false;
            }
            if (\trim($value) == '') {
                return 'empty-value';
            }
            if ($type == 'class') {
                if (!\preg_match('/[a-zA-Z]/', \substr($value, 0, 1))) {
                    return 'class-number';
                } elseif (\preg_match('/\\s/', $value)) {
                    return 'class-space';
                } elseif (\preg_match('/[^a-zA-Z0-9_-]/', $value)) {
                    return 'class-characters';
                }
            } elseif ($type == 'id') {
                if (!\preg_match('/[a-zA-Z]/', \substr($value, 0, 1))) {
                    return 'id-number';
                } elseif (\preg_match('/\\s/', $value)) {
                    return 'id-space';
                } elseif (\preg_match('/[^a-zA-Z0-9_-]/', $value)) {
                    return 'id-characters';
                }
            } elseif ($type == 'extension') {
                if (!\in_array($value, Click_Tracking::extensions())) {
                    return 'invalid-extension';
                }
            } elseif ($type == 'protocol') {
                if (!\in_array($value, Click_Tracking::protocols())) {
                    return 'invalid-protocol';
                }
            } elseif ($type == 'domain') {
                if (!self::is_valid_domain($value)) {
                    return 'invalid-domain';
                }
            } elseif ($type == 'subdirectory') {
                if (\preg_match("/[^A-Za-z0-9-._~!\$&'()*+,;=:@\\/]/", $value)) {
                    return 'invalid-subdirectory';
                }
            }
        }
        return \false;
    }
    public static function is_valid_domain(string $domain)
    {
        return \preg_match('/^(http[s]?\\:\\/\\/)?((\\w+)\\.)?(([\\w-]+)+)(\\.[\\w-]+){1,2}$/', $domain);
    }
    public static function sanitize_domain($domain)
    {
        $domain = \strpos($domain, 'http') !== 0 ? "http://{$domain}" : $domain;
        $components = Uri::createFromString($domain);
        $host = $components->getHost();
        if (Str::startsWith($host, 'www.')) {
            $host = Str::after($host, 'www.');
        }
        return $host;
    }
    public static function sanitize_subdirectory($subdirectory)
    {
        return \sanitize_text_field(\trim($subdirectory, '/'));
    }
}
