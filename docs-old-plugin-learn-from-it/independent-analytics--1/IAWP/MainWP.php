<?php

namespace IAWP;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Tables\Table_Pages;
use IAWP\Utils\Number_Formatter;
use IAWP\Utils\Security;
use IAWP\Utils\Timezone;
/** @internal */
class MainWP
{
    public static function initialize()
    {
        \add_filter('mainwp_site_sync_others_data', function ($information, $data = []) {
            return self::attach_analytics($information, $data);
        }, 10, 2);
        \add_filter('mainwp_child_prepared_reports_data', function ($data, $sections, $other_tokens, $options) {
            return self::attach_analytics_to_pro_report($data, $sections, $other_tokens, $options);
        }, 10, 4);
    }
    private static function attach_analytics(array $information, array $data) : array
    {
        $should_sync_analytics = \array_key_exists('iawp_sync_analytics', $data) && \true === $data['iawp_sync_analytics'];
        if (!$should_sync_analytics) {
            return $information;
        }
        try {
            $table = new Table_Pages();
            $statistics_class = $table->group()->statistics_class();
            $date_range = new Relative_Date_Range('LAST_THIRTY');
            $chart_interval = Intervals::default_for($date_range->number_of_days());
            $statistics = new $statistics_class($date_range, null, $chart_interval);
            $views = $statistics->get_statistic('views');
            $visitors = $statistics->get_statistic('visitors');
            $labels = \array_map(function ($data_point) use($statistics) {
                return Security::json_encode($statistics->chart_interval()->get_label_for($data_point[0]));
            }, $views->statistic_over_time());
            $views_over_time = \array_map(function ($data_point) {
                return $data_point[1];
            }, $views->statistic_over_time());
            $visitors_over_time = \array_map(function ($data_point) {
                return $data_point[1];
            }, $visitors->statistic_over_time());
            $information['iawp_analytics'] = ['analytics_dashboard_url' => \IAWPSCOPED\iawp_dashboard_url(), 'labels' => $labels, 'views_over_time' => $views_over_time, 'visitors_over_time' => $visitors_over_time, 'views' => Number_Formatter::integer($views->value()), 'visitors' => Number_Formatter::integer($visitors->value())];
        } catch (\Throwable $e) {
            // Do nothing
        }
        return $information;
    }
    private static function attach_analytics_to_pro_report($data, $sections, $other_tokens, $options)
    {
        try {
            $from = CarbonImmutable::parse($options['date_from'], Timezone::site_timezone());
            $to = CarbonImmutable::parse($options['date_to'], Timezone::site_timezone());
            $analytics = \IAWPSCOPED\iawp_analytics($from->toDate(), $to->toDate());
            $data['other_tokens_data']['body']['[iawp.views]'] = Number_Formatter::integer($analytics->views);
            $data['other_tokens_data']['header']['[iawp.views]'] = Number_Formatter::integer($analytics->views);
            $data['other_tokens_data']['body']['[iawp.visitors]'] = Number_Formatter::integer($analytics->visitors);
            $data['other_tokens_data']['header']['[iawp.visitors]'] = Number_Formatter::integer($analytics->visitors);
            $data['other_tokens_data']['body']['[iawp.sessions]'] = Number_Formatter::integer($analytics->sessions);
            $data['other_tokens_data']['header']['[iawp.sessions]'] = Number_Formatter::integer($analytics->sessions);
        } catch (\Throwable $e) {
        }
        return $data;
    }
}
