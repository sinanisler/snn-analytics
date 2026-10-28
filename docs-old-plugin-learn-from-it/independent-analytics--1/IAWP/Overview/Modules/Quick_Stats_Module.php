<?php

namespace IAWP\Overview\Modules;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Env;
use IAWP\Overview\Form_Field_Option;
use IAWP\Statistics\Intervals\Intervals;
use IAWP\Statistics\Statistic;
use IAWP\Statistics\Statistics;
use IAWP\Tables\Table;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Quick_Stats_Module extends \IAWP\Overview\Modules\Module
{
    public function module_type() : string
    {
        return 'quick-stats';
    }
    public function module_name() : string
    {
        return \__('Quick Stats', 'independent-analytics');
    }
    public function calculate_dataset()
    {
        $date_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? null);
        $chart_interval = Intervals::default_for($date_range->number_of_days());
        $table_class = Env::get_table($this->report()->type());
        /** @var Table $table */
        $table = new $table_class($this->report()->group_name(), \true);
        $rows_class = $table->group()->rows_class();
        if ($this->report()->has_filters()) {
            $filters = $table->sanitize_filters($this->report()->filters());
        } else {
            $filters = null;
        }
        $rows_query = new $rows_class($date_range, $table->sanitize_sort_parameters(), null, $filters);
        // $rows       = $rows_query->rows();
        $statistics_class = $table->group()->statistics_class();
        /** @var Statistics $statistics */
        $statistics = new $statistics_class($date_range, $this->report()->has_filters() ? $rows_query : null, $chart_interval);
        return Collection::make($this->attributes['statistics'] ?? [])->map(function (string $statistic_id) use($statistics) {
            return $statistics->get_statistic($statistic_id);
        })->filter(function (?Statistic $statistic) {
            return !\is_null($statistic) && $statistic->is_enabled() && $statistic->is_group_plugin_enabled();
        })->map(function (Statistic $statistic) {
            return ['id' => $statistic->id(), 'name' => $statistic->name(), 'formatted_value' => $statistic->formatted_value(), 'formatted_unfiltered_value' => $statistic->formatted_unfiltered_value(), 'growth' => $statistic->growth(), 'formatted_growth' => $statistic->formatted_growth(), 'growth_html_class' => $statistic->growth_html_class(), 'icon' => $statistic->icon(), 'is_visible' => \true];
        })->all();
    }
    /**
     * Used to determine which stats to show in the loading screen
     *
     * @return array
     */
    public function selected_stats() : array
    {
        $form_field = $this->get_form_field('statistics');
        return Collection::make($this->attributes['statistics'] ?? [])->filter(function (string $statistic_id) use($form_field) {
            return $form_field->is_a_supported_value($statistic_id);
        })->map(function (string $statistic_id) use($form_field) {
            $form_field_option = Collection::make($form_field->supported_values())->first(function (Form_Field_Option $form_field_option) use($statistic_id) {
                return $form_field_option->id() === $statistic_id;
            });
            return ['id' => $form_field_option->id(), 'name' => $form_field_option->name()];
        })->all();
    }
    protected function module_fields() : array
    {
        return ['report', 'statistics', 'date_range'];
    }
}
