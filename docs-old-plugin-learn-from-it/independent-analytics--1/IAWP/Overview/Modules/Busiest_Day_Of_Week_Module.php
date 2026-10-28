<?php

namespace IAWP\Overview\Modules;

use IAWPSCOPED\Carbon\Carbon;
use IAWPSCOPED\Carbon\CarbonInterface;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Busiest_Day_Of_Week_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'busiest-day-of-week';
    }
    public function module_name() : string
    {
        return \__('Busiest Day of Week', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $tables = Tables::class;
        $offset = Timezone::site_offset();
        $sessions_by_day_of_week = Illuminate_Builder::new()->selectRaw("DAYOFWEEK(CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}')) - 1 AS day")->selectRaw("WEEK(CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}')) AS week")->selectRaw('COUNT(*) AS sessions')->from($tables::sessions(), 'sessions')->whereRaw("CONVERT_TZ(sessions.created_at, '+00:00', '{$offset}') > CONVERT_TZ(CURDATE(), '+00:00', '{$offset}') - INTERVAL 90 DAY")->groupBy(['week', 'day']);
        $query = Illuminate_Builder::new()->select(['day'])->selectRaw('AVG(sessions) as sessions')->fromSub($sessions_by_day_of_week, 'sessions')->groupBy('day');
        return $query->get()->map(function ($row) {
            return ['day' => \intval($row->day), 'sessions' => \intval($row->sessions)];
        })->all();
    }
    public function get_labels(array $dataset) : array
    {
        $days = $this->shift_to_start_of_week(\range(0, 6));
        return \array_map(function ($day) {
            $date = Carbon::now()->startOfWeek(CarbonInterface::SUNDAY)->addDays($day);
            return \json_encode(['tick' => $date->format('l'), 'tooltipLabel' => $date->format('l')]);
        }, $days);
    }
    public function get_sessions(array $dataset) : array
    {
        $days = $this->shift_to_start_of_week(\range(0, 6));
        $dataset_collection = Collection::make($dataset);
        return \array_map(function ($day) use($dataset_collection) {
            $matching_row = $dataset_collection->first(function ($row) use($day) {
                return $row['day'] === $day;
            });
            if ($matching_row === null) {
                return 0;
            }
            return $matching_row['sessions'];
        }, $days);
    }
    public function shift_to_start_of_week(array $dataset) : array
    {
        $day_of_week = \IAWPSCOPED\iawp()->get_option('iawp_dow', 0);
        $collection = Collection::make($dataset);
        return $collection->splice($day_of_week)->merge($collection)->values()->all();
    }
    protected function module_fields() : array
    {
        return ['busiest_date_range'];
    }
}
