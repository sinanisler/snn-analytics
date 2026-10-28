<?php

namespace IAWP\Journey;

/** @internal */
class EngagementScore
{
    public static function for_session_total_views(int $views) : int
    {
        if ($views === 1) {
            return 1;
        }
        $average_views_cutoff = self::get_int_option('iawp_average_views_cutoff', 3);
        if ($views <= $average_views_cutoff) {
            return 2;
        }
        return 3;
    }
    public static function for_session_duration(?int $duration_in_seconds) : int
    {
        $low_duration_cutoff = self::get_int_option('iawp_low_duration_cutoff', 10);
        if ($duration_in_seconds <= $low_duration_cutoff) {
            return 1;
        }
        $average_duration_cutoff = self::get_int_option('iawp_average_duration_cutoff', 60);
        if ($duration_in_seconds <= $average_duration_cutoff) {
            return 2;
        }
        return 3;
    }
    private static function get_int_option(string $option, int $default) : ?int
    {
        $value = \get_option($option, null);
        if (\is_string($value) && \ctype_digit($value)) {
            $value = \intval($value);
        }
        if (!\is_int($value)) {
            return $default;
        }
        return $value;
    }
}
