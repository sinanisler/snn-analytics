<?php

namespace IAWP\Statistics\Intervals;

/** @internal */
abstract class Interval
{
    public abstract function id() : string;
    public abstract function label() : string;
    public abstract function date_interval() : \DateInterval;
    public abstract function calculate_start_of_interval_for(\DateTime $original_date_time) : \DateTime;
    /**
     * Allow a subclass to define what the label should be for the data point. If the interval is year,
     * you'd want to show something like "2025", but if it's a monthly interval you'd want "March 2025".
     *
     * @param \DateTime $date_time
     *
     * @return array
     */
    public abstract function get_label_for(\DateTime $date_time) : array;
    // Todo - Maybe have an option to pass in either the class or the id for Statistics.php equality checks
    //  that currently rely on the interval method
    public function equals(\IAWP\Statistics\Intervals\Interval $interval) : bool
    {
        return $this->id() === $interval->id();
    }
    protected function format(\DateTime $date_time, string $format)
    {
        return \wp_date($format, $date_time->getTimestamp(), $date_time->getTimezone());
    }
}
