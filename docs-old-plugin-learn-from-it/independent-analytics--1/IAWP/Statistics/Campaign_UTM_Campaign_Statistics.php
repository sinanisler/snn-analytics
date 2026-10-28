<?php

namespace IAWP\Statistics;

/** @internal */
class Campaign_UTM_Campaign_Statistics extends \IAWP\Statistics\Statistics
{
    protected function required_column() : ?string
    {
        return 'campaigns.utm_campaign_id';
    }
}
