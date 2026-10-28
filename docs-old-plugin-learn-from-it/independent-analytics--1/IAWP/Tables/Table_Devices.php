<?php

namespace IAWP\Tables;

use IAWP\ColumnOptions\Options;
use IAWP\ColumnOptions\Plugins\DeviceBrowsers;
use IAWP\ColumnOptions\Plugins\DeviceOperatingSystems;
use IAWP\ColumnOptions\Plugins\DeviceTypes;
use IAWP\Rows\Device_Browsers;
use IAWP\Rows\Device_OSS;
use IAWP\Rows\Device_Types;
use IAWP\Statistics\Device_Browser_Statistics;
use IAWP\Statistics\Device_OS_Statistics;
use IAWP\Statistics\Device_Type_Statistics;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Groups\Group;
use IAWP\Tables\Groups\Groups;
/** @internal */
class Table_Devices extends \IAWP\Tables\Table
{
    public function id() : string
    {
        return 'devices';
    }
    protected function groups() : Groups
    {
        $groups = [];
        $groups[] = new Group('device_type', \__('Device Type', 'independent-analytics'), 'device_type', Device_Types::class, Device_Type_Statistics::class);
        $groups[] = new Group('os', \__('OS', 'independent-analytics'), 'os', Device_OSS::class, Device_OS_Statistics::class);
        $groups[] = new Group('browser', \__('Browser', 'independent-analytics'), 'browser', Device_Browsers::class, Device_Browser_Statistics::class);
        return new Groups($groups);
    }
    protected function local_columns() : array
    {
        $columns = [new Column(['id' => 'device_type', 'name' => \__('Type', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new DeviceTypes()), 'separate_filter_column' => 'sessions.device_type_id', 'unavailable_for' => ['browser', 'os'], 'is_concrete_column' => \true]), new Column(['id' => 'os', 'name' => \__('Operating System', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new DeviceOperatingSystems()), 'separate_filter_column' => 'sessions.device_os_id', 'unavailable_for' => ['device_type', 'browser'], 'is_concrete_column' => \true]), new Column(['id' => 'browser', 'name' => \__('Browser', 'independent-analytics'), 'visible' => \true, 'type' => 'select', 'options' => new Options(new DeviceBrowsers()), 'separate_filter_column' => 'sessions.device_browser_id', 'unavailable_for' => ['device_type', 'os'], 'is_concrete_column' => \true]), new Column(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'type' => 'int', 'aggregatable' => \true]), new Column(['id' => 'average_session_duration', 'name' => \__('Session Duration', 'independent-analytics'), 'visible' => \true, 'type' => 'int', 'filter_placeholder' => 'Seconds']), new Column(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'type' => 'int']), new Column(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'visible' => \true, 'type' => 'int']), new Column(['id' => 'visitors_growth', 'name' => \__('Visitors Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'views_growth', 'name' => \__('Views Growth', 'independent-analytics'), 'type' => 'int', 'exportable' => \false]), new Column(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'type' => 'int', 'requires_pro' => \true, 'aggregatable' => \true])];
        return \array_merge($columns, $this->get_woocommerce_columns(), $this->get_form_columns());
    }
}
