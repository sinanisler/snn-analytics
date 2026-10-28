<?php

namespace IAWP\Tables;

use IAWP\ColumnOptions\Options;
use IAWP\ColumnOptions\Plugins\Authors;
use IAWP\ColumnOptions\Plugins\Categories;
use IAWP\ColumnOptions\Plugins\PageTypes;
use IAWP\Rows\Pages;
use IAWP\Statistics\Page_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Pages extends \IAWP\Tables\Table
{
    public function id() : string
    {
        return 'views';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('page', \__('Page', 'independent-analytics'), 'title', Pages::class, Page_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'title', 'name' => \__('Title', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'separate_database_column' => 'cached_title', 'is_concrete_column' => \true]), new Column(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'average_view_duration', 'name' => \__('View Duration', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'filter_placeholder' => 'Seconds']), new Column(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'visible' => \true, 'type' => 'int']), new Column(['id' => 'visitors_growth', 'name' => \__('Visitors Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'views_growth', 'name' => \__('Views Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'entrances', 'name' => \__('Entrances', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'exits', 'name' => \__('Exits', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'exit_percent', 'name' => \__('Exit Rate', 'independent-analytics'), 'type' => 'int']), new Column(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'type' => 'int', 'requires_pro' => \true, 'aggregatable' => \true]), new Column(['id' => 'url', 'name' => \__('URL', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'separate_database_column' => 'cached_url', 'is_concrete_column' => \true]), new Column(['id' => 'author', 'name' => \__('Author', 'independent-analytics'), 'type' => 'select', 'options' => new Options(new Authors()), 'separate_database_column' => 'cached_author_id', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'type', 'name' => \__('Page Type', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new PageTypes()), 'separate_database_column' => 'cached_type', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'date', 'name' => \__('Publish Date', 'independent-analytics'), 'type' => 'date', 'separate_database_column' => 'cached_date', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'category', 'name' => \__('Post Category', 'independent-analytics'), 'type' => 'select', 'options' => new Options(new Categories()), 'separate_database_column' => 'cached_category', 'is_nullable' => \true, 'is_concrete_column' => \true]), new Column(['id' => 'comments', 'name' => \__('Comments', 'independent-analytics'), 'type' => 'int', 'is_nullable' => \true])];
        return \array_merge($columns, $this->get_woocommerce_columns(), $this->get_form_columns());
    }
}
