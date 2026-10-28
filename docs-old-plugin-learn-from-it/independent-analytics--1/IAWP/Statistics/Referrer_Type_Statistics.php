<?php

namespace IAWP\Statistics;

/** @internal */
class Referrer_Type_Statistics extends \IAWP\Statistics\Statistics
{
    protected function required_column() : ?string
    {
        return 'referrers.referrer_type_id';
    }
}
