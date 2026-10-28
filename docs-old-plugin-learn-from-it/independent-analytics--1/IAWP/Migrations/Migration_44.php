<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_44 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 44;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->increase_length_of_campaign_landing_page_titles(), $this->add_ai_as_referrer_type()];
    }
    private function increase_length_of_campaign_landing_page_titles() : string
    {
        return "\n            ALTER TABLE {$this->tables::campaigns()} MODIFY landing_page_title VARCHAR(2048)\n        ";
    }
    private function add_ai_as_referrer_type()
    {
        return "\n           ALTER TABLE {$this->tables::referrers()} MODIFY COLUMN type ENUM ('Ad','Direct','Referrer','Search','Social', 'AI') NOT NULL;\n        ";
    }
}
