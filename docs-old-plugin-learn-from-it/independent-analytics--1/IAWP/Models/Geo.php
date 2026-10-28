<?php

namespace IAWP\Models;

/** @internal */
class Geo extends \IAWP\Models\Model
{
    use \IAWP\Models\Universal_Model_Columns;
    protected $row;
    private $continent;
    private $country;
    private $country_code;
    private $subdivision;
    private $city;
    public function __construct($row)
    {
        $this->row = $row;
        $this->continent = $row->continent;
        $this->country = $row->country;
        $this->country_code = $row->country_code;
        $this->subdivision = $row->subdivision ?? '';
        $this->city = $row->city ?? '';
    }
    public function id() : int
    {
        return $this->row->city_id ?? $this->row->country_id;
    }
    public function table_type() : string
    {
        return 'geo';
    }
    public function is_country() : bool
    {
        return \strlen($this->subdivision) === 0 && \strlen($this->city) === 0;
    }
    public function continent()
    {
        return $this->continent;
    }
    public function country()
    {
        return $this->country;
    }
    public function country_code()
    {
        return $this->country_code;
    }
    public function subdivision()
    {
        return $this->subdivision;
    }
    public function city()
    {
        return $this->city;
    }
    public function examiner_title() : ?string
    {
        return $this->is_country() ? $this->country() : $this->city();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'geo', 'examiner' => $this->id()]);
    }
}
