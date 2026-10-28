<?php

namespace IAWP\Tables;

use IAWP\ColumnOptions\Options;
use IAWP\ColumnOptions\Plugins\ReferrerTypes;
use IAWP\Rows\Referrer_Types;
use IAWP\Rows\Referrers;
use IAWP\Statistics\Referrer_Statistics;
use IAWP\Statistics\Referrer_Type_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Referrers extends \IAWP\Tables\Table
{
    public function id() : string
    {
        return 'referrers';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('referrer', \__('Referrer', 'independent-analytics'), 'referrer', Referrers::class, Referrer_Statistics::class);
        $groups[] = new Group('referrer_type', \__('Referrer Type', 'independent-analytics'), 'referrer_type', Referrer_Types::class, Referrer_Type_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'referrer', 'name' => \__('Referrer', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['referrer_type'], 'is_concrete_column' => \true]), new Column(['id' => 'referrer_type', 'name' => \__('Referrer Type', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new ReferrerTypes()), 'is_concrete_column' => \true]), new Column(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'average_session_duration', 'name' => \__('Session Duration', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'filter_placeholder' => 'Seconds']), new Column(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'type' => 'int']), new Column(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'visible' => \true, 'type' => 'int']), new Column(['id' => 'visitors_growth', 'name' => \__('Visitors Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'views_growth', 'name' => \__('Views Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'type' => 'int', 'requires_pro' => \true, 'aggregatable' => \true])];
        return \array_merge($columns, $this->get_woocommerce_columns(), $this->get_form_columns());
    }
}
