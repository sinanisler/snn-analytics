<?php

namespace IAWP\Statistics;

use IAWP\Date_Range\Date_Range;
use IAWP\Rows\Rows;
/** @internal */
class Journey_Statistics extends \IAWP\Statistics\Statistics
{
    public function total_number_of_rows() : ?int
    {
        return 0;
    }
    protected function make_statistic_instances() : array
    {
        return [];
    }
    protected function query(Date_Range $range, ?Rows $rows = null, bool $is_grouped_by_date_interval = \false)
    {
        return [];
    }
}
