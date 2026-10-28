<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_41 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 41;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_pmpro_to_orders_table()];
    }
    private function add_pmpro_to_orders_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::orders()} \n                ADD COLUMN pmpro_order_id BIGINT(20) UNSIGNED AFTER edd_order_status,\n                ADD COLUMN pmpro_order_status VARCHAR(64) AFTER pmpro_order_id,\n                ADD UNIQUE INDEX (pmpro_order_id);\n        ";
    }
}
