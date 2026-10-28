<?php

namespace IAWP\Ecommerce;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Tables;
/** @internal */
class PMPro_Order
{
    private $order_id;
    private $status;
    private $total;
    private $total_refunded;
    private $total_refunds;
    private $is_discounted;
    public function __construct(int $order_id)
    {
        $order = new \MemberOrder($order_id);
        $this->order_id = $order_id;
        $this->status = $order->status;
        $this->total = \intval(\round((float) $order->total * 100));
        $this->total_refunded = $order->status === 'refunded' ? $this->total : 0;
        $this->total_refunds = $order->status === 'refunded' ? 1 : 0;
        $this->is_discounted = \is_numeric($order->discount_code_id) && (int) $order->discount_code_id !== 0;
    }
    public function insert()
    {
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        Illuminate_Builder::new()->from(Tables::orders())->insertOrIgnore(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'pmpro_order_id' => $this->order_id, 'pmpro_order_status' => $this->status, 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }
    public function update() : void
    {
        Illuminate_Builder::new()->from(Tables::orders())->where('pmpro_order_id', '=', $this->order_id)->update(['is_included_in_analytics' => $this->is_included_in_analytics($this->status), 'pmpro_order_status' => $this->status, 'total' => $this->total, 'total_refunded' => $this->total_refunded, 'total_refunds' => $this->total_refunds, 'is_discounted' => $this->is_discounted]);
    }
    private function is_included_in_analytics(string $status) : bool
    {
        return \in_array($status, ['success', 'refunded']);
    }
    public static function register_hooks() : void
    {
        // Create a new order when a PMPro order is created
        \add_action('pmpro_added_order', function ($pmpro_order) {
            try {
                $order = new self((int) $pmpro_order->id);
                $order->insert();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Paid Memberships Pro order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
        // Calculating is_discounted doesn't seem possible at the time pmpro_added_order runs. This hooks
        // runs just after it but allows is_discounted to be correctly determined.
        \add_action('pmpro_discount_code_used', function ($discount_code_id, $user_id, $order_id) {
            try {
                $order = new self((int) $order_id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Paid Memberships Pro order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 3);
        // Update an existing order when a PMPro order is updated
        \add_action('pmpro_updated_order', function ($pmpro_order) {
            try {
                $order = new self((int) $pmpro_order->id);
                $order->update();
            } catch (\Throwable $e) {
                \error_log('Independent Analytics was unable to track the analytics for a Paid Memberships Pro order. Please report this error to Independent Analytics. The error message is below.');
                \error_log($e->getMessage());
            }
        }, 10, 1);
    }
}
