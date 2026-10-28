<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
/** @internal */
abstract class Event
{
    public abstract function type() : string;
    public abstract function label() : string;
    public abstract function created_at() : ?CarbonImmutable;
    public abstract function html() : string;
    public function created_at_for_humans() : string
    {
        $created_at = $this->created_at();
        if ($created_at === null) {
            return '';
        }
        $format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
        return $created_at->format($format);
    }
}
