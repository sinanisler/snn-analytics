<?php

namespace IAWP\Models;

/** @internal */
class Campaign_UTM_Campaign extends \IAWP\Models\Model
{
    use \IAWP\Models\Universal_Model_Columns;
    protected $row;
    private $utm_campaign;
    public function __construct($row)
    {
        $this->row = $row;
        $this->utm_campaign = $row->utm_campaign;
    }
    public function id() : int
    {
        return $this->row->utm_campaign_id;
    }
    public function table_type() : string
    {
        return 'campaigns';
    }
    /*
     * Column names have shared logic between tables. So "title" for resources has the same logic
     * as "title" for campaigns. Adding is_deleted ensures that the method can be called even though
     * campaigns can never be deleted. A better code base would allow this to be removed.
     */
    public function is_deleted()
    {
        return \false;
    }
    public function utm_campaign()
    {
        return $this->utm_campaign;
    }
    /**
     * This isn't building a URL param that's used in a URL. This is building a unique id that's
     * used for uniqueness in real-times most popular campaign list.
     *
     * @return string
     */
    public function params() : string
    {
        return \http_build_query(['title' => $this->utm_campaign()]);
    }
    public function examiner_title() : ?string
    {
        return $this->utm_campaign();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'campaigns', 'examiner' => $this->id()]);
    }
}
