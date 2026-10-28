<?php

namespace IAWP;

use IAWP\Models\Geo;
/** @internal */
class Map_Data
{
    private $geos;
    private $country_data;
    /**
     * @param Geo[] $geos
     */
    public function __construct(array $geos)
    {
        $this->geos = $geos;
        $this->country_data = $this->calculate_country_data($geos);
    }
    public function get_country_data() : array
    {
        return $this->country_data;
    }
    public function calculate_country_data($geos) : array
    {
        $countries = [];
        foreach ($geos as $geo) {
            $existing_country_index = null;
            foreach ($countries as $index => $country) {
                if ($geo->country_code() === $country['country_code']) {
                    $existing_country_index = $index;
                }
            }
            if (\is_numeric($existing_country_index)) {
                $countries[$existing_country_index]['views'] += $geo->views();
                $countries[$existing_country_index]['visitors'] += $geo->visitors();
                $countries[$existing_country_index]['sessions'] += $geo->sessions();
            } else {
                $countries[] = ['country_code' => $geo->country_code(), 'country' => $geo->country(), 'flag' => \IAWP\Icon_Directory_Factory::flags()->find($geo->country_code()), 'views' => $geo->views(), 'visitors' => $geo->visitors(), 'sessions' => $geo->sessions()];
            }
        }
        return $countries;
    }
}
