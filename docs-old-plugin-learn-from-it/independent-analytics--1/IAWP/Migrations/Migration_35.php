<?php

namespace IAWP\Migrations;

use IAWP\Database;
use IAWP\Query;
/** @internal */
class Migration_35 extends \IAWP\Migrations\Step_Migration
{
    /**
     * @return int
     */
    protected function database_version() : int
    {
        return 35;
    }
    /**
     * @return array
     */
    protected function queries() : array
    {
        return [$this->maybe_drop_order_table(), $this->create_orders_table(), $this->populate_orders_table()];
    }
    private function maybe_drop_order_table() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            DROP TABLE IF EXISTS {$orders_table}\n        ";
    }
    private function create_orders_table() : string
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        $character_set = Database::character_set();
        $collation = Database::collation();
        return "\n            CREATE TABLE {$orders_table} (\n                order_id BIGINT(20) UNSIGNED AUTO_INCREMENT,\n                is_included_in_analytics BOOLEAN NOT NULL, \n                \n                woocommerce_order_id BIGINT(20) UNSIGNED,\n                woocommerce_order_status VARCHAR(64),\n                surecart_order_id VARCHAR(36),\n                surecart_order_status VARCHAR(64),\n                \n                view_id BIGINT(20) UNSIGNED NOT NULL,\n                initial_view_id BIGINT(20) UNSIGNED NOT NULL,\n                \n                total INT NOT NULL,\n                total_refunded INT NOT NULL,\n                total_refunds SMALLINT NOT NULL,\n\n                is_discounted BOOLEAN NOT NULL, \n                \n                created_at DATETIME NOT NULL,\n                \n                PRIMARY KEY (order_id),\n                INDEX(view_id),\n                INDEX(initial_view_id),\n                INDEX(created_at)\n            )  DEFAULT CHARACTER SET {$character_set} COLLATE {$collation};\n        ";
    }
    private function populate_orders_table() : string
    {
        $wc_orders_table = Query::get_table_name(Query::WC_ORDERS);
        $orders_table = Query::get_table_name(Query::ORDERS);
        return "\n            INSERT INTO {$orders_table} (\n                is_included_in_analytics,\n                woocommerce_order_id,\n                woocommerce_order_status,\n                surecart_order_id,\n                surecart_order_status,\n                view_id,\n                initial_view_id,\n                total,\n                total_refunded,\n                total_refunds,\n                is_discounted,\n                created_at\n            )\n            SELECT\n                IF(status IN('wc-completed', 'completed', 'wc-processing', 'processing', 'wc-refunded', 'refunded', 'wc-shipped', 'shipped', 'wc-partial-shipped', 'partial-shipped', 'wc-delivered', 'delivered', 'wc-sent-to-fba', 'sent-to-fba', 'wc-part-to-fba', 'part-to-fba'), TRUE, FALSE) AS is_included_in_analytics,\n                wc.order_id AS woocommerce_order_id,\n                wc.status AS woocommerce_order_status,\n                NULL AS surecart_order_id,\n                NULL AS surecart_order_status,\n                wc.view_id AS view_id,\n                wc.initial_view_id AS initial_view_id,\n                ROUND(wc.total * 100) AS total,\n                ROUND(wc.total_refunded * 100) AS total_refunded,\n                wc.total_refunds AS total_refunds,\n                FALSE AS is_discounted,\n                wc.created_at AS created_at\n            FROM {$wc_orders_table} AS wc\n        ";
    }
}
