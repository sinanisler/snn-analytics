<?php

namespace IAWP\Tables;

use IAWP\ColumnOptions\Options;
use IAWP\ColumnOptions\Plugins\LinkPatterns;
use IAWP\Rows\Link_Patterns;
use IAWP\Rows\Links;
use IAWP\Statistics\Click_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Clicks extends \IAWP\Tables\Table
{
    protected $default_sorting_column = 'link_clicks';
    public function id() : string
    {
        return 'clicks';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('link', \__('Links', 'independent-analytics'), 'link_target', Links::class, Click_Statistics::class);
        $groups[] = new Group('link_pattern', \__('Link Patterns', 'independent-analytics'), 'link_name', Link_Patterns::class, Click_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'link_name', 'name' => \__('Link Pattern', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new LinkPatterns()), 'separate_filter_column' => 'link_rules.link_rule_id', 'is_concrete_column' => \true]), new Column(['id' => 'link_target', 'name' => \__('Target', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['link_pattern'], 'separate_database_column' => 'target', 'is_concrete_column' => \true]), new Column(['id' => 'link_clicks', 'name' => \__('Clicks', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true])];
        return $columns;
    }
}
