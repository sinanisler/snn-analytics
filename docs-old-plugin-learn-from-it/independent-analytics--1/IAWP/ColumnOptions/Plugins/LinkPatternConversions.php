<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class LinkPatternConversions implements OptionsPlugin
{
    public function get_options() : array
    {
        $any = new Option('is_not_null', \__('Any', 'independent-analytics'));
        $records = Illuminate_Builder::new()->from(Tables::link_rules())->select('link_rule_id', 'name')->get()->all();
        $link_pattern_options = \array_map(function ($record) {
            return new Option($record->link_rule_id, $record->name);
        }, $records);
        return [$any, ...$link_pattern_options];
    }
}
