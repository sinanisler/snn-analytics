<?php

namespace IAWP\Models;

/** @internal */
class Link_Pattern extends \IAWP\Models\Model
{
    protected $row;
    protected $link_name;
    protected $link_target;
    protected $link_clicks;
    public function __construct($row)
    {
        $this->row = $row;
        $this->link_name = $row->link_name;
        $this->link_target = $row->link_target ?? '';
        $this->link_clicks = \intval($row->link_clicks);
    }
    public function id() : int
    {
        return $this->row->link_rule_id;
    }
    public function table_type() : string
    {
        return 'clicks';
    }
    public function link_name() : string
    {
        return $this->link_name;
    }
    public function link_clicks() : int
    {
        return $this->link_clicks;
    }
    public function examiner_title() : ?string
    {
        return $this->link_name();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'clicks', 'examiner' => $this->id()]);
    }
}
