<?php

namespace IAWP\Email_Reports;

use DateTime;
use IAWP\Date_Range\Date_Range;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Utils\String_Util;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Carbon;
/** @internal */
class Interval
{
    private $id;
    private $relative_date_range_id;
    private $datetime_prefix;
    private $datetime_format_pattern;
    private $chart_title;
    public function __construct(array $attributes)
    {
        $this->id = $attributes['id'];
        $this->relative_date_range_id = $attributes['relative_date_range_id'];
        $this->datetime_prefix = $attributes['datetime_prefix'] ?? '';
        $this->datetime_format_pattern = $attributes['datetime_format_pattern'];
        $this->chart_title = $attributes['chart_title'];
    }
    public function id() : string
    {
        return $this->id;
    }
    public function report_time_period_for_humans() : string
    {
        $prefix = $this->datetime_prefix;
        if (\strlen($prefix) > 0 && !String_Util::str_ends_with($prefix, ' ')) {
            $prefix .= ' ';
        }
        $start = $this->date_range()->start()->setTimezone(Timezone::site_timezone());
        $formatted_date = Carbon::parse($start)->translatedFormat($this->datetime_format_pattern);
        return $prefix . $formatted_date;
    }
    public function chart_title() : string
    {
        return $this->chart_title;
    }
    public function next_interval_start() : DateTime
    {
        $start = $this->current_date_range()->next_period()->start();
        $delivery_hour = \intval(\IAWPSCOPED\iawp()->get_option('iawp_email_report_time', 9));
        $start->setTimezone(Timezone::site_timezone());
        $start->setTime($delivery_hour, 0, 0);
        return $start;
    }
    /**
     * This is the date range that the email report covers, which is the previous period, not the
     * current period.
     *
     * @return Date_Range
     */
    public function date_range() : Date_Range
    {
        // This exception is necessary because in Date_Range, range_sizes_in_days doesn't take the
        // months length into account
        if ($this->id === 'monthly') {
            return new Relative_Date_Range('LAST_MONTH');
        }
        return $this->current_date_range()->previous_period();
    }
    private function current_date_range() : Relative_Date_Range
    {
        return new Relative_Date_Range($this->relative_date_range_id);
    }
}
