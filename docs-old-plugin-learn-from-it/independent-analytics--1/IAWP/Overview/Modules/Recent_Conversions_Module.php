<?php

namespace IAWP\Overview\Modules;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Icon_Directory_Factory;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Currency;
use IAWP\Utils\Timezone;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
/** @internal */
class Recent_Conversions_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'recent-conversions';
    }
    public function module_name() : string
    {
        return \__('Recent Conversions', 'independent-analytics');
    }
    public function subtitle() : string
    {
        return \__('Most Recent', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $tables = Tables::class;
        // Get recent clicks
        $recent_clicks_query = Illuminate_Builder::new()->select(['clicks.view_id', 'clicks.created_at', 'link_rules.name'])->selectRaw("'click' as conversion_type")->from($tables::clicks(), 'clicks')->join("{$tables::clicked_links()} AS clicked_links", 'clicks.click_id', '=', 'clicked_links.click_id')->join("{$tables::links()} AS links", 'clicked_links.link_id', '=', 'links.id')->join("{$tables::link_rules()} AS link_rules", 'links.link_rule_id', '=', 'link_rules.link_rule_id');
        // Get form submission conversions
        $recent_submissions_query = Illuminate_Builder::new()->select(['form_submissions.view_id', 'form_submissions.created_at'])->selectRaw("IF(forms.cached_form_title = '', '(Unnamed form)', forms.cached_form_title) AS name")->selectRaw("'form_submission' as conversion_type")->from($tables::form_submissions(), 'form_submissions')->join("{$tables::forms()} AS forms", "form_submissions.form_id", '=', "forms.form_id");
        // Get order conversions
        $recent_orders_query = Illuminate_Builder::new()->select(['orders.view_id', 'orders.created_at', 'orders.total AS name'])->selectRaw("'order' as conversion_type")->from($tables::orders(), 'orders')->where('orders.is_included_in_analytics', '=', \true);
        // Create a single conversion query for all conversion types
        $conversion_query = $recent_clicks_query->unionAll($recent_orders_query)->unionAll($recent_submissions_query);
        $query = Illuminate_Builder::new()->select(['conversions.view_id', 'conversions.created_at', 'conversions.conversion_type', 'conversions.name', 'countries.country_code', 'countries.country', 'device_types.device_type', 'device_browsers.device_browser AS browser'])->fromSub($conversion_query, 'conversions')->leftJoin("{$tables::views()} as views", 'conversions.view_id', '=', 'views.id')->leftJoin("{$tables::resources()} as resources", 'views.resource_id', '=', 'resources.id')->leftJoin("{$tables::sessions()} as sessions", 'views.session_id', '=', 'sessions.session_id')->leftJoin("{$tables::countries()} as countries", 'sessions.country_id', '=', 'countries.country_id')->leftJoin("{$tables::device_types()} as device_types", 'sessions.device_type_id', '=', 'device_types.device_type_id')->leftJoin("{$tables::device_browsers()} as device_browsers", 'sessions.device_browser_id', '=', 'device_browsers.device_browser_id')->whereIn('conversions.conversion_type', $this->attributes['recent_conversion_types'] ?? ['order', 'form_submission', 'click'])->orderByDesc('conversions.created_at')->limit(40);
        return $query->get()->map(function ($row) {
            $date = CarbonImmutable::parse($row->created_at)->setTimezone(Timezone::site_timezone());
            $time_format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
            $long_date_string = $date->format(WordPress_Site_Date_Format_Pattern::for_php() . ' ' . $time_format);
            if ($date->isToday()) {
                $short_date_string = $date->format($time_format);
            } elseif ($date->isYesterday()) {
                $short_date_string = \__('Yesterday', 'independent-analytics');
            } else {
                $short_date_string = $date->startOfDay()->diffForHumans();
            }
            if ($row->conversion_type == 'order') {
                $name = Currency::format($row->name, \false);
            } else {
                $name = $row->name;
            }
            return ['viewed_at' => $short_date_string, 'viewed_at_the_long_way' => $long_date_string, 'name' => $name, 'country_code' => $row->country_code, 'country' => $row->country ?? \__('Unknown Country', 'independent-analytics'), 'device_type' => $row->device_type ?? \__('Unknown Device Type', 'independent-analytics'), 'browser' => $row->browser ?? \__('Unknown Browser', 'independent-analytics'), 'conversion_type' => $row->conversion_type, 'conversion_label' => $this->get_conversion_label($row->conversion_type)];
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
        return ['recent_conversion_types'];
    }
    private function get_conversion_label(string $conversion_type) : string
    {
        switch ($conversion_type) {
            case 'order':
                return \__('Order', 'independent-analytics');
            case 'form_submission':
                return \__('Form', 'independent-analytics');
            default:
                return \__('Click', 'independent-analytics');
        }
    }
}
