<?php

namespace IAWP\Overview\Modules;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Busiest_Time_Of_Day_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'busiest-time-of-day';
    }
    public function module_name() : string
    {
        return \__('Busiest Time of Day', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $tables = Tables::class;
        $offset = Timezone::site_offset();
        $sessions_by_hour_of_day = Illuminate_Builder::new()->selectRaw("HOUR(CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}')) AS hour")->selectRaw("DAYOFYEAR(CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}')) - 1 AS day")->selectRaw('COUNT(*) AS sessions')->from($tables::sessions(), 'sessions')->whereRaw("CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}') > CONVERT_TZ(CURDATE(), '+00:00', '{$offset}') - INTERVAL 90 DAY")->groupBy(['day', 'hour']);
        $query = Illuminate_Builder::new()->select(['hour'])->selectRaw('AVG(sessions) as sessions')->fromSub($sessions_by_hour_of_day, 'sessions')->groupBy('hour');
        return $query->get()->map(function ($row) {
            return ['hour' => \intval($row->hour), 'sessions' => \intval($row->sessions)];
        })->all();
    }
    public function get_labels(array $dataset) : array
    {
        $hours = \range(0, 23);
        return \array_map(function ($hour) {
            $time = CarbonImmutable::createFromTime($hour, 0, 0, Timezone::site_timezone());
            $format = 'g a';
            if (\IAWPSCOPED\iawp()->prefers_24_hour_clock()) {
                $format = 'G';
            }
            return \json_encode(['tick' => $time->format($format), 'tooltipLabel' => \__('Hour', 'independent-analytics') . ': ' . $time->format($format)]);
        }, $hours);
    }
    public function get_sessions(array $dataset) : array
    {
        $hours = \range(0, 23);
        $dataset_collection = Collection::make($dataset);
        return \array_map(function ($hour) use($dataset_collection) {
            $matching_row = $dataset_collection->first(function ($row) use($hour) {
                $time = CarbonImmutable::createFromTime($row['hour'], 0, 0, Timezone::site_timezone())->setTimezone(Timezone::site_timezone());
                return $time->hour === $hour;
            });
            if ($matching_row === null) {
                return 0;
            }
            return $matching_row['sessions'];
        }, $hours);
    }
    protected function module_fields() : array
    {
        return ['busiest_date_range'];
    }
}
