<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class DeviceTypes implements OptionsPlugin
{
    public function get_options() : array
    {
        $records = Illuminate_Builder::new()->from(Tables::device_types())->select('device_type_id', 'device_type')->get()->all();
        return \array_map(function ($record) {
            return new Option($record->device_type_id, $record->device_type);
        }, $records);
    }
}
