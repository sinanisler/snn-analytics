<?php

namespace IAWP\Overview\Modules;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Icon_Directory_Factory;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Timezone;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
/** @internal */
class Recent_Views_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'recent-views';
    }
    public function module_name() : string
    {
        return \__('Recent Views', 'independent-analytics');
    }
    public function subtitle() : string
    {
        return \__('Most Recent', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $tables = Tables::class;
        $query = Illuminate_Builder::new()->select(['views.viewed_at', 'resources.cached_title AS page_title', 'countries.country_code', 'countries.country', 'device_types.device_type', 'device_browsers.device_browser AS browser'])->from($tables::views(), 'views')->leftJoin("{$tables::resources()} as resources", 'views.resource_id', '=', 'resources.id')->leftJoin("{$tables::sessions()} as sessions", 'views.session_id', '=', 'sessions.session_id')->leftJoin("{$tables::countries()} as countries", 'sessions.country_id', '=', 'countries.country_id')->leftJoin("{$tables::device_types()} as device_types", 'sessions.device_type_id', '=', 'device_types.device_type_id')->leftJoin("{$tables::device_browsers()} as device_browsers", 'sessions.device_browser_id', '=', 'device_browsers.device_browser_id')->orderBy('views.viewed_at', 'desc')->limit(40);
        return $query->get()->map(function ($row) {
            $date = CarbonImmutable::parse($row->viewed_at)->setTimezone(Timezone::site_timezone());
            $time_format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
            $long_date_string = $date->format(WordPress_Site_Date_Format_Pattern::for_php() . ' ' . $time_format);
            if ($date->isToday()) {
                $short_date_string = $date->format($time_format);
            } elseif ($date->isYesterday()) {
                $short_date_string = \__('Yesterday', 'independent-analytics');
            } else {
                $short_date_string = $date->startOfDay()->diffForHumans();
            }
            return ['viewed_at' => $short_date_string, 'viewed_at_the_long_way' => $long_date_string, 'page_title' => $row->page_title, 'country_code' => $row->country_code, 'country' => $row->country ?? \__('Unknown Country', 'independent-analytics'), 'device_type' => $row->device_type ?? \__('Unknown Device Type', 'independent-analytics'), 'browser' => $row->browser ?? \__('Unknown Browser', 'independent-analytics')];
        })->all();
    }
    public function add_icons_to_dataset(array $dataset) : array
    {
        $flags = Icon_Directory_Factory::flags();
        $device_types = Icon_Directory_Factory::device_types();
        $browsers = Icon_Directory_Factory::browsers();
        return \array_map(function (array $row) use($flags, $device_types, $browsers) {
            $row['flag'] = $flags->find($row['country_code'] ?? '');
            $row['device_type_icon'] = $device_types->find($row['device_type'] ?? '');
            $row['browser_icon'] = $browsers->find($row['browser'] ?? '');
            return $row;
        }, $dataset);
    }
    protected function module_fields() : array
    {
        return [];
    }
}
