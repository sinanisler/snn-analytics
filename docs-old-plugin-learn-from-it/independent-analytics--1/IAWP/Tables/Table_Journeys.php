<?php

namespace IAWP\Tables;

use IAWP\ColumnOptions\Options;
use IAWP\ColumnOptions\Plugins\Countries;
use IAWP\ColumnOptions\Plugins\DeviceBrowsers;
use IAWP\ColumnOptions\Plugins\DeviceTypes;
use IAWP\ColumnOptions\Plugins\FormConversions;
use IAWP\ColumnOptions\Plugins\LinkPatternConversions;
use IAWP\Rows\Filter;
use IAWP\Rows\Journeys;
use IAWP\Statistics\Journey_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Journeys extends \IAWP\Tables\Table
{
    protected $default_sorting_column = 'created_at';
    public function id() : string
    {
        return 'journeys';
    }
    public function allow_downloading() : bool
    {
        return \false;
    }
    public function get_table_toolbar_markup()
    {
        return '';
    }
    public function get_table_markup(string $sort_column, string $sort_direction)
    {
        return \IAWPSCOPED\iawp_blade()->run('journeys.table', ['table' => $this, 'rows' => [], 'render_skeleton' => \true, 'page_size' => \IAWPSCOPED\iawp()->pagination_page_size()]);
    }
    public function get_rendered_template(array $rows, bool $just_rows, string $sort_column, string $sort_direction)
    {
        return \IAWPSCOPED\iawp_blade()->run('journeys.rows', ['table' => $this, 'rows' => $rows]);
    }
    public function resolve_filter_conflicts(array $filters, string $filter_logic) : array
    {
        // Are you back again to make changes or are you doing this for another table?
        // Consider moving the rules to a configuration object that'll be easier to change and reuse.
        $columns = \array_map(function (Filter $filter) {
            return $filter->column();
        }, $filters);
        $columns_to_disable = [];
        if ($filter_logic === 'or' && (\in_array('submitted_form', $columns) || \in_array('clicked_link', $columns) || \in_array('page', $columns))) {
            if (\in_array('views', $columns)) {
                $columns_to_disable[] = 'views';
            }
            if (\in_array('duration', $columns)) {
                $columns_to_disable[] = 'duration';
            }
        }
        if (empty($columns_to_disable)) {
            return $filters;
        }
        $modified_filters = \array_values(\array_filter($filters, function (Filter $filter) use($columns_to_disable) {
            return !\in_array($filter->column(), $columns_to_disable);
        }));
        return $modified_filters;
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('journey', \__('User Journeys', 'independent-analytics'), '', Journeys::class, Journey_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'created_at', 'name' => \__('Session Start', 'independent-analytics'), 'type' => 'date', 'visible' => \true, 'is_concrete_column' => \true, 'can_be_filtered' => \false]), new Column(['id' => 'cached_title', 'name' => \__('Landing Page Title', 'independent-analytics'), 'type' => 'string', 'visible' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'page', 'name' => \__('Page Title', 'independent-analytics'), 'type' => 'string', 'visible' => \true, 'separate_filter_column' => 'resources.cached_title', 'is_concrete_column' => \true]), new Column(['id' => 'referrer', 'name' => \__('Referrer', 'independent-analytics'), 'type' => 'string', 'visible' => \true, 'is_concrete_column' => \true, 'separate_database_column' => 'referrer']), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'type' => 'int', 'visible' => \true, 'is_concrete_column' => \false]), new Column(['id' => 'duration', 'name' => \__('Session Duration', 'independent-analytics'), 'type' => 'int', 'visible' => \true, 'is_concrete_column' => \false]), new Column(['id' => 'country', 'name' => \__('Country', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new Countries()), 'separate_filter_column' => 'sessions.country_id', 'is_concrete_column' => \true]), new Column(['id' => 'device_type', 'name' => \__('Device Type', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new DeviceTypes()), 'separate_filter_column' => 'sessions.device_type_id', 'is_concrete_column' => \true]), new Column(['id' => 'device_browser', 'name' => \__('Browser', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new DeviceBrowsers()), 'separate_filter_column' => 'sessions.device_browser_id', 'is_concrete_column' => \true]), new Column(['id' => 'utm_source', 'name' => \__('Source', 'independent-analytics'), 'plugin_group' => 'campaigns', 'visible' => \true, 'type' => 'string', 'is_concrete_column' => \true]), new Column(['id' => 'utm_medium', 'name' => \__('Medium', 'independent-analytics'), 'plugin_group' => 'campaigns', 'visible' => \true, 'type' => 'string', 'is_concrete_column' => \true]), new Column(['id' => 'utm_campaign', 'name' => \__('Campaign', 'independent-analytics'), 'plugin_group' => 'campaigns', 'visible' => \true, 'type' => 'string', 'is_concrete_column' => \true]), new Column(['id' => 'utm_term', 'name' => \__('Term', 'independent-analytics'), 'plugin_group' => 'campaigns', 'type' => 'string', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'utm_content', 'name' => \__('Content', 'independent-analytics'), 'plugin_group' => 'campaigns', 'type' => 'string', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'submitted_form', 'name' => \__('Form Submission', 'independent-analytics'), 'plugin_group' => 'conversions', 'type' => 'select', 'options' => new Options(new FormConversions()), 'visible' => \true, 'is_concrete_column' => \true, 'separate_filter_column' => 'form_id']), new Column(['id' => 'clicked_link', 'name' => \__('Clicked Link', 'independent-analytics'), 'plugin_group' => 'conversions', 'type' => 'select', 'options' => new Options(new LinkPatternConversions()), 'visible' => \true, 'is_concrete_column' => \true, 'separate_filter_column' => 'clicks.link_rule_id']), new Column(['id' => 'wc_gross_sales', 'name' => \__('Gross Sales', 'independent-analytics'), 'plugin_group' => 'conversions', 'type' => 'int', 'visible' => \true, 'is_concrete_column' => \true])];
        return $columns;
    }
}
