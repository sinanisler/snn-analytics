<?php

namespace IAWP\Migrations;

use IAWP\Query;
/** @internal */
class Migration_34 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 34;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_index_for_cached_author_id()];
    }
    private function add_index_for_cached_author_id() : string
    {
        $resources_table = Query::get_table_name(Query::RESOURCES);
        return "\n            ALTER TABLE {$resources_table}\n                ADD INDEX(cached_author_id);\n        ";
    }
}
