<?php

namespace IAWP\Tables;

use IAWP\Rows\Campaign_Landing_Pages;
use IAWP\Rows\Campaign_UTM_Campaigns;
use IAWP\Rows\Campaign_UTM_Mediums;
use IAWP\Rows\Campaign_UTM_Sources;
use IAWP\Rows\Campaigns;
use IAWP\Statistics\Campaign_Landing_Page_Statistics;
use IAWP\Statistics\Campaign_Statistics;
use IAWP\Statistics\Campaign_UTM_Campaign_Statistics;
use IAWP\Statistics\Campaign_UTM_Medium_Statistics;
use IAWP\Statistics\Campaign_UTM_Source_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Campaigns extends \IAWP\Tables\Table
{
    public function id() : string
    {
        return 'campaigns';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('campaign', \__('Unique', 'independent-analytics'), 'title', Campaigns::class, Campaign_Statistics::class);
        $groups[] = new Group('landing_page', \__('Landing Page', 'independent-analytics'), 'title', Campaign_Landing_Pages::class, Campaign_Landing_Page_Statistics::class);
        $groups[] = new Group('utm_source', \__('Source', 'independent-analytics'), 'utm_source', Campaign_UTM_Sources::class, Campaign_UTM_Source_Statistics::class);
        $groups[] = new Group('utm_medium', \__('Medium', 'independent-analytics'), 'utm_medium', Campaign_UTM_Mediums::class, Campaign_UTM_Medium_Statistics::class);
        $groups[] = new Group('utm_campaign', \__('Campaign', 'independent-analytics'), 'utm_campaign', Campaign_UTM_Campaigns::class, Campaign_UTM_Campaign_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'title', 'name' => \__('Landing Page', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['utm_source', 'utm_medium', 'utm_campaign'], 'is_concrete_column' => \true]), new Column(['id' => 'utm_source', 'name' => \__('Source', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['landing_page', 'utm_medium', 'utm_campaign'], 'is_concrete_column' => \true]), new Column(['id' => 'utm_medium', 'name' => \__('Medium', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['landing_page', 'utm_source', 'utm_campaign'], 'is_concrete_column' => \true]), new Column(['id' => 'utm_campaign', 'name' => \__('Campaign', 'independent-analytics'), 'visible' => \true, 'type' => 'string', 'unavailable_for' => ['landing_page', 'utm_source', 'utm_medium'], 'is_concrete_column' => \true]), new Column(['id' => 'utm_term', 'name' => \__('Term', 'independent-analytics'), 'type' => 'string', 'is_nullable' => \true, 'unavailable_for' => ['landing_page', 'utm_source', 'utm_medium', 'utm_campaign'], 'is_concrete_column' => \true]), new Column(['id' => 'utm_content', 'name' => \__('Content', 'independent-analytics'), 'type' => 'string', 'is_nullable' => \true, 'unavailable_for' => ['landing_page', 'utm_source', 'utm_medium', 'utm_campaign'], 'is_concrete_column' => \true]), new Column(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'average_session_duration', 'name' => \__('Session Duration', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'filter_placeholder' => 'Seconds']), new Column(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'type' => 'int']), new Column(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'visible' => \true, 'type' => 'int']), new Column(['id' => 'visitors_growth', 'name' => \__('Visitors Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'views_growth', 'name' => \__('Views Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'type' => 'int', 'requires_pro' => \true, 'aggregatable' => \true])];
        return \array_merge($columns, $this->get_woocommerce_columns(), $this->get_form_columns());
    }
}
