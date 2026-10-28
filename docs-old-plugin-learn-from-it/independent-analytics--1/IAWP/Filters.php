<?php

namespace IAWP;

use IAWP\Tables\Columns\Column;
use IAWP\Utils\Security;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
/** @internal */
class Filters
{
    public function get_filters_html(array $columns) : string
    {
        $options = \IAWP\Dashboard_Options::getInstance();
        \ob_start();
        ?>
    <div class="modal-parent filters"
         data-controller="filters"
         data-filters-filters-value="<?php 
        echo \esc_attr(\json_encode($options->raw_filters()));
        ?>"
         data-filters-filter-logic-value="<?php 
        echo Security::attr($options->filter_logic());
        ?>"
    >
        <span class="dashicons dashicons-filter"></span>
        <div id="filter-condition-buttons" data-filters-target="conditionButtons" class="filter-condition-buttons">
            <?php 
        echo $this->condition_buttons_html($options->filters());
        ?>
        </div>
        <button id="filters-button" class="filters-button"
                data-action="filters#toggleModal"
                data-filters-target="modalButton"
        >
            <span class="iawp-label"><?php 
        \esc_html_e('+ Add Filter', 'independent-analytics');
        ?></span>
        </button>
        <div id="modal-filters"
             class="iawp-modal large"
             data-filters-target="modal"
        >
            <div class="modal-inner">
                <?php 
        $select = '<select name="filter_logic" data-filters-target="filterLogic" id="filter_logic" data-action="filters#changeFilterLogic">
                                    <option value="and" ' . ($options->filter_logic() === 'and' ? 'selected' : '') . '>' . \esc_html__('all', 'independent-analytics') . '</option>
                                    <option value="or" ' . ($options->filter_logic() === 'or' ? 'selected' : '') . '>' . \esc_html__('any', 'independent-analytics') . '</option>
                                </select>';
        ?>
                <div class="title-small filter-title">
                    <?php 
        \printf(\__('If %s of the conditions apply', 'independent-analytics'), $select);
        ?>
                    <span data-filters-target="spinner" class="dashicons dashicons-update iawp-spin hidden"></span>
                </div>
                <div id="filters" data-filters-target="filters" class="filters" data-filters="[]">
                </div>
                <template data-filters-target="blueprint">
                    <?php 
        echo $this->get_condition_html($columns);
        ?>
                </template>
                <div>
                    <button id="add-condition" class="iawp-text-button"
                            data-action="filters#addCondition"
                    >
                        <?php 
        \esc_html_e('+ Add another condition', 'independent-analytics');
        ?>
                    </button>
                </div>
                <div class="actions">
                    <button id="filters-apply" class="iawp-button purple"
                            data-action="filters#apply"
                    >
                        <?php 
        \esc_html_e('Apply', 'independent-analytics');
        ?>
                    </button>
                    <button id="filters-reset" class="iawp-button ghost-purple"
                            data-action="filters#reset"
                            data-filters-target="reset"
                            disabled
                    >
                        <?php 
        \esc_html_e('Reset', 'independent-analytics');
        ?>
                    </button>
                </div>
            </div>
        </div>
        </div><?php 
        $html = \ob_get_contents();
        \ob_end_clean();
        return $html;
    }
    public function get_condition_html(array $columns)
    {
        \ob_start();
        ?>
        <div class="condition" data-filters-target="condition">
            <div class="input-group">
                <div>
                    <?php 
        echo self::get_inclusion_selects();
        ?>
                </div>
                <div>
                    <?php 
        self::get_column_select($columns);
        ?>
                </div>
                <div class="operator-select-container">
                    <?php 
        echo self::get_all_operator_selects();
        ?>
                </div>
                <div class="operand-field-container">
                    <?php 
        echo self::get_all_operand_fields($columns);
        ?>
                </div>
            </div>
            <button class="delete-button" data-action="filters#removeCondition">
                <span class="dashicons dashicons-no"></span></button>
        </div>
        <?php 
        $html = \ob_get_contents();
        \ob_end_clean();
        return $html;
    }
    public function condition_buttons_html(array $filters) : string
    {
        \ob_start();
        for ($i = 0; $i < \count($filters); $i++) {
            if ($i == 2) {
                ?>
                <button class="filters-condition-button"
                data-action="filters#toggleModal"
                data-filters-target="modalButton"><?php 
                \printf(\esc_html__('+%d More', 'independent-analytics'), \count($filters) - 2);
                ?></button><?php 
                break;
            }
            ?>
            <button class="filters-condition-button"
                data-action="filters#toggleModal"
                data-filters-target="modalButton"><?php 
            echo \wp_kses_post($filters[$i]->html_description());
            ?></button>
        <?php 
        }
        $html = \ob_get_contents();
        \ob_end_clean();
        return $html;
    }
    private function get_inclusion_selects()
    {
        $html = '<select class="filters-include" data-filters-target="inclusion">';
        $html .= '<option value="include">' . \esc_html__('Include', 'independent-analytics') . '</option>';
        $html .= '<option value="exclude">' . \esc_html__('Exclude', 'independent-analytics') . '</option>';
        $html .= '</select>';
        return $html;
    }
    /**
     * @param Column[] $columns
     *
     * @return void
     */
    private function get_column_select(array $columns)
    {
        $plugin_groups = \IAWP\Plugin_Group::get_plugin_groups();
        $column_sections = [];
        foreach ($columns as $column) {
            $plugin_group_id = $column->plugin_group();
            $section_name = '';
            $plugin_group = null;
            foreach ($plugin_groups as $a_plugin_group) {
                if ($a_plugin_group->id() === $plugin_group_id) {
                    $section_name = $a_plugin_group->name();
                    $plugin_group = $a_plugin_group;
                }
            }
            if (!$plugin_group->has_active_group_plugins()) {
                continue;
            }
            if (!\is_null($column->plugin_group_header())) {
                $section_name = $plugin_group->name() . ' - ' . $column->plugin_group_header();
            }
            if (!\array_key_exists($section_name, $column_sections)) {
                $column_sections[$section_name] = ['plugin_group' => $plugin_group, 'columns' => []];
            }
            \array_push($column_sections[$section_name]['columns'], $column);
        }
        echo \IAWPSCOPED\iawp_blade()->run('partials.filter-column-select', ['column_sections' => $column_sections]);
    }
    private function get_all_operator_selects()
    {
        $html = '';
        foreach (self::get_data_types() as $data_type) {
            $html .= '<select data-filters-target="operator" data-type="' . \esc_attr($data_type) . '" data-testid="' . \esc_attr($data_type) . '-operator">';
            foreach (self::get_operators($data_type) as $key => $value) {
                $html .= '<option value="' . \esc_attr($key) . '">' . \esc_html($value) . '</option>';
            }
            $html .= '</select>';
        }
        return $html;
    }
    private function get_all_operand_fields(array $columns)
    {
        $html = '';
        foreach ($columns as $column) {
            switch ($column->type()) {
                case 'string':
                    $html .= '<input data-filters-target="operand" data-action="keydown->filters#operandKeyDown filters#operandChange" data-column="' . \esc_attr($column->id()) . '" type="text" data-testid="' . \esc_attr($column->id()) . '-operand" placeholder="' . \esc_attr($column->filter_placeholder()) . '" />';
                    break;
                case 'int':
                    $html .= '<input data-filters-target="operand" data-action="keydown->filters#operandKeyDown filters#operandChange" data-column="' . \esc_attr($column->id()) . '" type="number" data-testid="' . \esc_attr($column->id()) . '-operand" placeholder="' . \esc_attr($column->filter_placeholder()) . '" />';
                    break;
                case 'date':
                    $html .= '<input type="text"
                        data-filters-target="operand"
                        data-action="keydown->filters#operandKeyDown filters#operandChange"
                        data-column="' . \esc_attr($column->id()) . '"
                        data-controller="easepick"
                        data-css="' . \esc_url(\IAWPSCOPED\iawp_url_to('dist/styles/easepick/datepicker.css')) . '" data-dow="' . \absint(\IAWPSCOPED\iawp()->get_option('iawp_dow', 1)) . '"
                        data-format="' . \esc_attr(WordPress_Site_Date_Format_Pattern::for_javascript()) . '"
                        data-testid="' . \esc_attr($column->id()) . '-operand" />';
                    break;
                case 'select':
                    $html .= '<select data-filters-target="operand" data-column="' . \esc_attr($column->id()) . '" data-testid="' . \esc_attr($column->id()) . '-operand">';
                    $html .= $this->get_select_options($column);
                    $html .= '</select>';
                    break;
            }
        }
        return $html;
    }
    private function get_select_options(Column $column) : string
    {
        $html = '';
        $parents = [];
        $children_by_parent_id = [];
        foreach ($column->options()->all() as $option) {
            if ($option->is_parent()) {
                $parents[] = $option;
                continue;
            }
            if (!\array_key_exists($option->parent_id, $children_by_parent_id)) {
                $children_by_parent_id[$option->parent_id] = [];
            }
            $children_by_parent_id[$option->parent_id][] = $option;
        }
        $has_nesting = \count($children_by_parent_id) > 0;
        foreach ($parents as $parent) {
            if ($has_nesting) {
                $html .= '<optgroup label="' . \esc_attr($parent->label) . '">';
            }
            $html .= '<option value="' . \esc_attr($parent->id) . '">' . \esc_html($parent->label) . '</option>';
            if (\array_key_exists($parent->id, $children_by_parent_id)) {
                foreach ($children_by_parent_id[$parent->id] as $child) {
                    $html .= '<option value="' . \esc_attr($child->id) . '">' . \esc_html($child->label) . '</option>';
                }
            }
            if ($has_nesting) {
                $html .= '</optgroup>';
            }
        }
        return $html;
    }
    private function get_data_types()
    {
        return ['string', 'int', 'date', 'select'];
    }
    private function get_operators(string $data_type)
    {
        if ($data_type == 'string') {
            return ['contains' => \esc_html__('Contains', 'independent-analytics'), 'exact' => \esc_html__('Exactly matches', 'independent-analytics')];
        } elseif ($data_type == 'int') {
            return ['greater' => \esc_html__('Greater than', 'independent-analytics'), 'lesser' => \esc_html__('Less than', 'independent-analytics'), 'equal' => \esc_html__('Equal to', 'independent-analytics')];
        } elseif ($data_type == 'select') {
            return ['is' => \esc_html__('Is', 'independent-analytics'), 'isnt' => \esc_html__('Isn\'t', 'independent-analytics')];
        } elseif ($data_type == 'date') {
            return ['before' => \esc_html__('Before', 'independent-analytics'), 'after' => \esc_html__('After', 'independent-analytics'), 'on' => \esc_html__('On', 'independent-analytics')];
        } else {
            return null;
        }
    }
}
