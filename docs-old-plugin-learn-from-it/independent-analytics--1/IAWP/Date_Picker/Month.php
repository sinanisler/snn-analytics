<?php

namespace IAWP\Date_Picker;

/** @internal */
class Month
{
    private $date;
    private $first_month;
    public function __construct(\DateTime $date, \DateTime $first_month)
    {
        $this->date = $date;
        $this->first_month = $first_month;
    }
    public function name() : string
    {
        return \IAWPSCOPED\iawp()->date_i18n('F Y', $this->date);
    }
    public function date_string() : string
    {
        return $this->date->format('Y-m');
    }
    public function days() : \DatePeriod
    {
        $start = $this->date->modify('first day of this month');
        $end = (clone $this->date)->modify('last day of this month');
        $end->setTime(23, 59, 59);
        $interval = new \DateInterval('P1D');
        // 1 day interval
        return new \DatePeriod($start, $interval, $end);
    }
    public function extra_cells() : int
    {
        $user_dow = \IAWPSCOPED\iawp()->get_option('iawp_dow', 0);
        $start = $this->date->modify('first day of this month');
        $month_dow = $start->format('w');
        if ($month_dow >= $user_dow) {
            return $month_dow - $user_dow;
        } else {
            return 7 - ($user_dow - $month_dow);
        }
    }
    public function month_class() : string
    {
        $now = new \DateTime('now');
        $month = clone $this->date;
        $class = 'iawp-calendar-month';
        if ($month->format('Y-m') === $this->first_month->format('Y-m')) {
            $class .= ' iawp-first-month';
        }
        if ($now->format('Y-m') === $month->format('Y-m')) {
            $class .= ' iawp-current iawp-last-month';
        }
        // Modifying $month here
        if ($now->format('Y n') === $month->modify('first day of +1 month')->format('Y n')) {
            $class .= ' iawp-previous';
        }
        return $class;
    }
    public function day_class(\DateTime $day, string $first_data, string $start_date, string $end_date) : string
    {
        $date = $day->format('Y-m-d');
        $class = 'iawp-day iawp-cell';
        if ($date === \date('Y-m-d')) {
            $class .= ' iawp-today';
        }
        if ($day->format('j') == '1') {
            $class .= ' first-of-month';
        }
        if ($date === $first_data) {
            $class .= ' iawp-first-data';
        }
        if ($date < $first_data || $date > \date('Y-m-d')) {
            $class .= ' out-of-range';
        }
        if ($date === $start_date) {
            $class .= ' iawp-start';
            if ($date === $end_date) {
                $class .= ' iawp-end';
            }
        } elseif ($date === $end_date) {
            $class .= ' iawp-end';
        } elseif ($date > $start_date && $date < $end_date) {
            $class .= ' in-range';
        }
        return $class;
    }
    public function days_of_week() : string
    {
        $days = [];
        $html = '';
        // Get the correct HTML
        for ($i = 0; $i < 7; $i++) {
            $days[] = '<span class="iawp-day-name">' . \date_i18n("D", \strtotime("Sunday +{$i} days")) . '</span>';
        }
        // Shift based on the user's selection
        for ($i = 0; $i < \IAWPSCOPED\iawp()->get_option('iawp_dow', 0); $i++) {
            $first_day = \array_shift($days);
            \array_push($days, $first_day);
        }
        // Create the HTMl string
        foreach ($days as $day) {
            $html .= $day;
        }
        return $html;
    }
}
