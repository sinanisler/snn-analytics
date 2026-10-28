<?php

namespace IAWP\Tables\Columns;

use IAWP\ColumnOptions\Options;
use IAWP\Plugin_Group_Option;
use IAWP\Tables\Groups\Group;
/** @internal */
class Column implements Plugin_Group_Option
{
    private $id;
    private $name;
    private $plugin_group;
    private $plugin_group_header;
    private $visible;
    private $can_be_filtered;
    private $type;
    private $exportable;
    private ?Options $options;
    private $filter_placeholder;
    private $unavailable_for;
    private $separate_database_column;
    private $separate_filter_column;
    private $is_nullable;
    private $is_plugin_active;
    private $requires_pro;
    private $aggregatable;
    private $is_concrete_column;
    public function __construct($attributes)
    {
        $this->id = $attributes['id'];
        $this->name = $attributes['name'];
        $this->plugin_group = $attributes['plugin_group'] ?? 'general';
        $this->plugin_group_header = $attributes['plugin_group_header'] ?? null;
        $this->visible = $attributes['visible'] ?? \false;
        $this->can_be_filtered = $attributes['can_be_filtered'] ?? \true;
        $this->type = $attributes['type'];
        $this->exportable = $attributes['exportable'] ?? \true;
        $this->options = $attributes['options'] ?? null;
        $this->filter_placeholder = $attributes['filter_placeholder'] ?? '';
        $this->unavailable_for = $attributes['unavailable_for'] ?? [];
        $this->separate_database_column = $attributes['separate_database_column'] ?? null;
        $this->separate_filter_column = $attributes['separate_filter_column'] ?? null;
        $this->is_nullable = $attributes['is_nullable'] ?? \false;
        $this->is_plugin_active = $attributes['is_subgroup_plugin_active'] ?? \true;
        $this->requires_pro = $attributes['requires_pro'] ?? \false;
        $this->aggregatable = $attributes['aggregatable'] ?? \false;
        $this->is_concrete_column = $attributes['is_concrete_column'] ?? \false;
    }
    public function is_enabled() : bool
    {
        if ($this->requires_pro === \true && \IAWPSCOPED\iawp_is_free()) {
            return \false;
        }
        return \true;
    }
    public function aggregatable() : bool
    {
        return $this->aggregatable;
    }
    public function is_enabled_for_group(Group $group) : bool
    {
        return !\in_array($group->id(), $this->unavailable_for);
    }
    public function is_group_dependent() : bool
    {
        return \count($this->unavailable_for) > 0;
    }
    public function id() : string
    {
        return $this->id;
    }
    public function separate_database_column() : ?string
    {
        return $this->separate_database_column;
    }
    public function separate_filter_column() : ?string
    {
        return $this->separate_filter_column;
    }
    public function name() : string
    {
        return $this->name;
    }
    public function plugin_group() : string
    {
        return $this->plugin_group;
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
        return $this->is_plugin_active;
    }
    public function is_visible() : bool
    {
        return $this->visible;
    }
    public function can_be_filtered() : bool
    {
        return $this->can_be_filtered;
    }
    public function is_member_of_plugin_group(string $plugin_group) : bool
    {
        return $this->plugin_group === $plugin_group;
    }
    public function plugin_group_header() : ?string
    {
        return $this->plugin_group_header;
    }
    public function type() : string
    {
        return $this->type;
    }
    /**
     * @return string[]
     */
    public function filter_operators() : array
    {
        switch ($this->type) {
            case 'string':
                return ['contains', 'exact'];
            case 'date':
                return ['before', 'after', 'on'];
            case 'select':
                return ['is', 'isnt'];
            default:
                // int
                return ['greater', 'lesser', 'equal'];
        }
    }
    public function is_valid_filter_operator(string $operator) : bool
    {
        return \in_array($operator, $this->filter_operators());
    }
    public function sort_direction() : string
    {
        $descending_types = ['int', 'date'];
        return \in_array($this->type, $descending_types) ? 'desc' : 'asc';
    }
    public function set_visibility(bool $visible) : void
    {
        $this->visible = $visible;
    }
    public function exportable() : bool
    {
        return $this->exportable;
    }
    public function options() : ?Options
    {
        return $this->options;
    }
    public function filter_placeholder() : string
    {
        return $this->filter_placeholder;
    }
    public function is_nullable() : bool
    {
        return $this->is_nullable;
    }
    public function is_concrete_column() : bool
    {
        return $this->is_concrete_column;
    }
}
