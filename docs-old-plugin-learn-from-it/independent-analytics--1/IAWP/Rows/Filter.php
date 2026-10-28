<?php

namespace IAWP\Rows;

use IAWP\Tables\Columns\Column;
use IAWP\Utils\WordPress_Site_Date_Format_Pattern;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Filter
{
    public static string $RECORD_FILTER = 'record';
    public static string $AGGREGATE_FILTER = 'aggregate';
    public static string $OUTER_FILTER = 'outer';
    private string $inclusion;
    private string $operator;
    private string $operand;
    private Column $column;
    public function __construct(array $filter)
    {
        $this->inclusion = $filter['inclusion'];
        $this->operator = $filter['operator'];
        $this->operand = $filter['operand'];
        $this->column = $filter['column'];
    }
    public function apply_to_query(Builder $query, string $filter_type, bool $in_or_chain = \false) : void
    {
        if ($this->column->id() === 'category') {
            $this->apply_category_filter($query, $in_or_chain);
            return;
        }
        if ($this->query_value_to_match() === 'is_not_null' && $this->query_operator() === '=') {
            $query->whereNotNull($this->query_column($filter_type));
            return;
        }
        if ($this->query_value_to_match() === 'is_not_null' && $this->query_operator() === '!=') {
            $query->whereNull($this->query_column($filter_type));
            return;
        }
        $method = $this->query_method($in_or_chain, $filter_type);
        $query->{$method}($this->query_column($filter_type), $this->query_operator(), $this->query_value_to_match());
    }
    public function html_description() : string
    {
        $condition = $this->as_associative_array();
        $full_string = '';
        foreach ($condition as $key => $value) {
            if (!\in_array($key, ['inclusion', 'column', 'operator', 'operand', 'name'])) {
                continue;
            }
            if ($key == 'column') {
                continue;
            }
            $condition_string = '';
            if ($key == 'inclusion' && $value == 'include') {
                $condition_string = \esc_html__('Include', 'independent-analytics');
            } elseif ($key == 'inclusion' && $value == 'exclude') {
                $condition_string = \esc_html__('Exclude', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'lesser') {
                $condition_string = '<';
            } elseif ($key == 'operator' && $value == 'greater') {
                $condition_string = '>';
            } elseif ($key == 'operator' && $value == 'equal') {
                $condition_string = '=';
            } elseif ($key == 'operator' && $value == 'on') {
                $condition_string = \esc_html__('On', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'before') {
                $condition_string = \esc_html__('Before', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'after') {
                $condition_string = \esc_html__('After', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'contains') {
                $condition_string = \esc_html__('Contains', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'exact') {
                $condition_string = \esc_html__('Exactly matches', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'is') {
                $condition_string = \esc_html__('Is', 'independent-analytics');
            } elseif ($key == 'operator' && $value == 'isnt') {
                $condition_string = \esc_html__("Isn't", 'independent-analytics');
            } elseif ($key == 'operand' && $this->column->options()) {
                $condition_string = $this->column->options()->label_for($value);
            } elseif ($key == 'operand' && $condition['column'] == 'date') {
                try {
                    $date = \DateTime::createFromFormat('U', $value);
                    $condition_string = $date->format(WordPress_Site_Date_Format_Pattern::for_php());
                } catch (\Throwable $e) {
                    $condition_string = $value;
                }
            } else {
                $condition_string = $value;
            }
            if ($key == 'name' || $key == 'operand') {
                $condition_string = '<strong>' . $condition_string . '</strong> ';
            }
            $full_string .= $condition_string . ' ';
        }
        return \trim($full_string);
    }
    public function column() : string
    {
        return $this->column->id();
    }
    public function is_concrete_column() : bool
    {
        return $this->column->is_concrete_column();
    }
    public function is_calculated_column() : bool
    {
        return !$this->column->is_concrete_column();
    }
    public function as_associative_array() : array
    {
        // The order of the elements here matters
        return ['inclusion' => $this->inclusion, 'name' => $this->column->name(), 'column' => $this->column->id(), 'operator' => $this->operator, 'operand' => $this->operand];
    }
    private function query_method(bool $in_or_chain, string $filter_type) : string
    {
        if ($filter_type === \IAWP\Rows\Filter::$AGGREGATE_FILTER) {
            if ($in_or_chain) {
                return 'orHaving';
            }
            return 'having';
        }
        if ($in_or_chain) {
            return 'orWhere';
        }
        return 'where';
    }
    private function query_column(string $filter_type) : string
    {
        $column = $this->column->separate_database_column() ?? $this->column->separate_filter_column() ?? $this->column->id();
        if ($filter_type === \IAWP\Rows\Filter::$OUTER_FILTER) {
            $column = Str::afterLast($column, '.');
        }
        return $column;
    }
    // Use early returns here to make it more obvious what $result value is actually used
    // without reading to the end
    private function query_operator() : string
    {
        $operator = $this->operator;
        $result = '';
        if ($operator === 'equal' || $operator === 'is' || $operator === 'exact' || $operator === 'on') {
            $result = '=';
        }
        if ($operator === 'contains') {
            $result = 'like';
        }
        if ($operator === 'isnt') {
            $result = '!=';
        }
        if ($operator === 'greater' || $operator === 'after') {
            $result = '>';
        }
        if ($operator === 'lesser' || $operator === 'before') {
            $result = '<';
        }
        if ($this->inclusion === 'exclude') {
            if ($result === '=') {
                return '!=';
            } elseif ($result === '!=') {
                return '=';
            } elseif ($result === '>') {
                return '<=';
            } elseif ($result === '<') {
                return '>=';
            } elseif ($result === 'like') {
                return 'not like';
            }
        }
        return $result;
    }
    private function query_value_to_match() : string
    {
        if ($this->operator === 'contains') {
            return '%' . $this->operand . '%';
        }
        // if ($this->column->id() === 'cached_date') {
        if ($this->column->id() === 'date') {
            try {
                $date = \DateTime::createFromFormat('U', $this->operand);
            } catch (\Throwable $e) {
                $date = new \DateTime();
            }
            return $date->format('Y-m-d');
        }
        // ID is fine here, but should use whatever method gives the database column
        if (\in_array($this->column->id(), ['wc_gross_sales', 'wc_refunded_amount', 'wc_net_sales', 'wc_earnings_per_visitor', 'wc_average_order_volume'])) {
            return \strval(\floatval($this->operand) * 100);
        }
        return $this->operand;
    }
    private function apply_category_filter(Builder $query, bool $in_or_chain) : void
    {
        $wp_query = new \WP_Query(['posts_per_page' => -1, 'cat' => $this->operand, 'fields' => 'ids']);
        $post_ids = $wp_query->posts;
        $include = $this->inclusion === 'include';
        $is = $this->operator === 'is';
        $method = $in_or_chain ? 'orWhere' : 'where';
        if ($include === $is) {
            $query->{$method}(function (Builder $query) use($post_ids) {
                $query->whereIn('singular_id', $post_ids);
            });
        } else {
            $query->{$method}(function (Builder $query) use($post_ids) {
                $query->whereNotIn('singular_id', $post_ids)->orWhereNull('singular_id');
            });
        }
    }
}
