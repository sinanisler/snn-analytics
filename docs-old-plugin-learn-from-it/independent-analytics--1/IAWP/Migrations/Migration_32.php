<?php

namespace IAWP\Migrations;

use IAWP\Query;
/** @internal */
class Migration_32 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 32;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_quick_stats_column_to_reports()];
    }
    private function add_quick_stats_column_to_reports() : string
    {
        $reports_table = Query::get_table_name(Query::REPORTS);
        return "\n            ALTER TABLE {$reports_table}\n                ADD COLUMN quick_stats TEXT AFTER group_name\n        ";
    }
}
