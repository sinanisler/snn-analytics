<?php

namespace IAWP\Migrations;

use IAWP\Query;
/** @internal */
class Migration_36 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 36;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->increase_size_of_virtual_page_id()];
    }
    private function increase_size_of_virtual_page_id() : string
    {
        $resources_table = Query::get_table_name(Query::RESOURCES);
        return "\n            ALTER TABLE {$resources_table} MODIFY virtual_page_id VARCHAR(64)\n        ";
    }
}
