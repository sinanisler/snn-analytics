<?php

namespace IAWP\Migrations;

/** @internal */
class Migration_52 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 52;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->add_fluent_cart_to_orders_table()];
    }
    private function add_fluent_cart_to_orders_table() : string
    {
        return "\n            ALTER TABLE {$this->tables::orders()} \n                ADD COLUMN fluent_cart_order_id BIGINT(20) UNSIGNED AFTER pmpro_order_status,\n                ADD COLUMN fluent_cart_order_status VARCHAR(64) AFTER fluent_cart_order_id,\n                ADD UNIQUE INDEX (fluent_cart_order_id);\n        ";
    }
}
