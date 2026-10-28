<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Tables;
/** @internal */
class EDD_Order
{
    private $order_id;
    private $status;
    private $total;
    private $total_refunded;
    private $total_refunds;
    private $is_discounted;
    public function __construct(int $order_id)
    {
        $order = \edd_get_order($order_id);
        $refunds = \edd_get_order_refunds($order_id);
        $total_refunded = 0;
        $total_refunds = 0;
        foreach ($refunds as $refund) {
            $total_refunds++;
            $total_refunded += \abs((float) $refund->total);
        }
        $this->order_id = $order_id;
        $this->status = $order->status;
        $this->total = \intval(\round((float) $order->total * 100));
        $this->total_refunded = \intval(\round($total_refunded * 100));
        $this->total_refunds = $total_refunds;
        $this->is_discounted = (float) $order->discount > 0;
    }
    public function insert()
    {
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        Illuminate_Builder::new()->from(Tables::orders())->insertOrIgnore(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'edd_order_id' => $this->order_id, 'edd_order_status' => $this->status, 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }
    public function update() : void
    {
        Illuminate_Builder::new()->from(Tables::orders())->where('edd_order_id', '=', $this->order_id)->update(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'edd_order_status' => $this->status, 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted]);
    }
    private function is_included_in_analytics(string $status) : bool
    {
        return \in_array($status, ['complete', 'refunded', 'partially_refunded']);
    }
    public static function register_hooks() : void
    {
        // While the EDD docs recommend it, we cannot use `edd_after_order_actions` as it runs in
        // a cron job 30 seconds after the order is completed. We need to run in the same request,
        // so we can determine which visitor make the purchase and attach the order correctly.
        \add_action('edd_complete_purchase', function ($order_id) {
            try {
                $order = new self($order_id);
                $order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a EDD order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
        // Track order status changes
        \add_action('edd_update_payment_status', function ($order_id) {
            try {
                $order = new self($order_id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a EDD order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
        // Track refunds to orders
        \add_action('edd_refund_order', function ($order_id) {
            try {
                $order = new self($order_id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a EDD order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
    }
}
