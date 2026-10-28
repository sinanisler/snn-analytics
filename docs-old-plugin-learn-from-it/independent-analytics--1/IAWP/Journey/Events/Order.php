<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Currency;
use IAWP\Utils\Obj;
use IAWP\Utils\Timezone;
/** @internal */
class Order extends \IAWP\Journey\Events\Event
{
    private int $session_id;
    private string $created_at;
    private int $total;
    private int $total_refunded;
    private ?string $platform;
    private string $status;
    private string $order_id;
    private ?string $admin_url;
    public function __construct(object $record)
    {
        $this->session_id = $record->session_id;
        $this->created_at = $record->created_at;
        $this->total = $record->total;
        $this->total_refunded = $record->total_refunded;
        // The order of these method calls is important!
        $this->platform = $this->calculate_platform($record);
        $this->status = $this->calculate_status($record);
        $this->order_id = $this->calculate_order_id($record);
        $this->admin_url = $this->calculate_admin_url();
    }
    public function type() : string
    {
        return 'order';
    }
    public function label() : string
    {
        return \__('Order', 'independent-analytics');
    }
    public function created_at() : ?CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at)->timezone(Timezone::site_timezone());
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_render('journeys.timeline.order', ['event' => $this]);
    }
    public function total() : string
    {
        return Currency::format($this->total, \false);
    }
    public function total_refunded() : ?string
    {
        $total_refunded = $this->total_refunded;
        if ($total_refunded === 0) {
            return null;
        }
        return Currency::format($total_refunded, \false);
    }
    public function status() : string
    {
        return $this->status;
    }
    public function order_id() : string
    {
        return $this->order_id;
    }
    public function admin_url() : ?string
    {
        return $this->admin_url;
    }
    private function calculate_platform(object $record) : ?string
    {
        $platforms = ['woocommerce', 'surecart', 'edd', 'pmpro', 'fluent_cart'];
        foreach ($platforms as $platform) {
            $key = $platform . '_order_status';
            if (\is_string($record->{$key} ?? null)) {
                return $platform;
            }
        }
        return null;
    }
    private function calculate_status(object $record) : string
    {
        if ($this->platform === null) {
            return '';
        }
        $key = $this->platform . '_order_status';
        return $record->{$key};
    }
    private function calculate_order_id(object $record) : string
    {
        if ($this->platform === null) {
            return 0;
        }
        $key = $this->platform . '_order_id';
        return $record->{$key};
    }
    private function calculate_admin_url() : ?string
    {
        if ($this->platform === 'fluent_cart' && \IAWPSCOPED\iawp()->is_fluent_cart_support_enabled()) {
            $order = \FluentCart\App\Models\Order::find($this->order_id);
            if ($order === null) {
                return null;
            }
            return $order->getViewURL('admin');
        }
        if ($this->platform === 'woocommerce' && \IAWPSCOPED\iawp()->is_woocommerce_support_enabled()) {
            $order = wc_get_order($this->order_id);
            if ($order === null) {
                return null;
            }
            return $order->get_edit_order_url();
        }
        if ($this->platform === 'surecart' && \IAWPSCOPED\iawp()->is_surecart_support_enabled()) {
            $admin_url = \admin_url('admin.php');
            $args = ['page' => 'sc-orders', 'action' => 'edit', 'id' => $this->order_id];
            $url = \add_query_arg($args, $admin_url);
            return $url;
        }
        if ($this->platform === 'edd' && \IAWPSCOPED\iawp()->is_edd_support_enabled()) {
            $admin_url = \admin_url('edit.php');
            $args = ['page' => 'edd-payment-history', 'post_type' => 'download', 'view' => 'view-order-details', 'id' => $this->order_id];
            $url = \add_query_arg($args, $admin_url);
            return $url;
        }
        if ($this->platform === 'pmpro' && \IAWPSCOPED\iawp()->is_pmpro_support_enabled()) {
            $admin_url = \admin_url('admin.php');
            $args = ['page' => 'pmpro-orders', 'id' => $this->order_id];
            $url = \add_query_arg($args, $admin_url);
            return $url;
        }
        return null;
    }
    public static function from_session(int $session_id) : array
    {
        $query = Illuminate_Builder::new()->select(['sessions.session_id', 'orders.*'])->from(Tables::sessions(), 'sessions')->join(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->join(Tables::orders() . ' AS orders', 'views.id', '=', 'orders.view_id')->where('sessions.session_id', '=', $session_id);
        $records = $query->get()->all();
        return \array_map(function ($record) {
            return new \IAWP\Journey\Events\Order(Obj::empty_strings_to_null($record));
        }, $records);
    }
}
