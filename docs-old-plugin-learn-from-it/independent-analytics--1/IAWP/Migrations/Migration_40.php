<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_40 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 40;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_edd_to_orders_table()];
    }
    private function add_edd_to_orders_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::orders()} \n                ADD COLUMN edd_order_id BIGINT(20) UNSIGNED AFTER surecart_order_status,\n                ADD COLUMN edd_order_status VARCHAR(64) AFTER edd_order_id,\n                ADD UNIQUE INDEX (edd_order_id);\n        ";
    }
}
