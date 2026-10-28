<?php

namespace IAWP\Data_Pruning;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Utils\Timezone;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
/** @internal */
class Pruning_Scheduler
{
    public static $cutoff_options = [['disabled', 'Keep data forever'], ['thirty-days', 'Keep data for 30 days'], ['sixty-days', 'Keep data for 60 days'], ['ninety-days', 'Keep data for 90 days'], ['one-hundred-and-eighty-days', 'Keep data for 180 days'], ['one-year', 'Keep data for 1 year'], ['two-years', 'Keep data for 2 years'], ['three-years', 'Keep data for 3 years'], ['four-years', 'Keep data for 4 years']];
    public function cutoff_options() : array
    {
        return self::$cutoff_options;
    }
    public function is_enabled() : bool
    {
        return \get_option('iawp_pruning_cutoff', 'disabled') !== 'disabled';
    }
    public function get_pruning_cutoff() : string
    {
        return \get_option('iawp_pruning_cutoff', 'disabled');
    }
    public function get_pruning_cutoff_as_datetime() : ?\DateTime
    {
        if (!$this->is_enabled()) {
            return null;
        }
        return $this->convert_cutoff_to_date($this->get_pruning_cutoff());
    }
    public function status_message() : ?string
    {
        if (!$this->is_enabled()) {
            return null;
        }
        $scheduled_at = new \DateTime();
        $scheduled_at->setTimezone(Timezone::site_timezone());
        $scheduled_at->setTimestamp(\wp_next_scheduled('iawp_prune'));
        $day = $scheduled_at->format(\get_option('date_format'));
        $time = $scheduled_at->format(\IAWPSCOPED\iawp()->get_option('time_format', 'g:i a'));
        return \sprintf(\__('Next data pruning scheduled for %s at %s.', 'independent-analytics'), '<span>' . $day . '</span>', '<span>' . $time . '</span>');
    }
    public function get_pruning_description(string $cutoff) : string
    {
        $date = $this->convert_cutoff_to_date($cutoff, \true);
        $utc_date = $this->convert_cutoff_to_date($cutoff);
        $formatted_date = $date->format(WordPress_Site_Date_Format_Pattern::for_php());
        $estimates = $this->get_pruning_estimates($utc_date);
        return \sprintf(\__("All data from before %1\$s will be deleted immediately. This will remove %2\$s of your %3\$s tracked sessions. \n\n This process will repeat daily at midnight.", 'independent-analytics'), $formatted_date, \number_format_i18n($estimates['sessions_to_be_deleted']), \number_format_i18n($estimates['sessions']));
    }
    /**
     * @return array{sessions: int, sessions_to_be_deleted: int}
     */
    public function get_pruning_estimates(\DateTime $cutoff_date) : array
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $sessions = Illuminate_Builder::new()->from($sessions_table)->selectRaw('COUNT(*) as sessions')->value('sessions');
        $sessions_to_be_deleted = Illuminate_Builder::new()->from($sessions_table)->selectRaw('COUNT(*) as sessions')->where('created_at', '<', $cutoff_date)->value('sessions');
        return ['sessions' => $sessions, 'sessions_to_be_deleted' => $sessions_to_be_deleted];
    }
    public function update_pruning_cutoff(string $cutoff) : bool
    {
        $is_valid_cutoff = \false;
        foreach (self::$cutoff_options as $option) {
            if ($option[0] === $cutoff) {
                $is_valid_cutoff = \true;
            }
        }
        if (!$is_valid_cutoff) {
            return \false;
        }
        \update_option('iawp_pruning_cutoff', $cutoff, \true);
        $this->schedule_pruning($cutoff);
        return \true;
    }
    /**
     * Attempt to schedule pruning based on whatever existing cutoff option is saved
     *
     * @return void
     */
    public function schedule() : void
    {
        $cutoff = \get_option('iawp_pruning_cutoff', '');
        $this->update_pruning_cutoff($cutoff);
    }
    private function schedule_pruning(string $cutoff) : void
    {
        \wp_unschedule_hook('iawp_prune');
        if ($cutoff === 'disabled') {
            return;
        }
        $tomorrow = new \DateTime('tomorrow', Timezone::site_timezone());
        \wp_schedule_event($tomorrow->getTimestamp(), 'daily', 'iawp_prune');
    }
    private function convert_cutoff_to_date(string $cutoff, bool $as_site_timezone = \false) : \DateTime
    {
        $beginning_of_today = new CarbonImmutable('today', Timezone::site_timezone());
        if (!$as_site_timezone) {
            $beginning_of_today = $beginning_of_today->setTimezone(Timezone::utc_timezone());
        }
        switch ($cutoff) {
            case 'thirty-days':
                return $beginning_of_today->subDays(30)->toDate();
            case 'sixty-days':
                return $beginning_of_today->subDays(60)->toDate();
            case 'ninety-days':
                return $beginning_of_today->subDays(90)->toDate();
            case 'one-hundred-and-eighty-days':
                return $beginning_of_today->subDays(180)->toDate();
            case 'one-year':
                return $beginning_of_today->subYearsNoOverflow(1)->toDate();
            case 'two-years':
                return $beginning_of_today->subYearsNoOverflow(2)->toDate();
            case 'three-years':
                return $beginning_of_today->subYearsNoOverflow(3)->toDate();
            case 'four-years':
                return $beginning_of_today->subYearsNoOverflow(4)->toDate();
        }
        return $beginning_of_today->toDate();
    }
}
