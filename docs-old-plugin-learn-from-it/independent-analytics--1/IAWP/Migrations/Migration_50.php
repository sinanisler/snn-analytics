<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_50 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 50;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_column_for_filtering_logic()];
    }
    private function add_column_for_filtering_logic() : string
    {
        return "\n            ALTER TABLE {$this->tables::reports()} ADD COLUMN filter_logic ENUM ('and', 'or') NOT NULL DEFAULT 'and' AFTER columns;\n        ";
    }
}
