<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class WooCommerce_Status_Manager
{
    private static $default_status_to_track = [
        'completed',
        'processing',
        'refunded',
        'shipped',
        'partial-shipped',
        'delivered',
        // Specific to WooCommerce Amazon Fulfillment plugin
        'sent-to-fba',
        'part-to-fba',
    ];
    public function __construct()
    {
    }
    /**
     * WooCommerce don't know if it wants statuses to be prefixed with `wc-` or not. It's a bit
     * of a mess. Instead of calling `wc_get_order_statuses` directly, this proxy function
     * should be used to normalize those values and strip the prefix off.
     *
     * @return array{id: string, name: string, is_tracked: bool}[]
     */
    public function get_statuses() : array
    {
        $prefixed_statuses = wc_get_order_statuses();
        $statuses = [];
        foreach ($prefixed_statuses as $key => $value) {
            if (Str::startsWith($key, 'wc-')) {
                $key = Str::replaceFirst('wc-', '', $key);
            }
            $statuses[] = ['id' => \strval($key), 'name' => \strval($value), 'is_tracked' => $this->is_tracked_status($key)];
        }
        return $statuses;
    }
    public function is_valid_status(string $status_id) : bool
    {
        $statuses = $this->get_statuses();
        foreach ($statuses as $status) {
            if ($status['id'] === $status_id) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Use the currently configured statuses to recalculate if a given order in `wp_orders`
     * should be included in the analytics or not.
     *
     * @return void
     */
    public function update_order_records_based_on_tracked_statuses() : void
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        Illuminate_Builder::new()->from($orders_table)->whereNotIn('woocommerce_order_status', $this->get_tracked_status_ids())->update(['is_included_in_analytics' => \false]);
        Illuminate_Builder::new()->from($orders_table)->whereIn('woocommerce_order_status', $this->get_tracked_status_ids())->update(['is_included_in_analytics' => \true]);
    }
    public function set_tracked_statuses(array $tracked_status_ids) : void
    {
        $filtered_statuses = \array_filter($tracked_status_ids, function ($status_id) {
            return $this->is_valid_status($status_id);
        });
        \update_option('iawp_tracked_woocommerce_status_ids', $filtered_statuses);
    }
    public function is_tracked_status(string $status_id) : bool
    {
        return \in_array($status_id, $this->get_tracked_status_ids());
    }
    public function reset_tracked_statuses() : void
    {
        \delete_option('iawp_tracked_woocommerce_status_ids');
    }
    /**
     * @return string[]
     */
    private function get_tracked_status_ids() : array
    {
        $status_ids = \IAWPSCOPED\iawp()->get_option('iawp_tracked_woocommerce_status_ids', []);
        $prefixed_statuses = [];
        if (!\is_array($status_ids) || \count($status_ids) === 0) {
            $status_ids = self::$default_status_to_track;
        }
        // These need to be prefixed, because you never know what you're going to get back from WooCommerce
        foreach ($status_ids as $status_id) {
            $prefixed_statuses[] = 'wc-' . $status_id;
        }
        return \array_merge($status_ids, $prefixed_statuses);
    }
}
