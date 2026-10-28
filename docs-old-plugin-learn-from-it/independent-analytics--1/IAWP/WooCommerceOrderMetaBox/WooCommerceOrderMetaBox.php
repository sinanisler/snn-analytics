<?php

namespace IAWP\WooCommerceOrderMetaBox;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class WooCommerceOrderMetaBox
{
    private int $order_id;
    private ?object $record;
    public function __construct(int $order_id)
    {
        $this->order_id = $order_id;
        $this->record = $this->record();
    }
    public function add_meta_box() : void
    {
        if ($this->record === null) {
            return;
        }
        \add_meta_box('iawp-wc-referrer-source', \esc_html__('Customer Journey', 'independent-analytics'), function () {
            $this->render();
        }, self::woocommerce_screen_id(), 'side');
    }
    private function render() : void
    {
        if ($this->record === null) {
            return;
        }
        echo \IAWPSCOPED\iawp_render('woocommerce-order-meta-box', ['record' => $this->record, 'journey_url' => $this->journey_url()]);
    }
    private function journey_url() : string
    {
        $session_id = $this->record->session_id;
        $visitor_id = $this->record->visitor_id;
        return \add_query_arg(['visitor' => $visitor_id, 'session' => $session_id], \admin_url('admin.php?page=independent-analytics-visitor'));
    }
    private function record() : ?object
    {
        $query = Illuminate_Builder::new()->select('initial_resources.cached_title AS initial_page_title', 'initial_resources.cached_url AS initial_page_url', 'sessions.total_views', 'sessions.created_at AS arrived_at', 'sessions.session_id', 'sessions.visitor_id', 'orders.created_at AS ordered_at', 'landing_pages.title AS landing_page_title', 'utm_sources.utm_source', 'utm_mediums.utm_medium', 'utm_campaigns.utm_campaign', 'utm_term', 'utm_content', 'referrers.referrer', 'referrers.domain AS referrer_domain', 'referrer_types.referrer_type', 'countries.country', 'countries.country_code', 'cities.city', 'device_types.device_type', 'device_oss.device_os', 'device_browsers.device_browser')->from(Tables::orders(), 'orders')->join(Tables::views() . ' AS views', function (JoinClause $join) {
            $join->on('views.id', '=', 'orders.view_id');
        })->join(Tables::sessions() . ' AS sessions', function (JoinClause $join) {
            $join->on('sessions.session_id', '=', 'views.session_id');
        })->leftJoin(Tables::campaigns() . ' AS campaigns', function (JoinClause $join) {
            $join->on('sessions.campaign_id', '=', 'campaigns.campaign_id');
        })->leftJoin(Tables::landing_pages() . ' AS landing_pages', function (JoinClause $join) {
            $join->on('campaigns.landing_page_id', '=', 'landing_pages.id');
        })->leftJoin(Tables::utm_sources() . ' AS utm_sources', function (JoinClause $join) {
            $join->on('campaigns.utm_source_id', '=', 'utm_sources.id');
        })->leftJoin(Tables::utm_mediums() . ' AS utm_mediums', function (JoinClause $join) {
            $join->on('campaigns.utm_medium_id', '=', 'utm_mediums.id');
        })->leftJoin(Tables::utm_campaigns() . ' AS utm_campaigns', function (JoinClause $join) {
            $join->on('campaigns.utm_campaign_id', '=', 'utm_campaigns.id');
        })->leftJoin(Tables::referrers() . ' AS referrers', function (JoinClause $join) {
            $join->on('sessions.referrer_id', '=', 'referrers.id');
        })->leftJoin(Tables::referrer_types() . ' AS referrer_types', function (JoinClause $join) {
            $join->on('referrers.referrer_type_id', '=', 'referrer_types.id');
        })->leftJoin(Tables::views() . ' AS initial_views', function (JoinClause $join) {
            $join->on('sessions.initial_view_id', '=', 'initial_views.id');
        })->leftJoin(Tables::resources() . ' AS initial_resources', function (JoinClause $join) {
            $join->on('initial_views.resource_id', '=', 'initial_resources.id');
        })->leftJoin(Tables::countries() . ' AS countries', function (JoinClause $join) {
            $join->on('sessions.country_id', '=', 'countries.country_id');
        })->leftJoin(Tables::cities() . ' AS cities', function (JoinClause $join) {
            $join->on('sessions.city_id', '=', 'cities.city_id');
        })->leftJoin(Tables::device_types() . ' AS device_types', function (JoinClause $join) {
            $join->on('sessions.device_type_id', '=', 'device_types.device_type_id');
        })->leftJoin(Tables::device_oss() . ' AS device_oss', function (JoinClause $join) {
            $join->on('sessions.device_os_id', '=', 'device_oss.device_os_id');
        })->leftJoin(Tables::device_browsers() . ' AS device_browsers', function (JoinClause $join) {
            $join->on('sessions.device_browser_id', '=', 'device_browsers.device_browser_id');
        })->where('orders.woocommerce_order_id', '=', $this->order_id);
        $record = $query->get()->first();
        if (!$record) {
            return null;
        }
        if ($record->referrer_domain === "") {
            $record->referrer_domain = null;
        }
        $record->arrived_at = $this->format_date($record->arrived_at);
        $record->ordered_at = $this->format_date($record->ordered_at);
        return $record;
    }
    private function format_date(string $value) : string
    {
        try {
            $date = CarbonImmutable::parse($value)->setTimezone(Timezone::site_timezone());
            $format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
            return $date->format($format);
        } catch (\Throwable $e) {
            return $value;
        }
    }
    public static function register()
    {
        \add_action('add_meta_boxes', function ($post_type, $post) {
            if (!\IAWPSCOPED\iawp()->is_woocommerce_support_enabled()) {
                return;
            }
            $current_screen_id = \get_current_screen()->id ?? null;
            if ($current_screen_id !== self::woocommerce_screen_id()) {
                return;
            }
            $order_id = self::get_order_id($post);
            $box = new self($order_id);
            $box->add_meta_box();
        }, 10, 2);
    }
    private static function get_order_id($post) : int
    {
        $order = $post;
        if ($post instanceof \WP_Post) {
            $order = wc_get_order($post->ID);
        }
        return $order->get_id();
    }
    private static function woocommerce_screen_id() : string
    {
        if (\class_exists('\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
            return wc_get_page_screen_id('shop-order');
        }
        return 'shop_order';
    }
}
