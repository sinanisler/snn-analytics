<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Query;
/** @internal */
class WooCommerce_Order
{
    private $order_id;
    private $status;
    private $total;
    private $total_refunded;
    private $total_refunds;
    private $is_discounted;
    /**
     * @param int $order_id WooCommerce order ID
     */
    public function __construct(int $order_id)
    {
        $order = wc_get_order($order_id);
        // Total based on order currency, not shop currency
        $total = \intval(\round($order->get_total() * 100));
        // Refund amount based on order currency, not shop currency
        $total_refunded = \intval(\round(\floatval($order->get_total_refunded()) * 100));
        [$total, $total_refunded] = $this->convert_to_base_currency($order, $total, $total_refunded);
        $this->order_id = $order_id;
        $this->status = $order->get_status();
        $this->total = $total;
        $this->total_refunded = $total_refunded;
        $this->total_refunds = \count($order->get_refunds());
        $this->is_discounted = $this->is_discounted_order($order);
    }
    public function insert() : void
    {
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        $orders_table = Query::get_table_name(Query::ORDERS);
        Illuminate_Builder::new()->from($orders_table)->insertOrIgnore(['is_included_in_analytics' => (new \IAWP\Ecommerce\WooCommerce_Status_Manager())->is_tracked_status($this->status), 'woocommerce_order_id' => $this->order_id, 'woocommerce_order_status' => $this->status, 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }
    public function update() : void
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        Illuminate_Builder::new()->from($orders_table)->where('woocommerce_order_id', '=', $this->order_id)->update(['is_included_in_analytics' => (new \IAWP\Ecommerce\WooCommerce_Status_Manager())->is_tracked_status($this->status), 'woocommerce_order_status' => $this->status, 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted]);
    }
    private function convert_to_base_currency($order, $total, $total_refunded)
    {
        $exchange_rate = $this->exchange_rate($order);
        if (\is_float($exchange_rate)) {
            // Exchange rate is from shop currency to order currency (divide)
            $total = \intval(\round($total / $exchange_rate));
            $total_refunded = \intval(\round($total_refunded / $exchange_rate));
        }
        return [$total, $total_refunded];
    }
    private function is_discounted_order($order) : bool
    {
        if ($order->get_total_discount() > 0) {
            return \true;
        }
        foreach ($order->get_items() as $item) {
            if ($item->get_product()->is_on_sale()) {
                return \true;
            }
        }
        return \false;
    }
    private function exchange_rate($order) : ?float
    {
        $rate_getters = [fn() => $this->aelia_exchange_rate($order), fn() => $this->wpml_exchange_rate($order), fn() => $this->curcy_exchange_rate($order), fn() => $this->pbc_exchange_rate($order), fn() => $this->yith_exchange_rate($order), fn() => $this->woopayments_exchange_rate($order), fn() => $this->yay_currency_exchange_rate($order)];
        foreach ($rate_getters as $rate_getter) {
            $exchange_rate = $rate_getter();
            if (\is_float($exchange_rate)) {
                return $exchange_rate;
            }
        }
        return null;
    }
    private function aelia_exchange_rate($order) : ?float
    {
        $exchange_rate = $order->get_meta('_base_currency_exchange_rate');
        if (!\is_numeric($exchange_rate)) {
            return null;
        }
        return 1 / \floatval($exchange_rate);
    }
    private function wpml_exchange_rate($order) : ?float
    {
        $currency_code = $order->get_currency();
        if (!\is_plugin_active('woocommerce-multilingual/wpml-woocommerce.php')) {
            return null;
        }
        $wcml_options = \get_option('_wcml_settings');
        if (!\is_array($wcml_options)) {
            return null;
        }
        if (!\array_key_exists('currency_options', $wcml_options)) {
            return null;
        }
        if (!\is_array($wcml_options['currency_options']) || !\array_key_exists($currency_code, $wcml_options['currency_options'])) {
            return null;
        }
        if (!\is_array($wcml_options['currency_options'][$currency_code]) || !\array_key_exists('rate', $wcml_options['currency_options'][$currency_code])) {
            return null;
        }
        $exchange_rate = \floatval($wcml_options['currency_options'][$currency_code]['rate']);
        // Was there an error parsing value as float?
        if ($exchange_rate === 0.0) {
            return null;
        }
        return $exchange_rate;
    }
    private function curcy_exchange_rate($order) : ?float
    {
        $rates = $order->get_meta('wmc_order_info');
        $currency = $order->get_currency();
        if (!\is_array($rates)) {
            return null;
        }
        $rate = $rates[$currency]['rate'] ?? null;
        if (!\is_numeric($rate)) {
            return null;
        }
        return (float) $rate;
    }
    private function pbc_exchange_rate($order) : ?float
    {
        $exchange_rate = $order->get_meta('_wcpbc_base_exchange_rate');
        if (!\is_numeric($exchange_rate)) {
            return null;
        }
        return 1 / \floatval($exchange_rate);
    }
    private function yith_exchange_rate($order) : ?float
    {
        $currency = $order->get_currency();
        $is_yith_order = !empty($order->get_meta('_yith_wcmcs_default_currency_on_creation'));
        if (!$is_yith_order) {
            return null;
        }
        try {
            $rates = \yith_wcmcs_get_currencies();
            if (!\array_key_exists($currency, $rates)) {
                return null;
            }
            return $rates[$currency]->get_rate('raw');
        } catch (\Throwable $e) {
            return null;
        }
    }
    private function woopayments_exchange_rate($order) : ?float
    {
        $rate = $order->get_meta('_wcpay_multi_currency_order_exchange_rate');
        if (!\is_numeric($rate)) {
            return null;
        }
        return (float) $rate;
    }
    private function yay_currency_exchange_rate($order) : ?float
    {
        $rate = $order->get_meta('yay_currency_order_rate');
        if (!\is_numeric($rate)) {
            return null;
        }
        return (float) $rate;
    }
    public static function register_hooks()
    {
        // Required for block checkout
        \add_action('woocommerce_store_api_checkout_order_processed', function ($order) {
            try {
                $woocommerce_order = new self($order->get_id());
                $woocommerce_order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a WooCommerce order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        // Required for shortcode checkout
        \add_action('woocommerce_checkout_order_created', function ($order) {
            try {
                $woocommerce_order = new self($order->get_id());
                $woocommerce_order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a WooCommerce order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        \add_action('woocommerce_order_status_changed', function ($order_id) {
            try {
                $woocommerce_order = new self($order_id);
                $woocommerce_order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a WooCommerce order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        // Captures a partial refund, something that woocommerce_order_status_changed will not do
        \add_action('woocommerce_order_refunded', function ($order_id) {
            try {
                $woocommerce_order = new self($order_id);
                $woocommerce_order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a WooCommerce order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
    }
}
