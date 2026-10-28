<?php

namespace IAWP\Models;

/** @internal */
class Device extends \IAWP\Models\Model
{
    use \IAWP\Models\Universal_Model_Columns;
    protected $row;
    private $type;
    private $os;
    private $browser;
    public function __construct($row)
    {
        $this->row = $row;
        $this->type = $row->device_type ?? null;
        $this->os = $row->os ?? null;
        $this->browser = $row->browser ?? null;
    }
    public function id() : int
    {
        return $this->row->device_type_id ?? $this->row->device_os_id ?? $this->row->device_browser_id;
    }
    public function table_type() : string
    {
        return 'devices';
    }
    public function device_type()
    {
        return $this->type;
    }
    public function browser()
    {
        return $this->browser;
    }
    public function os()
    {
        return $this->os;
    }
    public function examiner_title() : ?string
    {
        return $this->type ?? $this->os ?? $this->browser;
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'devices', 'examiner' => $this->id()]);
    }
}
