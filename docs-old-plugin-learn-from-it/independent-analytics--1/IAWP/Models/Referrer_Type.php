<?php

namespace IAWP\Models;

/** @internal */
class Referrer_Type extends \IAWP\Models\Model
{
    use \IAWP\Models\Universal_Model_Columns;
    protected $row;
    private $is_direct;
    private $referrer;
    private $domain;
    private $referrer_type;
    public function __construct($row)
    {
        $this->row = $row;
        $this->referrer_type = $row->referrer_type;
    }
    public function id() : int
    {
        return $this->row->referrer_type_id;
    }
    public function table_type() : string
    {
        return 'referrers';
    }
    /**
     * Return group referrer type, referrer, or direct.
     *
     * @return string Referrer type
     */
    public function referrer_type() : string
    {
        return $this->referrer_type;
    }
    public function examiner_title() : ?string
    {
        return $this->referrer_type();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'referrers', 'examiner' => $this->id()]);
    }
}
