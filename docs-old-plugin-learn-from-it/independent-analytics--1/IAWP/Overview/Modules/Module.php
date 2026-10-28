<?php

namespace IAWP\Overview\Modules;

use DateTime;
use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Overview\Form_Field;
use IAWP\Overview\Form_Field_Option;
use IAWP\Overview\Sync_Module_Background_Job;
use IAWP\Overview\WP_Options_Storage;
use IAWP\Report;
use IAWP\Report_Finder;
use IAWP\Utils\Security;
use IAWP\Utils\Timezone;
use IAWPSCOPED\Illuminate\Support\Arr;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
abstract class Module
{
    protected $attributes;
    private $report;
    private $form_fields;
    private $validation_errors = null;
    private $options_storage;
    public function __construct(?array $attributes = [])
    {
        $this->report = $this->get_report($attributes);
        $this->form_fields = $this->create_form_fields();
        $this->attributes = $this->prune_attributes($attributes);
        $this->options_storage = new WP_Options_Storage('iawp_overview_modules');
    }
    public abstract function module_type() : string;
    public abstract function module_name() : string;
    protected abstract function module_fields() : array;
    public abstract function calculate_dataset();
    public function id() : ?string
    {
        return $this->attributes['id'] ?? null;
    }
    public function name() : ?string
    {
        return $this->attributes['name'] ?? null;
    }
    public function is_full_width() : bool
    {
        return $this->attributes['is_full_width'] ?? \false;
    }
    public function report() : ?Report
    {
        return $this->report;
    }
    public function save($skip_sync = \false) : bool
    {
        if (!$this->is_valid()) {
            return \false;
        }
        $is_update = $this->options_storage->exists($this->id());
        // Generate an id
        if (!$is_update) {
            $this->attributes['id'] = $this->options_storage->generate_id();
        }
        $this->options_storage->insert($this->attributes);
        $this->report = $this->get_report($this->attributes);
        if (!$skip_sync) {
            static::queue_dataset_refresh([$this]);
        }
        return \true;
    }
    public function update(array $attributes)
    {
        $pruned_attributes = $this->prune_attributes($attributes, \true);
        $this->attributes = \array_merge($this->attributes, $pruned_attributes);
        $this->report = $this->get_report($attributes);
        $this->form_fields = $this->create_form_fields();
    }
    public function delete() : bool
    {
        // Delete any cached datasets
        \delete_option($this->option_name());
        // Delete the module configuration
        return $this->options_storage->delete($this->id());
    }
    public function is_valid() : bool
    {
        $validation_errors = [];
        if (\is_string($this->attributes['name'] ?? null) && \strlen($this->attributes['name']) > 0) {
        } else {
            $validation_errors[] = ['name', 'Must be provided'];
        }
        // Check every form field
        foreach ($this->form_fields as $form_field) {
            $value = $this->attributes[$form_field->id()] ?? null;
            if (!$form_field->is_a_supported_value($value)) {
                $validation_errors[] = [$form_field->id(), "Invalid option provided"];
            }
        }
        if (\count($validation_errors) > 0) {
            $this->validation_errors = $validation_errors;
            return \false;
        } else {
            return \true;
        }
    }
    public function validation_errors() : ?array
    {
        return $this->validation_errors;
    }
    public function form_fields() : array
    {
        return $this->form_fields;
    }
    public function get_module_html() : string
    {
        $dataset = $this->get_dataset();
        return \IAWPSCOPED\iawp_render("overview.modules.layout", ['module' => $this, 'dataset' => $dataset, 'is_loaded' => $dataset !== null, 'is_empty' => \is_array($dataset) && empty($dataset)]);
    }
    public function get_editor_html() : string
    {
        return \IAWPSCOPED\iawp_render('overview.module-editor', ['module' => $this]);
    }
    public function get_form_fields_html() : string
    {
        $html = '';
        foreach ($this->form_fields as $form_field) {
            $type = $form_field->type();
            $html .= \IAWPSCOPED\iawp_render("overview.form-fields.{$type}", ['form_field' => $form_field, 'selected_value' => $this->attributes[$form_field->id()] ?? null]);
        }
        return $html;
    }
    public function get_report_details() : array
    {
        return Form_Field::get_report_details();
    }
    /**
     * Has this module already been saved?
     *
     * @return bool
     */
    public function is_saved() : bool
    {
        return $this->id() !== null;
    }
    public function get_dataset() : ?array
    {
        $value = \get_option($this->option_name(), null);
        return \is_array($value) ? $value : null;
    }
    public function has_dataset() : bool
    {
        return \is_array($this->get_dataset());
    }
    /**
     * Subtitle to show under the module name
     *
     * @return string
     */
    public function subtitle() : string
    {
        // If the module has a range option, use the selected value
        if ($this->has_dataset()) {
            $relative_range = Relative_Date_Range::range_by_id($this->attributes['date_range'] ?? $this->attributes['busiest_date_range']);
            if ($relative_range) {
                return $relative_range->label();
            }
        }
        $number_of_days = $this->get_dataset_number_of_days();
        if (\is_int($number_of_days)) {
            return \sprintf(\__("Last %s Days", 'independent-analytics'), $this->get_dataset_number_of_days());
        }
        return '';
    }
    public function refresh_dataset()
    {
        \delete_option($this->option_name());
        \update_option($this->option_name(), $this->calculate_dataset(), \false);
    }
    protected function get_dataset_number_of_days() : ?int
    {
        return null;
    }
    protected function option_name() : string
    {
        return 'iawp_module_' . $this->id();
    }
    /**
     * Get the name for the field option that was selcted for the given $field_id.
     *
     * @param string $field_id
     *
     * @return string|null
     */
    protected function get_field_option_name(string $field_id) : ?string
    {
        $selected_field_option_id = $this->attributes[$field_id] ?? null;
        if ($selected_field_option_id === null) {
            return null;
        }
        $options = $this->get_form_field($field_id)->supported_values();
        $option = Collection::make($options)->first(function (Form_Field_Option $option) use($selected_field_option_id) {
            return $option->id() === $selected_field_option_id;
        });
        if ($option === null) {
            return null;
        }
        return $option->name();
    }
    /**
     * @return ?Form_Field
     */
    protected function get_form_field(string $id) : ?Form_Field
    {
        return Collection::make($this->form_fields)->first(function (Form_Field $field) use($id) {
            return $field->id() === $id;
        });
    }
    /**
     * Determine which report to associate with a module instance.
     *
     * @param array $attributes
     *
     * @return Report
     */
    private function get_report(array $attributes) : Report
    {
        $default_report = Report_Finder::new()->get_base_report_for_type('views');
        $report_id = $attributes['report'] ?? $attributes['geo_report'] ?? null;
        if ($report_id === null) {
            return $default_report;
        }
        $report = Report_Finder::new()->fetch_report($report_id);
        if ($report instanceof Report) {
            return $report;
        } else {
            return $default_report;
        }
    }
    /**
     * Remove attributes that are not available for a given module
     *
     * @param array $attributes
     *
     * @return array
     */
    private function prune_attributes(array $attributes, $for_update = \false) : array
    {
        if ($for_update) {
            $valid_attributes = Collection::make(['name', 'is_full_width']);
        } else {
            $valid_attributes = Collection::make(['id', 'module_type', 'name', 'is_full_width']);
        }
        foreach ($this->form_fields as $form_field) {
            $valid_attributes->push($form_field->id());
        }
        $new_attributes = Arr::only($attributes, $valid_attributes->all());
        // Convert is_full_width into a boolean if present
        if (\array_key_exists('is_full_width', $new_attributes) && !\is_bool($new_attributes['is_full_width'])) {
            $new_attributes['is_full_width'] = $new_attributes['is_full_width'] === 'true';
        }
        foreach ($new_attributes as $key => $value) {
            if (\is_string($value)) {
                $new_attributes[$key] = Security::string($value);
            }
            if (\is_array($value) && Arr::isList($value)) {
                $new_attributes[$key] = Collection::make($value)->map(function ($value) {
                    if (\is_string($value)) {
                        return Security::string($value);
                    }
                    return $value;
                })->all();
            } elseif (\is_array($value) && Arr::isAssoc($value)) {
                unset($new_attributes[$key]);
            }
        }
        return $new_attributes;
    }
    /**
     * @return Form_Field[]
     */
    private function create_form_fields() : array
    {
        return Collection::make($this->module_fields())->map(function (string $id) {
            return new Form_Field($id, $this->report);
        })->all();
    }
    /**
     * Triggers a background job to refresh the module's dataset.
     *
     * @param Module[] $modules
     *
     * @return void
     */
    public static function queue_dataset_refresh(array $modules) : void
    {
        $ids = [];
        foreach ($modules as $module) {
            // Delete existing data
            \delete_option($module->option_name());
            // Add it to the list of modules to update
            $ids[] = $module->id();
        }
        // Trigger a background job
        $sync = new Sync_Module_Background_Job();
        $sync->data(['ids' => $ids]);
        $sync->dispatch();
    }
    public static function last_refreshed_at() : string
    {
        $timestamp = \get_option('iawp_modules_refreshed_at', null);
        // The option could be a string or an int. Normalize it.
        if (\is_int($timestamp)) {
            $timestamp = \strval($timestamp);
        }
        if (!\ctype_digit($timestamp)) {
            return '';
        }
        try {
            $date = DateTime::createFromFormat('U', $timestamp);
            $date->setTimezone(Timezone::site_timezone());
            $format = \IAWPSCOPED\iawp()->get_option('time_format', 'g:i a');
            return \sprintf(\_x('Last updated at %s.', 'The placeholder is a time of day', 'independent-analytics'), $date->format($format));
        } catch (\Exception $e) {
            return '';
        }
    }
    public static function new(string $module_type, array $attributes) : ?self
    {
        switch ($module_type) {
            case 'top-ten':
                return new \IAWP\Overview\Modules\Top_Ten_Module($attributes);
            case 'quick-stats':
                return new \IAWP\Overview\Modules\Quick_Stats_Module($attributes);
            case 'line-chart':
                return new \IAWP\Overview\Modules\Line_Chart_Module($attributes);
            case 'pie-chart':
                return new \IAWP\Overview\Modules\Pie_Chart_Module($attributes);
            case 'map':
                return new \IAWP\Overview\Modules\Map_Module($attributes);
            case 'recent-views':
                return new \IAWP\Overview\Modules\Recent_Views_Module($attributes);
            case 'recent-conversions':
                return new \IAWP\Overview\Modules\Recent_Conversions_Module($attributes);
            case 'busiest-time-of-day':
                return new \IAWP\Overview\Modules\Busiest_Time_Of_Day_Module($attributes);
            case 'busiest-day-of-week':
                return new \IAWP\Overview\Modules\Busiest_Day_Of_Week_Module($attributes);
            case 'new-sessions':
                return new \IAWP\Overview\Modules\New_Sessions_Module($attributes);
            default:
                return null;
        }
    }
    /**
     * Get a template version of each module for use when creating new modules
     *
     * @return self[]
     */
    public static function get_template_modules() : array
    {
        return [new \IAWP\Overview\Modules\Top_Ten_Module(), new \IAWP\Overview\Modules\Quick_Stats_Module(), new \IAWP\Overview\Modules\Line_Chart_Module(), new \IAWP\Overview\Modules\Pie_Chart_Module(), new \IAWP\Overview\Modules\Map_Module(), new \IAWP\Overview\Modules\Recent_Views_Module(), new \IAWP\Overview\Modules\Recent_Conversions_Module(), new \IAWP\Overview\Modules\Busiest_Time_Of_Day_Module(), new \IAWP\Overview\Modules\Busiest_Day_Of_Week_Module(), new \IAWP\Overview\Modules\New_Sessions_Module()];
    }
    /**
     * Get the modules that are saved to wp_options
     *
     * @return self[]
     */
    public static function get_saved_modules() : array
    {
        $options_storage = new WP_Options_Storage('iawp_overview_modules');
        $records = $options_storage->all();
        // No records? Add the default modules.
        if (\count($records) === 0 && \IAWPSCOPED\iawp()->get_option('iawp_default_modules_added', \false) === \false) {
            \update_option('iawp_modules_refreshed_at', \time(), \true);
            \update_option('iawp_default_modules_added', \true, \true);
            foreach (self::default_modules() as $attributes) {
                $module = self::new($attributes['module_type'], $attributes);
                $module->save(\true);
            }
            $records = $options_storage->all();
            static::queue_full_module_refresh();
        }
        return \array_map(function ($record) {
            return self::new($record['module_type'], $record);
        }, $records);
    }
    public static function queue_full_module_refresh() : void
    {
        $modules = self::get_saved_modules();
        static::queue_dataset_refresh($modules);
        \update_option('iawp_modules_refreshed_at', \time(), \true);
    }
    public static function get_saved_module(string $id) : ?self
    {
        $modules = self::get_saved_modules();
        return Collection::make($modules)->first(function (self $module) use($id) {
            return $module->id() === $id;
        });
    }
    /**
     * @param string[] $ids
     *
     * @return void
     */
    public static function set_module_order(array $ids) : void
    {
        $options_storage = new WP_Options_Storage('iawp_overview_modules');
        $options_storage->set_order($ids);
    }
    /**
     * Delete all exiting modules and restore the defaults.
     *
     * @return void
     */
    public static function reset()
    {
        $modules = self::get_saved_modules();
        foreach ($modules as $module) {
            $module->delete();
        }
        \delete_option('iawp_modules_refreshed_at');
        \delete_option('iawp_default_modules_added');
    }
    public static function default_modules() : array
    {
        return [["module_type" => "line-chart", "name" => \esc_html__("Site Traffic", "independent-analytics"), "report" => "views", "primary_metric" => "visitors", "secondary_metric" => "views", "date_range" => "LAST_THIRTY"], ["module_type" => "top-ten", "name" => \esc_html__("Top 10 Pages", "independent-analytics"), "report" => "views", "sort_by" => "visitors", "sort_direction" => "desc", "date_range" => "LAST_SEVEN"], ["module_type" => "map", "name" => \esc_html__("Geographic Traffic", "independent-analytics"), "geo_report" => "geo", "date_range" => "LAST_SEVEN"], ["module_type" => "new-sessions", "name" => \esc_html__("New vs. Returning Sessions", "independent-analytics"), "date_range" => "LAST_THIRTY"], ["module_type" => "quick-stats", "name" => \esc_html__("Site Metrics", "independent-analytics"), "report" => "views", "statistics" => ["visitors", "views", "sessions", "average_session_duration", "bounce_rate", "views_per_session", "clicks"], "date_range" => "LAST_SEVEN", "is_full_width" => \true], ["module_type" => "recent-views", "name" => \esc_html__("Recent Views", "independent-analytics")], ["module_type" => "recent-conversions", "name" => \esc_html__("Recent Conversions", "independent-analytics"), "recent_conversion_types" => ["order", "form_submission", "click"]], ["module_type" => "pie-chart", "name" => \esc_html__("Devices", "independent-analytics"), "report" => "devices", "aggregatable_sort_by" => "visitors", "date_range" => "LAST_THIRTY"], ["module_type" => "pie-chart", "name" => \esc_html__("Top Traffic Sources", "independent-analytics"), "report" => "referrers", "aggregatable_sort_by" => "visitors", "date_range" => "LAST_SEVEN"], ["module_type" => "busiest-time-of-day", "name" => \esc_html__("Busiest Time of Day", "independent-analytics"), "busiest_date_range" => "LAST_NINETY"], ["module_type" => "busiest-day-of-week", "name" => \esc_html__("Busiest Day of Week", "independent-analytics"), "busiest_date_range" => "LAST_NINETY"]];
    }
}
