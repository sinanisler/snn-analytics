<?php

namespace IAWP\Date_Range;

use DateTime;
/** @internal */
class Exact_Date_Range extends \IAWP\Date_Range\Date_Range
{
    /**
     * Generate a range using exact start and end dates
     *
     * @param DateTime $start
     * @param DateTime $end
     * @param bool $convert_to_full_days
     */
    public function __construct(DateTime $start, DateTime $end, bool $convert_to_full_days = \true)
    {
        $this->set_range($start, $end, $convert_to_full_days);
    }
    /**
     * Get a formatted label for the range
     *
     * @return string
     */
    public function label() : string
    {
        $formatted_start = \IAWPSCOPED\iawp()->date_i18n(\get_option('date_format'), $this->start);
        $formatted_end = \IAWPSCOPED\iawp()->date_i18n(\get_option('date_format'), $this->end);
        return $formatted_start . ' - ' . $formatted_end;
    }
    /**
     * Get a range that covers the entirety of the plugins lifetime
     *
     * @return Exact_Date_Range
     */
    public static function comprehensive_range() : \IAWP\Date_Range\Exact_Date_Range
    {
        return new \IAWP\Date_Range\Exact_Date_Range(new DateTime('1991-01-06'), new DateTime());
    }
}
