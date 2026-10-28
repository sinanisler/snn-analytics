<?php

namespace IAWP\Statistics;

/** @internal */
class Campaign_Landing_Page_Statistics extends \IAWP\Statistics\Statistics
{
    protected function required_column() : ?string
    {
        return 'campaigns.landing_page_id';
    }
}
