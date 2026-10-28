<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_51 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 51;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists($this->tables::links()), $this->create_links_table(), $this->populate_links_table(), $this->add_link_id_column_to_clicked_links_table(), $this->populate_link_id_column(), $this->remove_click_target_id_from_clicks_table(), $this->switch_primary_key_for_clicked_links_table()];
    }
    private function create_links_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::links()} (\n                id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                link_rule_id BIGINT(20) UNSIGNED,\n                click_target_id BIGINT(20) UNSIGNED,\n                PRIMARY KEY (id),\n                INDEX(link_rule_id),\n                INDEX(click_target_id),\n                UNIQUE INDEX(link_rule_id, click_target_id)\n            )  DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function populate_links_table() : string
    {
        return "\n            INSERT INTO {$this->tables::links()} (link_rule_id, click_target_id)\n            SELECT DISTINCT\n                clicked_links.link_rule_id,\n                clicks.click_target_id\n            FROM\n                {$this->tables::clicked_links()} AS clicked_links\n                JOIN {$this->tables::clicks()} AS clicks ON clicked_links.click_id = clicks.click_id;\n        ";
    }
    private function add_link_id_column_to_clicked_links_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::clicked_links()}\n            ADD COLUMN link_id BIGINT(20) UNSIGNED,\n            ADD INDEX (link_id);\n        ";
    }
    private function populate_link_id_column() : string
    {
        return "\n            UPDATE {$this->tables::clicked_links()} AS clicked_links\n            JOIN {$this->tables::clicks()} AS clicks ON clicked_links.click_id = clicks.click_id\n            JOIN {$this->tables::links()} AS links ON clicks.click_target_id = links.click_target_id AND clicked_links.link_rule_id = links.link_rule_id\n            SET\n                clicked_links.link_id = links.id;\n        ";
    }
    private function remove_click_target_id_from_clicks_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::clicks()} DROP COLUMN click_target_id;\n        ";
    }
    private function switch_primary_key_for_clicked_links_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::clicked_links()}\n            DROP PRIMARY KEY,\n            ADD PRIMARY KEY (click_id, link_id),\n            DROP COLUMN link_rule_id;\n        ";
    }
}
