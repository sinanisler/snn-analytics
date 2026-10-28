<?php

namespace IAWP\Tables;

use IAWP\Rows\Cities;
use IAWP\Rows\Countries;
use IAWP\Statistics\City_Statistics;
use IAWP\Statistics\Country_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Geo extends \IAWP\Tables\Table
{
    public function id() : string
    {
        return 'geo';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('country', \__('Country', 'independent-analytics'), 'country', Countries::class, Country_Statistics::class);
        $groups[] = new Group('city', \__('City', 'independent-analytics'), 'city', Cities::class, City_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'continent', 'name' => \__('Continent', 'independent-analytics'), 'type' => 'string', 'is_concrete_column' => \true]), new Column(['id' => 'country', 'name' => \__('Country', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'is_concrete_column' => \true]), new Column(['id' => 'subdivision', 'name' => \__('Subdivision', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['country'], 'is_concrete_column' => \true]), new Column(['id' => 'city', 'name' => \__('City', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['country'], 'is_concrete_column' => \true]), new Column(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'average_session_duration', 'name' => \__('Session Duration', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'filter_placeholder' => 'Seconds']), new Column(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'type' => 'int']), new Column(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'visible' => \true, 'type' => 'int']), new Column(['id' => 'visitors_growth', 'name' => \__('Visitors Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'views_growth', 'name' => \__('Views Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'type' => 'int', 'requires_pro' => \true, 'aggregatable' => \true])];
        return \array_merge($columns, $this->get_woocommerce_columns(), $this->get_form_columns());
    }
}
