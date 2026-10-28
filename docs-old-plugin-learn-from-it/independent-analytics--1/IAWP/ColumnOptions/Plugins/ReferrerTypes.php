<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
/** @internal */
class ReferrerTypes implements OptionsPlugin
{
    public function get_options() : array
    {
        return [new Option('Search', \esc_html__('Search', 'independent-analytics')), new Option('Social', \esc_html__('Social', 'independent-analytics')), new Option('AI', \esc_html__('AI', 'independent-analytics')), new Option('Referrer', \esc_html__('Referrer', 'independent-analytics')), new Option('Ad', \esc_html__('Ad', 'independent-analytics')), new Option('Direct', \esc_html__('Direct', 'independent-analytics'))];
    }
}
