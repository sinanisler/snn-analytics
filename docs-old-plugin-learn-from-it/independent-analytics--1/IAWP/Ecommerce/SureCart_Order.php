<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Query;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class SureCart_Order
{
    private $order_id;
    private $status;
    private $total;
    private $total_refunded;
    private $total_refunds;
    private $is_discounted;
    /**
     * @param string $order_id SureCart order ID
     */
    public function __construct(string $order_id)
    {
        if (!\class_exists('\\SureCart\\Models\\Order') || !\class_exists('\\SureCart\\Models\\Refund')) {
            return;
        }
        $order = \SureCart\Models\Order::with(['checkout', 'checkout.charges'])->find($order_id);
        $this->order_id = $order_id;
        $this->status = $order->checkout->status;
        $this->total = $order->checkout->total_amount;
        $this->total_refunded = \absint($order->checkout->refunded_amount);
        $this->total_refunds = 0;
        if ($this->total_refunded > 0) {
            $refunds = \SureCart\Models\Refund::where(['charge_ids' => (new Collection($order->checkout->charges->data))->pluck('id')->toArray()])->get();
            $this->total_refunds = \count($refunds);
        }
        $this->is_discounted = \absint($order->checkout->discount_amount) > 0 || \absint($order->checkout->total_savings_amount) > 0;
    }
    public function insert() : void
    {
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        $orders_table = Query::get_table_name(Query::ORDERS);
        Illuminate_Builder::new()->from($orders_table)->insertOrIgnore(['is_included_in_analytics' => $this->status === 'paid', 'surecart_order_id' => $this->order_id, 'surecart_order_status' => $this->status, 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }
    public function update() : void
    {
        $orders_table = Query::get_table_name(Query::ORDERS);
        Illuminate_Builder::new()->from($orders_table)->where('surecart_order_id', '=', $this->order_id)->update(['is_included_in_analytics' => $this->status === 'paid', 'surecart_order_status' => $this->status, 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted]);
    }
    public static function register_hooks()
    {
        \add_action('surecart/purchase_created', function ($purchase) {
            try {
                $surecart_order = new self($purchase->getAttribute('initial_order'));
                $surecart_order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a SureCart order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
    }
    public static function update_order_using_charge_id(string $charge_id) : void
    {
        if (!\class_exists('\\SureCart\\Models\\Charge')) {
            return;
        }
        $charge = \SureCart\Models\Charge::with(['checkout'])->find($charge_id);
        $surecart_order = new self($charge->checkout->order);
        $surecart_order->update();
    }
    public static function update_order_using_order_id(string $order_id) : void
    {
        $surecart_order = new self($order_id);
        $surecart_order->update();
    }
}
