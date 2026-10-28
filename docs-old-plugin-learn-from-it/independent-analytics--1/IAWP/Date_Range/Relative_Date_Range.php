<?php

namespace IAWP\Date_Range;

use DateTime;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Relative_Date_Range extends \IAWP\Date_Range\Date_Range
{
    private const VALID_RELATIVE_RANGE_IDS = ['TODAY', 'YESTERDAY', 'THIS_WEEK', 'LAST_WEEK', 'LAST_SEVEN', 'LAST_THIRTY', 'LAST_SIXTY', 'LAST_NINETY', 'THIS_MONTH', 'LAST_MONTH', 'LAST_THREE_MONTHS', 'LAST_SIX_MONTHS', 'LAST_TWELVE_MONTHS', 'THIS_YEAR', 'LAST_YEAR', 'ALL_TIME'];
    /**
     * @var string
     */
    private $label;
    /**
     * @var string
     */
    private $relative_range_id = 'LAST_THIRTY';
    /**
     * @param string $relative_range_id A valid range id such as LAST_MONTH
     * @param bool $convert_to_full_days
     */
    public function __construct(string $relative_range_id, bool $convert_to_full_days = \true)
    {
        if (\in_array($relative_range_id, self::VALID_RELATIVE_RANGE_IDS)) {
            $this->relative_range_id = $relative_range_id;
        }
        // Call the method whose name matches the relative range id
        $method_name = \strtolower($relative_range_id);
        list($start, $end, $label) = $this->{$method_name}();
        $this->set_range($start, $end, $convert_to_full_days);
        $this->label = $label;
    }
    /**
     * Get the id of the current range such as THIS_YEAR
     *
     * @return string
     */
    public function relative_range_id() : string
    {
        return $this->relative_range_id;
    }
    public function label() : string
    {
        return $this->label;
    }
    private function today() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        return [$today, $today, \__('Today', 'independent-analytics')];
    }
    private function yesterday() : array
    {
        $tz = Timezone::site_timezone();
        $yesterday = new DateTime('-1 day', $tz);
        return [$yesterday, $yesterday, \__('Yesterday', 'independent-analytics')];
    }
    private function last_seven() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $seven_days_ago = new DateTime('-6 days', $tz);
        return [$seven_days_ago, $today, \__('Last 7 Days', 'independent-analytics')];
    }
    private function last_thirty() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $thirty_days_ago = new DateTime('-29 days', $tz);
        return [$thirty_days_ago, $today, \__('Last 30 Days', 'independent-analytics')];
    }
    private function last_sixty() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $sixty_days_ago = new DateTime('-59 days', $tz);
        return [$sixty_days_ago, $today, \__('Last 60 Days', 'independent-analytics')];
    }
    private function last_ninety() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $ninety_days_ago = new DateTime('-89 days', $tz);
        return [$ninety_days_ago, $today, \__('Last 90 Days', 'independent-analytics')];
    }
    private function this_week() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $firstDayOfWeek = \intval(\get_option('iawp_dow'));
        $currentDayOfWeek = \intval($today->format('w'));
        $startOfWeekDaysAgo = $currentDayOfWeek - $firstDayOfWeek;
        if ($startOfWeekDaysAgo < 0) {
            $startOfWeekDaysAgo += 7;
        }
        $startOfWeek = new DateTime("-{$startOfWeekDaysAgo} days", $tz);
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');
        return [$startOfWeek, $endOfWeek, \__('This Week', 'independent-analytics')];
    }
    private function last_week() : array
    {
        list($start, $end) = $this->this_week();
        $startOfWeek = $start->modify('-7 days');
        $endOfWeek = $end->modify('-7 days');
        return [$startOfWeek, $endOfWeek, \__('Last Week', 'independent-analytics')];
    }
    private function this_month() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $day_of_month = \intval($today->format('d')) - 1;
        $days_in_month = \intval($today->format('t')) - 1;
        $start_of_month = (clone $today)->modify("-{$day_of_month} days");
        $end_of_month = (clone $start_of_month)->modify("+{$days_in_month} days");
        return [$start_of_month, $end_of_month, \__('This Month', 'independent-analytics')];
    }
    private function last_month() : array
    {
        list($start, $end) = $this->this_month();
        $start_of_last_month = (clone $start)->modify('-1 month');
        $days_in_last_month = \intval($start_of_last_month->format('t')) - 1;
        $end_of_last_month = (clone $start_of_last_month)->modify("+{$days_in_last_month} days");
        return [$start_of_last_month, $end_of_last_month, \__('Last Month', 'independent-analytics')];
    }
    private function last_three_months() : array
    {
        $tz = Timezone::site_timezone();
        $first_of_three_months_ago = new DateTime('first day of last month', $tz);
        $first_of_three_months_ago = $first_of_three_months_ago->modify('-2 months');
        $last_of_last_month = new DateTime('last day of last month', $tz);
        return [$first_of_three_months_ago, $last_of_last_month, \__('Last 3 Months', 'independent-analytics')];
    }
    private function last_six_months() : array
    {
        $tz = Timezone::site_timezone();
        $first_of_six_months_ago = new DateTime('first day of last month', $tz);
        $first_of_six_months_ago = $first_of_six_months_ago->modify('-5 months');
        $last_of_last_month = new DateTime('last day of last month', $tz);
        return [$first_of_six_months_ago, $last_of_last_month, \__('Last 6 Months', 'independent-analytics')];
    }
    private function last_twelve_months() : array
    {
        $tz = Timezone::site_timezone();
        $first_of_twelve_months_ago = new DateTime('first day of last month', $tz);
        $first_of_twelve_months_ago = $first_of_twelve_months_ago->modify('-11 months');
        $last_of_last_month = new DateTime('last day of last month', $tz);
        return [$first_of_twelve_months_ago, $last_of_last_month, \__('Last 12 Months', 'independent-analytics')];
    }
    private function this_year() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $year = \intval($today->format('Y'));
        $start_of_year = (clone $today)->setDate($year, 1, 1);
        $end_of_year = clone $today;
        return [$start_of_year, $end_of_year, \__('This Year', 'independent-analytics')];
    }
    private function last_year() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        $last_year = \intval($today->format('Y')) - 1;
        $start_of_last_year = (clone $today)->setDate($last_year, 1, 1);
        $end_of_last_year = (clone $today)->setDate($last_year, 12, 31);
        return [$start_of_last_year, $end_of_last_year, \__('Last Year', 'independent-analytics')];
    }
    private function all_time() : array
    {
        $tz = Timezone::site_timezone();
        $today = new DateTime('now', $tz);
        return [self::beginning_of_time(), $today, \__('All Time', 'independent-analytics')];
    }
    /**
     * Returns an array of relative ranges representing all supported ranges
     *
     * @return Relative_Date_Range[]
     */
    public static function ranges(?array $ranges_to_include = null) : array
    {
        return Collection::make(self::VALID_RELATIVE_RANGE_IDS)->when(\is_array($ranges_to_include), function ($collection) use($ranges_to_include) {
            return $collection->filter(function ($range) use($ranges_to_include) {
                return \in_array($range, $ranges_to_include);
            });
        })->map(function ($range) {
            return new self($range);
        })->all();
    }
    /**
     * @return string[]
     */
    public static function range_ids() : array
    {
        return self::VALID_RELATIVE_RANGE_IDS;
    }
    /**
     * Get a range by its id.
     *
     * @param ?string $range_id
     *
     * @return ?Relative_Date_Range
     */
    public static function range_by_id(?string $range_id = null) : ?\IAWP\Date_Range\Relative_Date_Range
    {
        if ($range_id === null) {
            return null;
        }
        if (self::is_valid_range($range_id)) {
            return new self($range_id);
        } else {
            return null;
        }
    }
    /**
     * @param string $range_id
     *
     * @return bool
     */
    public static function is_valid_range(string $range_id) : bool
    {
        if (\in_array($range_id, self::VALID_RELATIVE_RANGE_IDS)) {
            return \true;
        }
        return \false;
    }
    public static function beginning_of_time() : DateTime
    {
        $option_value = \get_option('iawp_beginning_of_time');
        if ($option_value !== \false && $option_value !== '') {
            try {
                $date = new DateTime($option_value, Timezone::utc_timezone());
                $date->setTimezone(Timezone::site_timezone());
                return $date;
            } catch (\Throwable $e) {
                return new DateTime('now', Timezone::site_timezone());
            }
        }
        $views_table = Query::get_table_name(Query::VIEWS);
        $first_view_at = Illuminate_Builder::new()->select('viewed_at')->from($views_table, 'views')->orderBy('viewed_at')->value('viewed_at');
        if (\is_null($first_view_at)) {
            return new DateTime('now', Timezone::site_timezone());
        }
        try {
            $date = new DateTime($first_view_at, Timezone::utc_timezone());
            \update_option('iawp_beginning_of_time', $first_view_at);
            return $date;
        } catch (\Throwable $e) {
            return new DateTime('now', Timezone::site_timezone());
        }
    }
}
