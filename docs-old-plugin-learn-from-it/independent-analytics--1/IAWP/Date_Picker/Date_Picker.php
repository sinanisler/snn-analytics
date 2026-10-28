<?php

namespace IAWP\Date_Picker;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Utils\Timezone;
/** @internal */
class Date_Picker
{
    private $start;
    private $end;
    private $relative_range;
    private $first_data;
    public function __construct($start, $end, $relative_range)
    {
        $this->start = $start;
        $this->end = $end;
        $this->relative_range = $relative_range;
        $this->first_data = Relative_Date_Range::beginning_of_time();
    }
    public function calendar_html()
    {
        return \IAWPSCOPED\iawp_blade()->run('date-picker.date-picker', ['months' => $this->months(), 'start_date' => $this->start, 'end_date' => $this->end, 'relative_range' => $this->relative_range, 'date_ranges' => Relative_Date_Range::ranges(), 'timezone' => Timezone::site_timezone(), 'user_format' => \get_option('date_format'), 'first_data' => $this->first_data->format('Y-m-d'), 'site_offset_in_seconds' => Timezone::site_offset_in_seconds($this->start)]);
    }
    private function months() : array
    {
        $first_day_last_year = new \DateTime('first day of January last year', Timezone::site_timezone());
        $start = $this->first_data < $first_day_last_year ? clone $this->first_data : clone $first_day_last_year;
        $start->modify('first day of this month')->setTime(0, 0, 0);
        $end = (new \DateTime('last day of this month', Timezone::site_timezone()))->setTime(23, 59, 59);
        $interval = new \DateInterval('P1M');
        // 1 month interval
        $period = new \DatePeriod($start, $interval, $end);
        $months = [];
        foreach ($period as $month) {
            $months[] = new \IAWP\Date_Picker\Month($month, $start);
        }
        return $months;
    }
}
