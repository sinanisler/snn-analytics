<?php

namespace IAWP\Migrations;

use IAWP\Query;
/** @internal */
class Migration_33 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 33;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_chart_metrics_to_reports()];
    }
    private function add_chart_metrics_to_reports() : string
    {
        $reports_table = Query::get_table_name(Query::REPORTS);
        return "\n            ALTER TABLE {$reports_table}\n                ADD COLUMN primary_chart_metric_id varchar(255),\n                ADD COLUMN secondary_chart_metric_id varchar(255),\n                DROP COLUMN visible_datasets\n        ";
    }
}
