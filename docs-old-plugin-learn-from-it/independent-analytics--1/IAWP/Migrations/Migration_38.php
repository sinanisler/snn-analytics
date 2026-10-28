<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_38 extends \IAWP\Migrations\Step_Migration
{
    use \IAWP\Migrations\Creates_Reports;
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 38;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists($this->tables::clicks()), $this->create_clicks_table(), $this->drop_table_if_exists($this->tables::click_targets()), $this->create_click_targets_table(), $this->drop_table_if_exists($this->tables::clicked_links()), $this->create_clicked_links_table(), $this->drop_table_if_exists($this->tables::link_rules()), $this->create_link_rules_table(), $this->add_initial_rules(), $this->create_pdf_report(), $this->create_zip_report(), $this->create_email_report(), $this->create_phone_number_report()];
    }
    private function create_clicks_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::clicks()} (\n                click_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                view_id BIGINT(20) UNSIGNED,\n                click_target_id BIGINT(20) UNSIGNED,\n                created_at DATETIME NOT NULL,\n                PRIMARY KEY (click_id),\n                INDEX (view_id),\n                INDEX (click_target_id)\n            )  DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        \n        ";
    }
    private function create_click_targets_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::click_targets()} (\n                click_target_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                target VARCHAR(2083),\n                protocol ENUM('mailto', 'tel'),\n                PRIMARY KEY (click_target_id)\n            ) DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function create_clicked_links_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::clicked_links()} (\n                click_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                link_rule_id BIGINT(20) UNSIGNED,\n                PRIMARY KEY (click_id, link_rule_id)\n            ) DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        \n        ";
    }
    private function create_link_rules_table() : string
    {
        return "\n            CREATE TABLE {$this->tables::link_rules()} (\n                link_rule_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                name VARCHAR(255) NOT NULL,\n                type VARCHAR(255) NOT NULL,\n                value VARCHAR(255) NOT NULL,\n                is_active BOOLEAN NOT NULL DEFAULT TRUE,\n                position INT UNSIGNED DEFAULT 0,\n                created_at DATETIME NOT NULL,\n                PRIMARY KEY (link_rule_id)\n            ) DEFAULT CHARACTER SET {$this->character_set()} COLLATE {$this->collation()};\n        ";
    }
    private function add_initial_rules() : string
    {
        global $wpdb;
        $created_at = (new \DateTime())->format('Y-m-d H:i:s');
        return $wpdb->prepare("\n            INSERT INTO {$this->tables::link_rules()}\n                (link_rule_id, name, type, value, position, created_at)\n            VALUES\n                (1, 'PDF', 'extension', 'pdf', 0, %s),\n                (2, 'Zip', 'extension', 'zip', 1, %s),\n                (3, 'Email', 'protocol', 'mailto', 2, %s),\n                (4, 'Phone number', 'protocol', 'tel', 3, %s);\n        ", $created_at, $created_at, $created_at, $created_at);
    }
    private function create_pdf_report() : string
    {
        return $this->build_report_insert_query(['name' => \esc_html__('PDFs', 'independent-analytics'), 'type' => 'clicks', 'user_created_report' => 0, 'sort_column' => 'link_clicks', 'sort_direction' => 'desc', 'filters' => [['inclusion' => 'include', 'column' => 'link_name', 'operator' => 'is', 'operand' => '1']]]);
    }
    private function create_zip_report() : string
    {
        return $this->build_report_insert_query(['name' => \esc_html__('Zips', 'independent-analytics'), 'type' => 'clicks', 'user_created_report' => 0, 'sort_column' => 'link_clicks', 'sort_direction' => 'desc', 'filters' => [['inclusion' => 'include', 'column' => 'link_name', 'operator' => 'is', 'operand' => '2']]]);
    }
    private function create_email_report() : string
    {
        return $this->build_report_insert_query(['name' => \esc_html__('Emails', 'independent-analytics'), 'type' => 'clicks', 'user_created_report' => 0, 'sort_column' => 'link_clicks', 'sort_direction' => 'desc', 'filters' => [['inclusion' => 'include', 'column' => 'link_name', 'operator' => 'is', 'operand' => '3']]]);
    }
    private function create_phone_number_report() : string
    {
        return $this->build_report_insert_query(['name' => \esc_html__('Phone numbers', 'independent-analytics'), 'type' => 'clicks', 'user_created_report' => 0, 'sort_column' => 'link_clicks', 'sort_direction' => 'desc', 'filters' => [['inclusion' => 'include', 'column' => 'link_name', 'operator' => 'is', 'operand' => '4']]]);
    }
}
