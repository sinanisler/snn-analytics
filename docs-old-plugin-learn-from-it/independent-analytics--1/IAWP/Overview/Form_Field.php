<?php

namespace IAWP\Overview;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Plugin_Group;
use IAWP\Report;
use IAWP\Report_Finder;
use IAWP\Statistics\Statistic;
use IAWP\Tables\Columns\Column;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Form_Field
{
    private static $report_details;
    private $id;
    private $report;
    public function __construct(string $id, ?Report $report)
    {
        $this->id = $id;
        $this->report = $report;
    }
    public function id() : string
    {
        return $this->id;
    }
    public function name() : string
    {
        $name_lookup_table = ['report' => \__('Report', 'independent-analytics'), 'geo_report' => \__('Geo Report', 'independent-analytics'), 'sort_by' => \__('Sort By', 'independent-analytics'), 'sort_direction' => \__('Sort Direction', 'independent-analytics'), 'aggregatable_sort_by' => \__('Sort By', 'independent-analytics'), 'date_range' => \__('Date Range', 'independent-analytics'), 'busiest_date_range' => \__('Date Range', 'independent-analytics'), 'primary_metric' => \__('Primary Metric', 'independent-analytics'), 'secondary_metric' => \__('Secondary Metric', 'independent-analytics'), 'statistics' => \__('Statistics', 'independent-analytics'), 'recent_conversion_types' => \__('Conversions', 'independent-analytics')];
        return $name_lookup_table[$this->id] ?? $this->id;
    }
    public function type() : string
    {
        $field_type_lookup_table = ['report' => 'select', 'geo_report' => 'select', 'sort_by' => 'select', 'sort_direction' => 'select', 'aggregatable_sort_by' => 'select', 'date_range' => 'select', 'busiest_date_range' => 'select', 'primary_metric' => 'select', 'secondary_metric' => 'select', 'statistics' => 'checkboxes', 'recent_conversion_types' => 'checkboxes'];
        return $field_type_lookup_table[$this->id] ?? 'select';
    }
    /**
     * Check if the provided value is valid for a given form field.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function is_a_supported_value($value) : bool
    {
        if (\is_array($value)) {
            return $this->is_a_supported_array_value($value);
        }
        return \in_array($value, $this->supported_ids(), \true);
    }
    /**
     * Get the grouped form field options.
     *
     * @return array
     */
    public function template_values() : array
    {
        $grouped_values = Collection::make($this->supported_values())->groupBy(function (\IAWP\Overview\Form_Field_Option $form_field_option) {
            return $form_field_option->group();
        })->toArray();
        // If the values don't have groups, just return an array of the values
        if (\count($grouped_values) === 1 && \array_key_exists("", $grouped_values)) {
            return $grouped_values[''];
        }
        return $grouped_values;
    }
    /**
     * @return Form_Field_Option[]
     */
    public function supported_values() : array
    {
        $report_details = self::get_report_details_by_id($this->report->id());
        $report_values = Collection::make(Report_Finder::new()->get_reports())->map(function (Report $report) {
            return \IAWP\Overview\Form_Field_Option::new($report->id(), $report->name(), $report->type_label());
        })->all();
        $geo_report_values = Collection::make(Report_Finder::new()->get_reports())->filter(function (Report $report) {
            return $report->type() === 'geo';
        })->map(function (Report $report) {
            return \IAWP\Overview\Form_Field_Option::new($report->id(), $report->name());
        })->values()->all();
        $sort_direction_values = [\IAWP\Overview\Form_Field_Option::new('desc', 'High-to-Low'), \IAWP\Overview\Form_Field_Option::new('asc', 'Low-to-High')];
        $date_range_values = Collection::make(Relative_Date_Range::ranges())->map(function (Relative_Date_Range $range) {
            return \IAWP\Overview\Form_Field_Option::new($range->relative_range_id(), $range->label());
        })->all();
        $busiest_date_range_values = Collection::make(Relative_Date_Range::ranges())->filter(function (Relative_Date_Range $range) {
            $busiest_ranges = ['LAST_SEVEN', 'LAST_THIRTY', 'LAST_NINETY'];
            return \in_array($range->relative_range_id(), $busiest_ranges);
        })->map(function (Relative_Date_Range $range) {
            return \IAWP\Overview\Form_Field_Option::new($range->relative_range_id(), $range->label());
        })->all();
        $secondary_metric = Collection::make($report_details['statistics'] ?? [])->prepend(\IAWP\Overview\Form_Field_Option::new('no_comparison', \__('No Comparison', 'independent-analytics'), \__('No Comparison', 'independent-analytics')))->all();
        $recent_conversion_types = [\IAWP\Overview\Form_Field_Option::new('order', \__('Orders', 'independent-analytics')), \IAWP\Overview\Form_Field_Option::new('form_submission', \__('Form Submissions', 'independent-analytics')), \IAWP\Overview\Form_Field_Option::new('click', \__('Clicks', 'independent-analytics'))];
        $supported_values_lookup_table = ['report' => $report_values, 'geo_report' => $geo_report_values, 'sort_by' => $report_details['columns'] ?? [], 'sort_direction' => $sort_direction_values, 'aggregatable_sort_by' => $report_details['aggregatable_columns'] ?? [], 'date_range' => $date_range_values, 'busiest_date_range' => $busiest_date_range_values, 'primary_metric' => $report_details['statistics'] ?? [], 'secondary_metric' => $secondary_metric, 'statistics' => $report_details['statistics'] ?? [], 'recent_conversion_types' => $recent_conversion_types];
        return $supported_values_lookup_table[$this->id] ?? [];
    }
    private function supported_ids() : array
    {
        return Collection::make($this->supported_values())->map(function (\IAWP\Overview\Form_Field_Option $option) {
            return $option->id();
        })->all();
    }
    /**
     * Check if the provided array values are valid for a given form field.
     *
     * @param array $values
     *
     * @return bool
     */
    private function is_a_supported_array_value(array $values) : bool
    {
        // Only checkboxes support selecting more than one value
        if ($this->type() !== 'checkboxes') {
            return \false;
        }
        return Collection::make($values)->every(function ($value) {
            return \in_array($value, $this->supported_ids(), \true);
        });
    }
    public static function get_report_details() : array
    {
        if (\is_array(self::$report_details)) {
            return self::$report_details;
        }
        self::$report_details = Collection::make(Report_Finder::new()->get_reports())->map(function (Report $report) {
            $columns = Collection::make($report->get_supported_columns())->filter(function (Column $column) {
                return \in_array($column->type(), ['int', 'date']);
            })->map(function (Column $column) {
                $plugin_group = Plugin_Group::get_plugin_group($column->plugin_group());
                return \IAWP\Overview\Form_Field_Option::new($column->id(), $column->name(), $plugin_group->name());
            })->values()->all();
            $aggregatable_columns = Collection::make($report->get_supported_columns())->filter(function (Column $column) {
                return $column->aggregatable();
            })->map(function (Column $column) {
                $plugin_group = Plugin_Group::get_plugin_group($column->plugin_group());
                return \IAWP\Overview\Form_Field_Option::new($column->id(), $column->name(), $plugin_group->name());
            })->values()->all();
            $statistics = \array_map(function (Statistic $statistic) {
                $plugin_group = Plugin_Group::get_plugin_group($statistic->plugin_group());
                return \IAWP\Overview\Form_Field_Option::new($statistic->id(), $statistic->name(), $plugin_group->name());
            }, $report->get_supported_statistics());
            return ['id' => $report->id(), 'name' => $report->name(), 'columns' => $columns, 'aggregatable_columns' => $aggregatable_columns, 'statistics' => $statistics];
        })->all();
        return self::$report_details;
    }
    public static function get_report_details_by_id($id) : ?array
    {
        return Collection::make(self::get_report_details())->first(function ($report_details) use($id) {
            return $report_details['id'] === $id;
        });
    }
}
