<?php

namespace IAWP\Migrations;

use IAWP\Query;
/** @internal */
class Migration_37 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 37;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->remove_duplicate_woocommerce_orders(), $this->remove_duplicate_surecart_orders(), $this->add_unique_index_for_woocommerce_order_id(), $this->add_unique_index_for_surecart_order_id()];
    }
    private function remove_duplicate_woocommerce_orders() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            DELETE orders\n            FROM\n              {$orders_table} orders\n              JOIN (\n                SELECT\n                  woocommerce_order_id,\n                  MIN(order_id) AS first_order_id\n                FROM\n                  {$orders_table}\n                GROUP BY\n                  woocommerce_order_id\n              ) first_orders ON orders.woocommerce_order_id = first_orders.woocommerce_order_id\n              AND orders.order_id > first_orders.first_order_id; \n        ";
    }
    private function remove_duplicate_surecart_orders() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            DELETE orders\n            FROM\n              {$orders_table} orders\n              JOIN (\n                SELECT\n                  surecart_order_id,\n                  MIN(order_id) AS first_order_id\n                FROM\n                  {$orders_table}\n                GROUP BY\n                  surecart_order_id\n              ) first_orders ON orders.surecart_order_id = first_orders.surecart_order_id\n              AND orders.order_id > first_orders.first_order_id;\n        ";
    }
    private function add_unique_index_for_woocommerce_order_id() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            CREATE UNIQUE INDEX orders_woocommerce_order_id_index ON {$orders_table} (woocommerce_order_id)\n        ";
    }
    private function add_unique_index_for_surecart_order_id() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            CREATE UNIQUE INDEX orders_surecart_order_id_index ON {$orders_table} (surecart_order_id)\n        ";
    }
}
