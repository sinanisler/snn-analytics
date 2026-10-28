<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_39 extends \IAWP\Migrations\Step_Migration
{
    use \IAWP\Migrations\Creates_Reports;
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 39;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->increase_size_of_cached_url(), $this->increase_size_of_not_found_url()];
    }
    private function increase_size_of_cached_url() : string
    {
        return "\n            ALTER TABLE {$this->tables::resources()} MODIFY COLUMN cached_url VARCHAR(2083);\n        ";
    }
    private function increase_size_of_not_found_url() : string
    {
        return "\n            ALTER TABLE {$this->tables::resources()} MODIFY COLUMN not_found_url VARCHAR(2083);\n        ";
    }
}
