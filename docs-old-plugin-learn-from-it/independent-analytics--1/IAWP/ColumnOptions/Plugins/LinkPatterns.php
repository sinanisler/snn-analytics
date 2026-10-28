<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class LinkPatterns implements OptionsPlugin
{
    public function get_options() : array
    {
        $records = Illuminate_Builder::new()->from(Tables::link_rules())->select('link_rule_id', 'name')->get()->all();
        return \array_map(function ($record) {
            return new Option($record->link_rule_id, $record->name);
        }, $records);
    }
}
