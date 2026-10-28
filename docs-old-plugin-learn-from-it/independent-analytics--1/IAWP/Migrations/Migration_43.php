<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_43 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 43;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_new_visitor_column(), $this->populate_new_visitor_column()];
    }
    private function add_new_visitor_column() : string
    {
        return "\n            ALTER TABLE {$this->tables::sessions()} \n                ADD COLUMN is_first_session BOOLEAN;\n        ";
    }
    private function populate_new_visitor_column() : string
    {
        return "\n            UPDATE {$this->tables::sessions()} AS sessions\n            JOIN (\n                SELECT\n                    visitor_id,\n                    MIN(session_id) AS first_session_id\n                FROM {$this->tables::sessions()}\n                GROUP BY visitor_id\n            ) AS first_session\n            ON sessions.visitor_id = first_session.visitor_id\n            SET sessions.is_first_session = IF(sessions.session_id = first_session.first_session_id, 1, 0)\n        ";
    }
}
