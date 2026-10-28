<?php

namespace IAWP\Statistics;

use IAWP\Dashboard_Options;
use IAWP\Plugin_Group_Option;
use IAWP\Utils\Currency;
use IAWP\Utils\Number_Formatter;
/** @internal */
class Statistic implements Plugin_Group_Option
{
    private $id;
    private $name;
    private $plugin_group;
    private $plugin_group_header;
    private $icon;
    private $statistic;
    private $previous_period_statistic;
    private $unfiltered_statistic;
    private $statistic_over_time;
    private $is_visible_in_dashboard_widget;
    private $is_invisible;
    private $format;
    private $is_growth_good;
    private $is_subgroup_plugin_enabled;
    private $requires_pro;
    public function __construct(array $attributes)
    {
        $this->id = $attributes['id'];
        $this->name = $attributes['name'];
        $this->plugin_group = $attributes['plugin_group'];
        $this->plugin_group_header = $attributes['plugin_group_header'] ?? null;
        $this->icon = $attributes['icon'] ?? null;
        $this->statistic = $attributes['statistic'] ?? 0;
        $this->previous_period_statistic = $attributes['previous_period_statistic'] ?? 0;
        $this->unfiltered_statistic = $attributes['unfiltered_statistic'] ?? null;
        $this->statistic_over_time = $attributes['statistic_over_time'] ?? [];
        $this->is_visible_in_dashboard_widget = $attributes['is_visible_in_dashboard_widget'] ?? \false;
        $this->is_invisible = $attributes['is_invisible'] ?? \false;
        $this->format = $attributes['format'] ?? null;
        $this->is_growth_good = $attributes['is_growth_good'] ?? \true;
        $this->is_subgroup_plugin_enabled = $attributes['is_subgroup_plugin_active'] ?? \true;
        $this->requires_pro = $attributes['requires_pro'] ?? \false;
    }
    public function is_enabled() : bool
    {
        if ($this->requires_pro === \true && \IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        return \true;
    }
    public function id() : string
    {
        return $this->id;
    }
    public function name() : string
    {
        return $this->name;
    }
    public function icon() : ?string
    {
        return $this->icon;
    }
    public function plugin_group() : string
    {
        return $this->plugin_group;
    }
    public function plugin_group_header() : ?string
    {
        return $this->plugin_group_header;
    }
    public function is_visible_in_dashboard_widget() : bool
    {
        return $this->is_visible_in_dashboard_widget;
    }
    public function is_invisible() : bool
    {
        return $this->is_invisible;
    }
    public function statistic_over_time() : array
    {
        return $this->statistic_over_time;
    }
    public function is_group_plugin_enabled() : bool
    {
        switch ($this->plugin_group) {
            case "ecommerce":
                return \IAWPSCOPED\iawp()->is_ecommerce_support_enabled();
            case "forms":
                return \IAWPSCOPED\iawp()->is_form_submission_support_enabled();
            default:
                return \true;
        }
    }
    public function is_subgroup_plugin_enabled() : bool
    {
        return $this->is_subgroup_plugin_enabled;
    }
    public function is_visible() : bool
    {
        $options = Dashboard_Options::getInstance();
        return \in_array($this->id(), $options->visible_quick_stats());
    }
    public function is_member_of_plugin_group(string $plugin_group) : bool
    {
        return $this->plugin_group === $plugin_group;
    }
    public function formatted_value() : string
    {
        return $this->format_value($this->statistic);
    }
    public function value()
    {
        return $this->statistic;
    }
    public function formatted_unfiltered_value() : ?string
    {
        if (\is_null($this->unfiltered_statistic)) {
            return null;
        }
        return $this->format_value($this->unfiltered_statistic);
    }
    /**
     * Growth can be good or bad depending on the quick stat. This allows it to be calculated on a
     * at the individual quick stat level.
     *
     * The default behavior is "up" and "good" so you can tweak either of those as needed
     *
     * @return string
     */
    public function growth_html_class() : string
    {
        $html_classes = [];
        $has_growth = $this->growth() >= 0;
        if (!$has_growth) {
            $html_classes[] = 'down';
        }
        if ($has_growth && !$this->is_growth_good || !$has_growth && $this->is_growth_good) {
            $html_classes[] = 'bad';
        }
        return \implode(' ', $html_classes);
    }
    public function formatted_growth() : string
    {
        $growth = $this->growth();
        return Number_Formatter::percent(\absint($growth));
    }
    public function growth()
    {
        if ($this->statistic == 0 && $this->previous_period_statistic != 0) {
            return -100;
        } elseif ($this->statistic == 0 || $this->previous_period_statistic == 0) {
            return 0;
        }
        $percent_growth = ($this->statistic / $this->previous_period_statistic - 1) * 100;
        return \round($percent_growth, 0);
    }
    public function format_value($value) : string
    {
        switch ($this->format) {
            case 'time':
                return Number_Formatter::second_to_minute_timestamp($value);
            case 'percent':
                return Number_Formatter::percent($value, 2);
            case 'decimal':
                return Number_Formatter::decimal($value, 2);
            case 'currency':
                return Currency::format($value, \false);
            case 'rounded-currency':
                return Currency::format($value, \true);
            default:
                return Number_Formatter::integer($value);
        }
    }
}
