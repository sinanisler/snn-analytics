<?php

namespace IAWP\Migrations;

use IAWP\Database;
/** @internal */
class Migration_46 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 46;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists($this->tables::landing_pages()), $this->create_landing_pages_table(), $this->populate_landing_pages_table(), $this->add_landing_page_id_column(), $this->populate_landing_page_id_column(), $this->modify_landing_page_id_column(), $this->drop_original_landing_page_title_column(), $this->index_new_column()];
    }
    private function create_landing_pages_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::landing_pages()} (\n                id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                title VARCHAR(512) NOT NULL,\n                PRIMARY KEY (id)\n            )  DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function populate_landing_pages_table() : string
    {
        return "\n            INSERT INTO {$this->tables::landing_pages()} (title)\n                SELECT DISTINCT LEFT(landing_page_title, 512)\n                FROM {$this->tables::campaigns()} WHERE landing_page_title IS NOT NULL\n        ";
    }
    private function add_landing_page_id_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} ADD COLUMN landing_page_id BIGINT(20) UNSIGNED;\n        ";
    }
    private function populate_landing_page_id_column() : string
    {
        $old_collation = Database::column_collation_for($this->tables::campaigns(), 'landing_page_title');
        $current_collation = $this->collation();
        $collation_statement = $this->get_collation_statement($current_collation, $old_collation);
        return "\n            UPDATE\n              {$this->tables::campaigns()} AS campaigns\n              JOIN {$this->tables::landing_pages()} AS landing_pages ON  campaigns.landing_page_title = landing_pages.title {$collation_statement}\n            SET\n              campaigns.landing_page_id = landing_pages.id\n        ";
    }
    private function modify_landing_page_id_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} MODIFY COLUMN landing_page_id BIGINT(20) UNSIGNED NOT NULL;\n        ";
    }
    private function drop_original_landing_page_title_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} DROP COLUMN landing_page_title;\n        ";
    }
    private function index_new_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} ADD INDEX (landing_page_id);\n        ";
    }
}
