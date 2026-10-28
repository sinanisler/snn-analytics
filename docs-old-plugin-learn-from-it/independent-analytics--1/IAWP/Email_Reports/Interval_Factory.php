<?php

namespace IAWP\Email_Reports;

/** @internal */
class Interval_Factory
{
    public static function from_option() : \IAWP\Email_Reports\Interval
    {
        $interval_id = \get_option('iawp_email_report_interval', 'monthly');
        switch ($interval_id) {
            case 'daily':
                return \IAWP\Email_Reports\Interval_Factory::daily();
            case 'weekly':
                return \IAWP\Email_Reports\Interval_Factory::weekly();
            default:
                return \IAWP\Email_Reports\Interval_Factory::monthly();
        }
    }
    public static function daily() : \IAWP\Email_Reports\Interval
    {
        return new \IAWP\Email_Reports\Interval(['id' => 'daily', 'relative_date_range_id' => 'TODAY', 'datetime_format_pattern' => 'l, M jS', 'chart_title' => \__('Hourly Views', 'independent-analytics')]);
    }
    public static function weekly() : \IAWP\Email_Reports\Interval
    {
        return new \IAWP\Email_Reports\Interval(['id' => 'weekly', 'relative_date_range_id' => 'THIS_WEEK', 'datetime_prefix' => \__('Week of', 'independent-analytics'), 'datetime_format_pattern' => 'l, M jS', 'chart_title' => \__('Daily Views', 'independent-analytics')]);
    }
    public static function monthly() : \IAWP\Email_Reports\Interval
    {
        return new \IAWP\Email_Reports\Interval(['id' => 'monthly', 'relative_date_range_id' => 'THIS_MONTH', 'datetime_format_pattern' => 'F Y', 'chart_title' => \__('Daily Views', 'independent-analytics')]);
    }
}
