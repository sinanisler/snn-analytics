<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Tables;
/** @internal */
class Fluent_Cart_Order
{
    private $order_id;
    private $status;
    private $total;
    private $total_refunded;
    private $total_refunds;
    private $is_discounted;
    public function __construct(int $order_id)
    {
        $order = \FluentCart\App\Models\Order::find($order_id);
        $this->order_id = $order_id;
        $this->status = $order->payment_status;
        $this->total = (int) $order->total_amount;
        $this->total_refunded = (int) $order->getTotalRefundAmount();
        $this->total_refunds = (int) $order->transactions()->where('transaction_type', 'refund')->count();
        $this->is_discounted = $order->manual_discount_total > 0 || $order->coupon_discount_total > 0;
    }
    public function insert()
    {
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        Illuminate_Builder::new()->from(Tables::orders())->insertOrIgnore(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'fluent_cart_order_id' => $this->order_id, 'fluent_cart_order_status' => $this->status, 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }
    public function update() : void
    {
        Illuminate_Builder::new()->from(Tables::orders())->where('fluent_cart_order_id', '=', $this->order_id)->update(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'fluent_cart_order_status' => $this->status, 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted]);
    }
    private function is_included_in_analytics(string $status) : bool
    {
        return \in_array($status, ['paid', 'refunded', 'partially_refunded']);
    }
    public static function register_hooks() : void
    {
        // Track orders
        \add_action('fluent_cart/order_created', function ($data) {
            try {
                $id = $data['order']->id;
                $order = new self($id);
                $order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Fluent Cart order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        // Track order status changes
        \add_action('fluent_cart/order_status_changed', function ($data) {
            try {
                $id = $data['order']->id;
                $order = new self($id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Fluent Cart order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        // Track order refunds
        \add_action('fluent_cart/order_fully_refunded', function ($data) {
            try {
                $id = $data['order']->id;
                $order = new self($id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Fluent Cart order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
        \add_action('fluent_cart/order_partially_refunded', function ($data) {
            try {
                $id = $data['order']->id;
                $order = new self($id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Fluent Cart order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        });
    }
}
