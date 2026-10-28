<?php

namespace IAWP\Form_Submissions;

use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Newsletter
{
    public static function get_form(?string $referrer) : ?array
    {
        if (\is_null($referrer)) {
            return null;
        }
        return Collection::make(self::get_forms())->first(fn($form) => $form['referrer'] === $referrer);
    }
    private static function get_forms() : array
    {
        return [['id' => 1, 'title' => \__('Standard Form', 'independent-analytics'), 'referrer' => ''], ['id' => 2, 'title' => \__('After Post Content', 'independent-analytics'), 'referrer' => 'posts_bottom'], ['id' => 3, 'title' => \__('Popup', 'independent-analytics'), 'referrer' => 'popup'], ['id' => 4, 'title' => \__('Minimal Form', 'independent-analytics'), 'referrer' => 'minimal']];
    }
}
