<?php

namespace IAWP\Utils;

/** @internal */
class Calculations
{
    public static function divide(float $numerator, float $denominator, int $precision = 0)
    {
        if ($denominator === 0.0 && $numerator > 0) {
            return 100;
        } elseif ($denominator === 0.0) {
            return 0;
        }
        return \round($numerator / $denominator, $precision);
    }
    public static function percentage(float $numerator, float $denominator, int $precision = 0)
    {
        if ($denominator === 0.0 && $numerator > 0) {
            return 100;
        } elseif ($denominator === 0.0) {
            return 0;
        }
        return \round($numerator / $denominator * 100, $precision);
    }
}
