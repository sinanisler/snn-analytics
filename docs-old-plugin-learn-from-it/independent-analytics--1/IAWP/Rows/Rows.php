<?php

namespace IAWP\Rows;

use IAWP\Date_Range\Date_Range;
use IAWP\Examiner_Config;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Sort_Configuration;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
/** @internal */
abstract class Rows
{
    protected $tables = Tables::class;
    protected $date_range;
    protected $number_of_rows;
    /** @var Filter[] */
    protected $filters;
    protected $sort_configuration;
    protected $filter_logic;
    protected $solo_record_id = null;
    protected $examiner_config = null;
    private $rows = null;
    public function __construct(Date_Range $date_range, Sort_Configuration $sort_configuration, ?int $number_of_rows = null, ?array $filters = null, ?string $filter_logic = null)
    {
        $this->date_range = $date_range;
        $this->sort_configuration = $sort_configuration;
        $this->number_of_rows = $number_of_rows;
        $this->filters = $filters ?? [];
        $this->filter_logic = \in_array($filter_logic, ['and', 'or']) ? $filter_logic : 'and';
    }
    protected abstract function fetch_rows() : array;
    public abstract function attach_filters(Builder $query) : void;
    protected abstract function sort_tie_breaker_column() : string;
    /**
     * Used to limit the rows to just a single record. This is useful if you want a single page,
     * referrer, etc. row, and you know the records database id.
     *
     * @param int $id
     *
     * @return void
     */
    public function limit_to(int $id) : void
    {
        $this->solo_record_id = $id;
    }
    public function for_examiner(Examiner_Config $config)
    {
        $this->examiner_config = $config;
    }
    public function rows()
    {
        if (\is_array($this->rows)) {
            return $this->rows;
        }
        $this->rows = $this->fetch_rows();
        return $this->rows;
    }
    protected function only_filtering_by_record_columns() : bool
    {
        if (\count($this->filters) === 0) {
            return \true;
        }
        foreach ($this->filters as $filter) {
            if ($filter->is_calculated_column()) {
                return \false;
            }
        }
        return \true;
    }
    protected function only_filtering_by_aggregate_columns() : bool
    {
        if (\count($this->filters) === 0) {
            return \false;
        }
        foreach ($this->filters as $filter) {
            if ($filter->is_concrete_column()) {
                return \false;
            }
        }
        return \true;
    }
    protected function filtering_by_mixed_columns() : bool
    {
        if (\count($this->filters) === 0) {
            return \false;
        }
        $has_record_column = \false;
        $has_aggregate_column = \false;
        foreach ($this->filters as $filter) {
            if ($filter->is_calculated_column()) {
                $has_aggregate_column = \true;
            } else {
                $has_record_column = \true;
            }
            if ($has_record_column && $has_aggregate_column) {
                return \true;
            }
        }
        return \false;
    }
    protected function get_current_period_iso_range() : array
    {
        return [$this->date_range->iso_start(), $this->date_range->iso_end()];
    }
    protected function appears_to_be_for_real_time_analytics() : bool
    {
        $difference_in_seconds = $this->date_range->end()->getTimestamp() - $this->date_range->start()->getTimestamp();
        $one_hour_in_seconds = 3600;
        return $difference_in_seconds < $one_hour_in_seconds;
    }
    protected function get_previous_period_iso_range() : array
    {
        return [$this->date_range->previous_period()->iso_start(), $this->date_range->previous_period()->iso_end()];
    }
    protected function get_form_submissions_query() : Builder
    {
        $form_submissions_table = Query::get_table_name(Query::FORM_SUBMISSIONS);
        return Illuminate_Builder::new()->select(['form_id', 'view_id'])->selectRaw('COUNT(*) AS form_submissions')->from($form_submissions_table, 'form_submissions')->whereBetween('created_at', $this->get_current_period_iso_range())->groupBy(['form_id', 'view_id']);
    }
    protected function apply_record_filters(Builder $query) : void
    {
        $should_apply_record_filters = $this->using_logical_and_operator() || $this->only_filtering_by_record_columns();
        if (!$should_apply_record_filters) {
            return;
        }
        if ($this->using_logical_or_operator()) {
            $query->where(function (Builder $query) {
                foreach ($this->filters as $index => $filter) {
                    $filter->apply_to_query($query, \IAWP\Rows\Filter::$RECORD_FILTER, $index > 0);
                }
            });
            return;
        }
        foreach ($this->filters as $filter) {
            if ($filter->is_concrete_column()) {
                $filter->apply_to_query($query, \IAWP\Rows\Filter::$RECORD_FILTER);
            }
        }
    }
    protected function can_order_and_limit_at_record_level() : bool
    {
        return $this->sort_configuration->the_column()->is_concrete_column() && $this->only_filtering_by_record_columns();
    }
    protected function apply_aggregate_filters(Builder $query) : void
    {
        $should_apply_aggregate_filters = $this->using_logical_and_operator() || $this->using_logical_or_operator() && $this->only_filtering_by_aggregate_columns();
        if (!$should_apply_aggregate_filters) {
            return;
        }
        if ($this->using_logical_or_operator()) {
            $query->where(function (Builder $query) {
                foreach ($this->filters as $index => $filter) {
                    $filter->apply_to_query($query, \IAWP\Rows\Filter::$AGGREGATE_FILTER, $index > 0);
                }
            });
            return;
        }
        foreach ($this->filters as $filter) {
            if ($filter->is_calculated_column()) {
                $filter->apply_to_query($query, \IAWP\Rows\Filter::$AGGREGATE_FILTER);
            }
        }
    }
    protected function apply_or_filters(Builder $query) : void
    {
        $query->where(function (Builder $query) {
            foreach ($this->filters as $index => $filter) {
                $filter->apply_to_query($query, \IAWP\Rows\Filter::$OUTER_FILTER, $index > 0);
            }
        });
    }
    protected function using_logical_or_operator() : bool
    {
        return $this->filter_logic === 'or' && \count($this->filters) > 1;
    }
    protected function using_logical_and_operator() : bool
    {
        return !$this->using_logical_or_operator();
    }
    protected function apply_order_and_limit(Builder $query, string $sort_column) : void
    {
        $query->when($this->sort_configuration->the_column()->is_nullable(), function (Builder $query) use($sort_column) {
            $query->orderByRaw("CASE WHEN {$sort_column} IS NULL THEN 1 ELSE 0 END");
        })->orderBy($sort_column, $this->sort_configuration->direction())->orderBy($this->sort_tie_breaker_column())->when(\is_int($this->number_of_rows), function (Builder $query) {
            $query->limit($this->number_of_rows);
        });
    }
}
