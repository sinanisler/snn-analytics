<?php

namespace IAWP\Migrations;

use IAWP\Database;
/** @internal */
class Migration_48 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 48;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists($this->tables::utm_mediums()), $this->create_utm_mediums_table(), $this->populate_utm_mediums_table(), $this->add_utm_medium_id(), $this->populate_utm_medium_id_column(), $this->modify_utm_medium_id_column(), $this->drop_original_landing_page_title_column(), $this->index_new_column()];
    }
    private function create_utm_mediums_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::utm_mediums()} (\n                id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                utm_medium VARCHAR(512) NOT NULL,\n                PRIMARY KEY (id)\n            )  DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function populate_utm_mediums_table() : string
    {
        return "\n            INSERT INTO {$this->tables::utm_mediums()} (utm_medium)\n                SELECT DISTINCT LEFT(utm_medium, 512)\n                FROM {$this->tables::campaigns()} WHERE utm_medium IS NOT NULL\n        ";
    }
    private function add_utm_medium_id() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} ADD COLUMN utm_medium_id BIGINT(20) UNSIGNED;\n        ";
    }
    private function populate_utm_medium_id_column() : string
    {
        $old_collation = Database::column_collation_for($this->tables::campaigns(), 'utm_medium');
        $current_collation = $this->collation();
        $collation_statement = $this->get_collation_statement($current_collation, $old_collation);
        return "\n            UPDATE\n              {$this->tables::campaigns()} AS campaigns\n              JOIN {$this->tables::utm_mediums()} AS mediums ON  campaigns.utm_medium = mediums.utm_medium {$collation_statement}\n            SET\n              campaigns.utm_medium_id = mediums.id\n        ";
    }
    private function modify_utm_medium_id_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} MODIFY COLUMN utm_medium_id BIGINT(20) UNSIGNED NOT NULL;\n        ";
    }
    private function drop_original_landing_page_title_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} DROP COLUMN utm_medium;\n        ";
    }
    private function index_new_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::campaigns()} ADD INDEX (utm_medium_id);\n        ";
    }
}
