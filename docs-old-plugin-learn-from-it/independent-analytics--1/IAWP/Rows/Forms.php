<?php

namespace IAWP\Rows;

use IAWP\Illuminate_Builder;
use IAWP\Models\Form;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
/** @internal */
class Forms extends \IAWP\Rows\Rows
{
    public function attach_filters(Builder $query) : void
    {
        // There are no filters to attach as this is used for the email report and not a dashboard
    }
    protected function fetch_rows() : array
    {
        $rows = $this->query()->get()->all();
        return \array_map(function ($row) {
            return new Form($row);
        }, $rows);
    }
    protected function sort_tie_breaker_column() : string
    {
        return 'form_title';
    }
    private function query(?bool $skip_pagination = \false) : Builder
    {
        if ($skip_pagination) {
            $this->number_of_rows = null;
        }
        $query = Illuminate_Builder::new()->select(['forms.form_id', 'forms.cached_form_title AS form_title'])->selectRaw('COUNT(DISTINCT form_submissions.form_submission_id) AS submissions')->from($this->tables::forms(), 'forms')->leftJoin($this->tables::form_submissions() . ' AS form_submissions', 'form_submissions.form_id', '=', 'forms.form_id')->whereBetween('form_submissions.created_at', $this->get_current_period_iso_range())->when(\is_int($this->number_of_rows), function (Builder $query) {
            $query->limit($this->number_of_rows);
        })->orderBy($this->sort_configuration->column(), $this->sort_configuration->direction())->orderBy($this->sort_tie_breaker_column())->groupBy('forms.form_id');
        return $query;
    }
}
