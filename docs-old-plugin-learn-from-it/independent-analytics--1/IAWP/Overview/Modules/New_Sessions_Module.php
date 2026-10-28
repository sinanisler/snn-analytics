<?php

namespace IAWP\Overview\Modules;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Number_Formatter;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class New_Sessions_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'new-sessions';
    }
    public function module_name() : string
    {
        return \__('New vs. Returning Sessions', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $date_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? null);
        $tables = Tables::class;
        // Find all session in range
        $session_in_range = Illuminate_Builder::new()->selectRaw('DISTINCT session_id')->from($tables::views())->whereBetween('viewed_at', [$date_range->iso_start(), $date_range->iso_end()]);
        $query = Illuminate_Builder::new()->selectRaw('CAST(SUM(IF(is_first_session = 1, 1, 0)) AS SIGNED) AS is_first_session')->selectRaw('CAST(SUM(IF(is_first_session = 0, 1, 0)) AS SIGNED) AS is_returning_session')->from($tables::sessions(), 'sessions')->joinSub($session_in_range, 'sessions_in_range', function (JoinClause $join) {
            $join->on('sessions_in_range.session_id', '=', 'sessions.session_id');
        });
        $row = $query->first();
        if ($row->is_first_session === null && $row->is_returning_session === null) {
            return [];
        }
        return [['label' => \__('New Sessions', 'independent-analytics'), 'unit' => \__('Sessions', 'independent-analytics'), 'value' => \intval($row->is_first_session), 'formatted_value' => Number_Formatter::integer($row->is_first_session)], ['label' => \__('Returning Sessions', 'independent-analytics'), 'unit' => \__('Sessions', 'independent-analytics'), 'value' => \intval($row->is_returning_session), 'formatted_value' => Number_Formatter::integer($row->is_returning_session)]];
    }
    protected function module_fields() : array
    {
        return ['date_range'];
    }
}
