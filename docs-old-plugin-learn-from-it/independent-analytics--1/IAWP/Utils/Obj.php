<?php

namespace IAWP\Utils;

/** @internal */
class Obj
{
    public static function empty_strings_to_null(object $obj)
    {
        $clone = clone $obj;
        foreach ($clone as $key => $value) {
            if ($value === '') {
                $clone->{$key} = null;
            }
        }
        return $clone;
    }
}
