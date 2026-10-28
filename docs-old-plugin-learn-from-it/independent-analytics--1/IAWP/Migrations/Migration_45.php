<?php

namespace IAWP\Migrations;

use IAWP\Database;
/** @internal */
class Migration_45 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 45;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists($this->tables::referrer_types()), $this->create_referrer_types_table(), $this->populate_referrer_types_table(), $this->add_referrer_type_id_column(), $this->populate_referrer_type_id_column(), $this->modify_referrer_type_id_column(), $this->drop_original_referrer_type_column(), $this->index_new_column()];
    }
    private function create_referrer_types_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::referrer_types()} (\n                id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                referrer_type ENUM('Ad','Direct','Referrer','Search','Social', 'AI') NOT NULL,\n                PRIMARY KEY (id),\n                UNIQUE INDEX (referrer_type)\n            )  DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function populate_referrer_types_table() : string
    {
        return "\n            INSERT INTO {$this->tables::referrer_types()} (referrer_type)\n                SELECT DISTINCT type\n                FROM {$this->tables::referrers()} WHERE type IS NOT NULL\n        ";
    }
    private function add_referrer_type_id_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::referrers()} ADD COLUMN referrer_type_id BIGINT(20) UNSIGNED;\n        ";
    }
    private function populate_referrer_type_id_column() : string
    {
        $old_collation = Database::column_collation_for($this->tables::referrers(), 'type');
        $current_collation = $this->collation();
        $collation_statement = $this->get_collation_statement($current_collation, $old_collation);
        return "\n            UPDATE\n              {$this->tables::referrers()} AS referrers\n              JOIN {$this->tables::referrer_types()} AS referrer_types ON referrers.type = referrer_types.referrer_type {$collation_statement}\n            SET\n              referrers.referrer_type_id = referrer_types.id\n        ";
    }
    private function modify_referrer_type_id_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::referrers()} MODIFY COLUMN referrer_type_id BIGINT(20) UNSIGNED NOT NULL;\n        ";
    }
    private function drop_original_referrer_type_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::referrers()} DROP COLUMN type;\n        ";
    }
    private function index_new_column() : string
    {
        return "\n           ALTER TABLE {$this->tables::referrers()} ADD INDEX (referrer_type_id);\n        ";
    }
}
