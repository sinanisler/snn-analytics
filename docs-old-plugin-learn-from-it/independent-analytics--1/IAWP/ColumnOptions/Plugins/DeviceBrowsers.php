<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class DeviceBrowsers implements OptionsPlugin
{
    public function get_options() : array
    {
        $records = Illuminate_Builder::new()->from(Tables::device_browsers())->select('device_browser_id', 'device_browser')->get()->all();
        return \array_map(function ($record) {
            return new Option($record->device_browser_id, $record->device_browser);
        }, $records);
    }
}
