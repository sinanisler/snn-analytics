<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_42 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 42;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->update_click_target_protocols()];
    }
    private function update_click_target_protocols() : string
    {
        return "\n            ALTER TABLE {$this->tables::click_targets()} \n                MODIFY COLUMN protocol ENUM('mailto', 'tel', 'sms');\n        ";
    }
}
