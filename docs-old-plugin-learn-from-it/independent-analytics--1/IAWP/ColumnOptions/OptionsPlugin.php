<?php

namespace IAWP\ColumnOptions;

/** @internal */
interface OptionsPlugin
{
    public function get_options() : array;
}
