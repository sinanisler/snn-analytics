<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class Countries implements OptionsPlugin
{
    public function get_options() : array
    {
        $records = Illuminate_Builder::new()->from(Tables::countries())->select('country_id', 'country')->get()->all();
        return \array_map(function ($record) {
            return new Option($record->country_id, $record->country);
        }, $records);
    }
}
