<?php

namespace IAWP;

use IAWP\Form_Submissions\Form;
use IAWP\Statistics\Statistic;
use IAWP\Tables\Columns\Column;
use IAWP\Tables\Table;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Report
{
    private $attributes;
    public function __construct(object $attributes)
    {
        $this->attributes = $attributes;
        // Convert string report ids to integers if the string is just numbers
        if (\is_string($this->attributes->report_id) && \ctype_digit($this->attributes->report_id)) {
            $this->attributes->report_id = \intval($this->attributes->report_id);
        }
    }
    /**
     * Will return an int for saved reports and a string for base reports
     *
     * @return int|string
     */
    public function id()
    {
        return $this->attributes->report_id;
    }
    public function type() : string
    {
        return $this->attributes->type;
    }
    public function type_label() : string
    {
        $labels = ['overview' => \__('Overview', 'independent-analytics'), 'real-time' => \__('Real-Time', 'independent-analytics'), 'views' => \__('Pages', 'independent-analytics'), 'referrers' => \__('Referrers', 'independent-analytics'), 'geo' => \__('Geo', 'independent-analytics'), 'devices' => \__('Devices', 'independent-analytics'), 'campaigns' => \__('Campaigns', 'independent-analytics'), 'clicks' => \__('Clicks', 'independent-analytics')];
        return $labels[$this->type()] ?? 'Report';
    }
    public function name() : string
    {
        return $this->attributes->name;
    }
    public function group_name() : ?string
    {
        return $this->attributes->group_name ?? null;
    }
    public function url() : string
    {
        if (!$this->is_saved_report()) {
            return \IAWPSCOPED\iawp_dashboard_url(['tab' => $this->attributes->type]);
        }
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => $this->attributes->type, 'report' => $this->attributes->report_id]);
    }
    public function is_saved_report() : bool
    {
        return \is_int($this->id());
    }
    public function filters() : ?array
    {
        $array = (array) $this->attributes;
        if (\array_key_exists('filters', $array) && \is_string($array['filters'])) {
            $filters = \json_decode($array['filters'], \true);
            if (\false !== $filters && \is_array($filters)) {
                return $filters;
            }
        }
        return null;
    }
    public function has_filters() : bool
    {
        return \is_array($this->filters()) && \count($this->filters()) > 0;
    }
    public function is_current() : bool
    {
        return (new \IAWP\Env())->is_currently_viewed($this->is_saved_report() ? $this->id() : $this->type());
    }
    public function is_favorite() : bool
    {
        return (new \IAWP\Env())->is_favorite($this->is_saved_report() ? $this->id() : $this->type());
    }
    public function to_array() : array
    {
        $array = (array) $this->attributes;
        if (\array_key_exists('columns', $array) && !\is_null($array['columns'])) {
            $array['columns'] = \json_decode($array['columns'], \true);
        }
        if (\array_key_exists('filters', $array) && !\is_null($array['filters'])) {
            $array['filters'] = \json_decode($array['filters'], \true);
        }
        return $array;
    }
    /**
     * @return Column[]
     */
    public function get_supported_columns() : array
    {
        /** @var Table $table_class */
        $table_class = \IAWP\Env::get_table($this->type());
        $table = new $table_class($this->group_name());
        return \array_values($table->get_columns());
    }
    public function get_supported_statistics() : array
    {
        $statistics = [new Statistic(['id' => 'visitors', 'name' => \__('Visitors', 'independent-analytics'), 'plugin_group' => 'general', 'is_visible_in_dashboard_widget' => \true]), new Statistic(['id' => 'views', 'name' => \__('Views', 'independent-analytics'), 'plugin_group' => 'general', 'is_visible_in_dashboard_widget' => \true]), new Statistic(['id' => 'sessions', 'name' => \__('Sessions', 'independent-analytics'), 'plugin_group' => 'general']), new Statistic(['id' => 'average_session_duration', 'name' => \__('Average Session Duration', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'time']), new Statistic(['id' => 'bounce_rate', 'name' => \__('Bounce Rate', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'percent', 'is_growth_good' => \false]), new Statistic(['id' => 'views_per_session', 'name' => \__('Views Per Session', 'independent-analytics'), 'plugin_group' => 'general', 'format' => 'decimal']), new Statistic(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'plugin_group' => 'general', 'requires_pro' => \true]), new Statistic(['id' => 'wc_orders', 'name' => \__('Orders', 'independent-analytics'), 'plugin_group' => 'ecommerce']), new Statistic(['id' => 'wc_gross_sales', 'name' => \__('Gross Sales', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'rounded-currency']), new Statistic(['id' => 'wc_refunds', 'name' => \__('Refunds', 'independent-analytics'), 'plugin_group' => 'ecommerce']), new Statistic(['id' => 'wc_refunded_amount', 'name' => \__('Refunded Amount', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'rounded-currency']), new Statistic(['id' => 'wc_net_sales', 'name' => \__('Total Sales', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'rounded-currency']), new Statistic(['id' => 'wc_conversion_rate', 'name' => \__('Conversion Rate', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'percent']), new Statistic(['id' => 'wc_earnings_per_visitor', 'name' => \__('Earnings Per Visitor', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'currency']), new Statistic(['id' => 'wc_average_order_volume', 'name' => \__('Average Order Volume', 'independent-analytics'), 'plugin_group' => 'ecommerce', 'format' => 'rounded-currency']), new Statistic(['id' => 'form_submissions', 'name' => \__('Form Submissions', 'independent-analytics'), 'plugin_group' => 'forms']), new Statistic(['id' => 'form_conversion_rate', 'name' => \__('Form Conversion Rate', 'independent-analytics'), 'plugin_group' => 'forms', 'format' => 'percent'])];
        foreach (Form::get_forms() as $form) {
            if (!$form->is_plugin_active()) {
                continue;
            }
            $statistics[] = new Statistic(['id' => 'form_submissions_for_' . $form->id(), 'name' => \sprintf(\_x('%s Submissions', 'Title of the contact form', 'independent-analytics'), $form->title()), 'plugin_group' => 'forms', 'is_subgroup_plugin_active' => $form->is_plugin_active(), 'plugin_group_header' => $form->plugin_name()]);
            $statistics[] = new Statistic(['id' => 'form_conversion_rate_for_' . $form->id(), 'name' => \sprintf(\_x('%s Conversion Rate', 'Title of the contact form', 'independent-analytics'), $form->title()), 'plugin_group' => 'forms', 'is_subgroup_plugin_active' => $form->is_plugin_active(), 'plugin_group_header' => $form->plugin_name(), 'format' => 'percent']);
        }
        if ($this->type() === 'clicks') {
            $statistics = [new Statistic(['id' => 'clicks', 'name' => \__('Clicks', 'independent-analytics'), 'plugin_group' => 'general'])];
        }
        return Collection::make($statistics)->filter(function (Statistic $statistic) {
            return $statistic->is_enabled() && $statistic->is_group_plugin_enabled() && $statistic->is_subgroup_plugin_enabled();
        })->values()->all();
    }
}
