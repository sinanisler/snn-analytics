<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class FormConversions implements OptionsPlugin
{
    public function get_options() : array
    {
        $any = new Option('is_not_null', \__('Any', 'independent-analytics'));
        $records = Illuminate_Builder::new()->from(Tables::forms())->select('form_id', 'cached_form_title')->get()->all();
        $form_options = \array_map(function ($record) {
            return new Option($record->form_id, $record->cached_form_title);
        }, $records);
        return [$any, ...$form_options];
    }
}
