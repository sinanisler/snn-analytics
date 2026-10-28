<?php

namespace IAWP\Ecommerce;

use IAWP\Cron_Job;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class SureCart_Event_Sync_Job extends Cron_Job
{
    protected $name = 'iawp_surecart_event_syncing';
    protected $interval = 'five_minutes';
    public function should_execute_handler() : bool
    {
        return \IAWPSCOPED\iawp()->is_surecart_support_enabled();
    }
    public function handle() : void
    {
        $one_day_ago = \time() - 86400;
        $last_seen_event_at = \get_option('iawp_last_seen_surecart_event_at', $one_day_ago);
        $events = new Collection();
        $page = 1;
        while (\true) {
            // If an event is older than the last one we've seen, then we have all the events we need
            if ($events->isNotEmpty() && $events->last()->created_at < $last_seen_event_at) {
                break;
            }
            if (!\class_exists('\\SureCart\\Models\\Event')) {
                break;
            }
            $page_of_events = \SureCart\Models\Event::where(['type' => ['refund.succeeded', 'purchase.created'], 'limit' => 100, 'page' => $page])->get();
            if (\is_wp_error($page_of_events) || \count($page_of_events) === 0) {
                break;
            }
            $events = $events->concat($page_of_events);
            $page = $page + 1;
        }
        if ($events->isNotEmpty()) {
            \update_option('iawp_last_seen_surecart_event_at', $events->first()->created_at);
        }
        $events = $events->filter(function ($event) use($last_seen_event_at) {
            return $event->created_at > $last_seen_event_at;
        });
        $events->filter(function ($event) {
            return $event->type === 'refund.succeeded';
        })->map(function ($event) {
            return $event->data->object->charge;
        })->unique()->each(function ($charge_id) {
            \IAWP\Ecommerce\SureCart_Order::update_order_using_charge_id($charge_id);
        });
        $events->filter(function ($event) {
            return $event->type === 'purchase.created';
        })->map(function ($event) {
            return $event->data->object->initial_order;
        })->unique()->each(function ($order_id) {
            \IAWP\Ecommerce\SureCart_Order::update_order_using_order_id($order_id);
        });
    }
}
