<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class DeviceOperatingSystems implements OptionsPlugin
{
    public function get_options() : array
    {
        $records = Illuminate_Builder::new()->from(Tables::device_oss())->select('device_os_id', 'device_os')->get()->all();
        return \array_map(function ($record) {
            return new Option($record->device_os_id, $record->device_os);
        }, $records);
    }
}
