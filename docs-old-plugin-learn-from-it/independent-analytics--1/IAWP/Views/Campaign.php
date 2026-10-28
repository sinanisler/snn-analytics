<?php

namespace IAWP\Views;

use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWPSCOPED\Illuminate\Database\Query\Builder;
/** @internal */
class Campaign
{
    public \IAWP\Views\CampaignParameters $campaign_parameters;
    public string $landing_page_title;
    public function __construct(\IAWP\Views\CampaignParameters $campaign_parameters, string $landing_page_title)
    {
        $this->campaign_parameters = $campaign_parameters;
        $this->landing_page_title = $landing_page_title;
    }
    /**
     * Fetch the existing campaign id from the database. If no existing campaign is found, create
     * one and return its id.
     *
     * @return int|null Campaign id
     */
    public function sync() : ?int
    {
        // Sync the landing page record
        $landing_page_id = $this->fetchOrCreate(Tables::landing_pages(), ['title' => $this->landing_page_title]);
        if (\is_null($landing_page_id)) {
            return null;
        }
        // Sync the utm source record
        $utm_source_id = $this->fetchOrCreate(Tables::utm_sources(), ['utm_source' => $this->campaign_parameters->utm_source()]);
        if (\is_null($utm_source_id)) {
            return null;
        }
        // Sync the utm medium record
        $utm_medium_id = $this->fetchOrCreate(Tables::utm_mediums(), ['utm_medium' => $this->campaign_parameters->utm_medium()]);
        if (\is_null($utm_medium_id)) {
            return null;
        }
        // Sync the utm campaign record
        $utm_campaign_id = $this->fetchOrCreate(Tables::utm_campaigns(), ['utm_campaign' => $this->campaign_parameters->utm_campaign()]);
        if (\is_null($utm_campaign_id)) {
            return null;
        }
        // Finally, sync the campaign record
        return $this->fetchOrCreate(Tables::campaigns(), ['landing_page_id' => $landing_page_id, 'utm_source_id' => $utm_source_id, 'utm_medium_id' => $utm_medium_id, 'utm_campaign_id' => $utm_campaign_id, 'utm_term' => $this->campaign_parameters->utm_term(), 'utm_content' => $this->campaign_parameters->utm_content()], 'campaign_id');
    }
    private function fetchOrCreate(string $table, array $values, string $primary_key_column = 'id') : ?int
    {
        Illuminate_Builder::new()->from($table)->updateOrInsert($values);
        $id = Illuminate_Builder::new()->select([$primary_key_column])->from($table)->tap(function (Builder $query) use($values) {
            foreach ($values as $key => $value) {
                $query->where($key, '=', $value);
            }
        })->value($primary_key_column);
        if (!\is_numeric($id)) {
            return null;
        }
        return (int) $id;
    }
}
