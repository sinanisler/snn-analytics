<?php

namespace IAWP;

use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Query_Taps
{
    public static function tap_authored_content_check($should_join_resources = \true)
    {
        return function (Builder $query) use($should_join_resources) {
            if (!\is_user_logged_in() || \IAWP\Capability_Manager::can_view_all_analytics()) {
                return;
            }
            if ($should_join_resources) {
                $resources_table = \IAWP\Query::get_table_name(\IAWP\Query::RESOURCES);
                $query->leftJoin($query->raw($resources_table . ' AS resources'), function (JoinClause $join) {
                    $join->on('views.resource_id', '=', 'resources.id');
                });
            }
            $query->where('resources.cached_author_id', '=', \get_current_user_id());
        };
    }
    public static function tap_authored_content_for_clicks()
    {
        return function (Builder $query) {
            if (!\is_user_logged_in() || \IAWP\Capability_Manager::can_view_all_analytics()) {
                return;
            }
            $resources_table = \IAWP\Query::get_table_name(\IAWP\Query::RESOURCES);
            $query->leftJoin($query->raw($resources_table . ' AS resources'), function (JoinClause $join) {
                $join->on('views.resource_id', '=', 'resources.id');
            });
            $query->where('resources.cached_author_id', '=', \get_current_user_id());
        };
    }
    public static function tap_related_to_examined_record(?\IAWP\Examiner_Config $config)
    {
        return function (Builder $query) use($config) {
            if (!$config) {
                return;
            }
            $column = self::examiner_type_to_column($config->group());
            if (!$column) {
                return;
            }
            if ($config->group() === 'referrer_type') {
                $query->leftJoin(\IAWP\Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id');
            }
            $campaign_groups = ['landing_page', 'utm_source', 'utm_medium', 'utm_campaign'];
            if (\in_array($config->group(), $campaign_groups)) {
                $query->leftJoin(\IAWP\Tables::campaigns() . ' AS campaigns', 'sessions.campaign_id', '=', 'campaigns.campaign_id');
            }
            if ($config->group() === 'link' || $config->group() === 'link_pattern') {
                $query->leftJoin(\IAWP\Tables::clicked_links() . ' AS clicked_links', 'clicked_links.click_id', '=', 'clicks.click_id');
                $query->leftJoin(\IAWP\Tables::links() . ' AS links', 'links.id', '=', 'clicked_links.link_id');
            }
            $query->where($column, '=', $config->id());
        };
    }
    public static function tap_related_to_examined_record_for_previous_period(?\IAWP\Examiner_Config $config, array $tables)
    {
        return function (Builder $query) use($config, $tables) {
            if (!$config) {
                return;
            }
            $column = self::examiner_type_to_column($config->group());
            if (!$column) {
                return;
            }
            $table = Str::before($column, '.');
            if (!\in_array($table, $tables)) {
                if ($table === 'views') {
                    $query->leftJoin(\IAWP\Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id');
                }
                if ($table === 'referrers') {
                    $query->leftJoin(\IAWP\Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id');
                }
                if ($table === 'campaigns') {
                    $query->leftJoin(\IAWP\Tables::campaigns() . ' AS campaigns', 'sessions.campaign_id', '=', 'campaigns.campaign_id');
                }
                if ($table === 'links') {
                    if (!\in_array('views', $tables)) {
                        $query->leftJoin(\IAWP\Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id');
                    }
                    $query->leftJoin(\IAWP\Tables::clicks() . ' AS clicks', 'views.id', '=', 'clicks.view_id');
                    $query->leftJoin(\IAWP\Tables::clicked_links() . ' AS clicked_links', 'clicked_links.click_id', '=', 'clicks.click_id');
                    $query->leftJoin(\IAWP\Tables::links() . ' AS links', 'links.id', '=', 'clicked_links.link_id');
                }
            }
            $query->where($column, '=', $config->id());
        };
    }
    private static function examiner_type_to_column(string $group) : ?string
    {
        switch ($group) {
            case 'page':
                return 'views.resource_id';
            case 'referrer':
                return 'sessions.referrer_id';
            case 'referrer_type':
                return 'referrers.referrer_type_id';
            case 'country':
                return 'sessions.country_id';
            case 'city':
                return 'sessions.city_id';
            case 'device_type':
                return 'sessions.device_type_id';
            case 'os':
                return 'sessions.device_os_id';
            case 'browser':
                return 'sessions.device_browser_id';
            case 'campaign':
                return 'sessions.campaign_id';
            case 'landing_page':
                return 'campaigns.landing_page_id';
            case 'utm_source':
                return 'campaigns.utm_source_id';
            case 'utm_medium':
                return 'campaigns.utm_medium_id';
            case 'utm_campaign':
                return 'campaigns.utm_campaign_id';
            case 'link':
                return 'links.id';
            case 'link_pattern':
                return 'links.link_rule_id';
            default:
                return null;
        }
    }
}
