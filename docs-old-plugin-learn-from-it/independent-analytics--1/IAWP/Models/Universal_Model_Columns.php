<?php

namespace IAWP\Models;

use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
trait Universal_Model_Columns
{
    /**
     * Handle dynamic column names such as those for form tracking
     */
    public function __call($name, $arguments)
    {
        if (Str::startsWith($name, 'form_submissions_for_')) {
            return $this->as_int($name);
        } elseif (Str::startsWith($name, 'form_conversion_rate_for_')) {
            return $this->as_float($name);
        }
    }
    public function form_submissions() : int
    {
        return $this->as_int('form_submissions');
    }
    public function form_conversion_rate() : float
    {
        return $this->as_float('form_conversion_rate');
    }
    public function wc_orders() : float
    {
        return $this->as_float('wc_orders');
    }
    public function wc_gross_sales() : float
    {
        return $this->as_float('wc_gross_sales');
    }
    public function wc_refunds() : float
    {
        return $this->as_float('wc_refunds');
    }
    public function wc_refunded_amount() : float
    {
        return $this->as_float('wc_refunded_amount');
    }
    public function wc_net_sales() : float
    {
        return $this->as_float('wc_net_sales');
    }
    public function wc_conversion_rate() : float
    {
        return $this->as_float('wc_conversion_rate');
    }
    public function wc_earnings_per_visitor() : float
    {
        return $this->as_float('wc_earnings_per_visitor');
    }
    public function wc_average_order_volume() : float
    {
        return $this->as_float('wc_average_order_volume');
    }
    public function views() : int
    {
        return $this->as_int('views');
    }
    public function previous_period_views() : int
    {
        return $this->as_int('previous_period_views');
    }
    public function views_growth() : float
    {
        return $this->as_float('views_growth');
    }
    public function views_per_session() : float
    {
        return $this->as_float('views_per_session');
    }
    public function visitors() : int
    {
        return $this->as_int('visitors');
    }
    public function previous_period_visitors() : int
    {
        return $this->as_int('previous_period_views');
    }
    public function visitors_growth() : float
    {
        return $this->as_float('visitors_growth');
    }
    public function clicks() : float
    {
        return $this->as_int('clicks');
    }
    public function average_session_duration() : ?int
    {
        return $this->as_int('average_session_duration', null);
    }
    public function average_session_duration_growth() : float
    {
        $current = $this->average_session_duration();
        $previous = $this->previous_period_average_session_duration();
        if ($current == 0 || $previous == 0) {
            return 0;
        } else {
            return ($current - $previous) / $previous * 100;
        }
    }
    public function average_view_duration() : ?int
    {
        return $this->as_int('average_view_duration', null);
    }
    public function average_view_duration_growth() : float
    {
        $current = $this->average_view_duration();
        $previous = $this->previous_period_average_view_duration();
        if ($current == 0 || $previous == 0) {
            return 0;
        } else {
            return ($current - $previous) / $previous * 100;
        }
    }
    public function sessions() : int
    {
        return $this->as_int('sessions');
    }
    public function previous_period_sessions() : int
    {
        return $this->as_int('previous_period_sessions');
    }
    public function sessions_growth() : float
    {
        $current = $this->sessions();
        $previous = $this->previous_period_sessions();
        if ($current == 0 || $previous == 0) {
            return 0;
        } else {
            return ($current - $previous) / $previous * 100;
        }
    }
    public function previous_period_average_session_duration() : int
    {
        return $this->as_int('previous_period_average_session_duration');
    }
    public function previous_period_average_view_duration() : int
    {
        return $this->as_int('previous_period_average_view_duration');
    }
    public function bounces() : int
    {
        return $this->as_int('bounces');
    }
    public function bounce_rate() : float
    {
        return $this->as_float('bounce_rate');
    }
    private function as_float($property, $default = 0) : ?float
    {
        return \property_exists($this->row, $property) && $this->row->{$property} !== null ? \floatval($this->row->{$property}) : $default;
    }
    private function as_int($property, $default = 0) : ?int
    {
        return \property_exists($this->row, $property) && $this->row->{$property} !== null ? \intval($this->row->{$property}) : $default;
    }
}
