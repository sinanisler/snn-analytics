<?php

namespace IAWP;

use IAWP\Models\Geo;
/** @internal */
class Map
{
    private $country_data;
    private $title;
    private $is_showing_skeleton_ui;
    /**
     * @param Geo[] $geos
     * @param $title
     */
    public function __construct(array $country_data, $title = null, bool $is_showing_skeleton_ui = \false)
    {
        $this->country_data = $country_data;
        $this->title = $title;
        $this->is_showing_skeleton_ui = $is_showing_skeleton_ui;
    }
    public function get_html()
    {
        if ($this->is_showing_skeleton_ui) {
            $country_data = [];
        } else {
            $country_data = $this->country_data;
        }
        \ob_start();
        ?>
        <div class="chart-container">
            <div class="chart-inner chart-inner--map">
                <div id="independent-analytics-chart"
                     data-controller="map"
                     data-map-data-value="<?php 
        echo \esc_attr(\json_encode($country_data));
        ?>"
                     data-map-flags-url-value="<?php 
        echo \IAWPSCOPED\iawp_url_to('/img/flags');
        ?>"
                     data-map-locale-value="<?php 
        echo \get_bloginfo('language');
        ?>"
                >
                    <div data-map-target="chart"></div>
                </div>
            </div>
        </div><?php 
        $html = \ob_get_contents();
        \ob_end_clean();
        return $html;
    }
}
