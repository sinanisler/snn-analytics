<?php

namespace IAWP\Journey;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Cron_Job;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class JourneyStatisticsJob extends Cron_Job
{
    protected $name = 'iawp_journey_statistics';
    protected $interval = 'daily';
    protected $at_midnight = \true;
    public function should_execute_handler() : bool
    {
        return \IAWPSCOPED\iawp_is_pro();
    }
    public function handle() : void
    {
        $this->calculate_average_views_cutoff();
        $this->calculate_duration_cutoffs();
    }
    private function calculate_average_views_cutoff() : void
    {
        $thiry_days_ago = CarbonImmutable::now()->subDays(30);
        $sessions = Illuminate_Builder::new()->from(Tables::sessions())->where('total_views', '>', 1)->where('created_at', '>', $thiry_days_ago)->count();
        if ($sessions < 25) {
            return;
        }
        $sessions_in_seventy_fifth_percentile = \intval(\round($sessions * 0.75));
        $average_views_cutoff = Illuminate_Builder::new()->select('total_views')->from(Tables::sessions())->where('total_views', '>', 1)->where('created_at', '>', $thiry_days_ago)->orderBy('total_views', 'ASC')->limit(1)->offset($sessions_in_seventy_fifth_percentile)->value('total_views');
        \update_option('iawp_average_views_cutoff', $average_views_cutoff, \false);
    }
    private function calculate_duration_cutoffs() : void
    {
        $thiry_days_ago = CarbonImmutable::now()->subDays(30);
        $sessions = Illuminate_Builder::new()->from(Tables::sessions())->whereNotNull('ended_at')->where('created_at', '>', $thiry_days_ago)->count();
        if ($sessions < 25) {
            return;
        }
        $sessions_in_thirty_third_percentile = \intval(\round($sessions * 0.33));
        $sessions_in_sixty_seventh_percentile = \intval(\round($sessions * 0.67));
        $low_duration_cutoff = Illuminate_Builder::new()->selectRaw('TIMESTAMPDIFF(SECOND, created_at, ended_at) AS duration')->from(Tables::sessions())->whereNotNull('ended_at')->where('created_at', '>', $thiry_days_ago)->orderBy('total_views', 'ASC')->limit(1)->offset($sessions_in_thirty_third_percentile)->value('total_views');
        $average_duration_cutoff = Illuminate_Builder::new()->selectRaw('TIMESTAMPDIFF(SECOND, created_at, ended_at) AS duration')->from(Tables::sessions())->whereNotNull('ended_at')->where('created_at', '>', $thiry_days_ago)->orderBy('duration', 'ASC')->limit(1)->offset($sessions_in_sixty_seventh_percentile)->value('duration');
        \update_option('iawp_low_duration_cutoff', $low_duration_cutoff, \false);
        \update_option('iawp_average_duration_cutoff', $average_duration_cutoff, \false);
    }
}
