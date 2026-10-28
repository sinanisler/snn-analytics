<?php

namespace IAWP\Migrations;

use IAWP\Database;
use IAWP\Query;
/** @internal */
class Migration_31 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 31;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->drop_table_if_exists(Query::get_table_name(Query::FORMS)), $this->drop_table_if_exists(Query::get_table_name(Query::FORM_SUBMISSIONS)), $this->create_forms_table(), $this->create_form_submissions_table()];
    }
    private function create_forms_table() : string
    {
        $forms_table = Query::get_table_name(Query::FORMS);
        $character_set = Database::character_set();
        $collation = Database::collation();
        return "\n            CREATE TABLE IF NOT EXISTS {$forms_table} (\n                form_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                plugin_id BIGINT(20) UNSIGNED NOT NULL,\n                plugin_form_id BIGINT(20) UNSIGNED NOT NULL,\n                cached_form_title VARCHAR(64) NOT NULL,\n                PRIMARY KEY (form_id),\n                UNIQUE INDEX (plugin_id, plugin_form_id)\n            ) DEFAULT CHARACTER SET {$character_set} COLLATE {$collation};\n        ";
    }
    private function create_form_submissions_table() : string
    {
        $form_submissions_table = Query::get_table_name(Query::FORM_SUBMISSIONS);
        $character_set = Database::character_set();
        $collation = Database::collation();
        return "\n            CREATE TABLE IF NOT EXISTS {$form_submissions_table} (\n                form_submission_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                form_id BIGINT(20) UNSIGNED NOT NULL,\n                session_id BIGINT(20) UNSIGNED NOT NULL,\n                view_id BIGINT(20) UNSIGNED NOT NULL,\n                initial_view_id BIGINT(20) UNSIGNED NOT NULL,\n                created_at DATETIME NOT NULL,\n                PRIMARY KEY (form_submission_id)\n            )  DEFAULT CHARACTER SET {$character_set} COLLATE {$collation};\n        ";
    }
}
