<?php

namespace IAWP\Models;

use IAWP\Favicon\Favicon;
/** @internal */
class Referrer extends \IAWP\Models\Model
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
        $this->is_direct = $row->domain === '';
        $this->referrer = $row->referrer;
        $this->domain = $row->domain;
        $this->referrer_type = $row->referrer_type;
    }
    public function id() : int
    {
        return $this->row->referrer_id;
    }
    public function table_type() : string
    {
        return 'referrers';
    }
    /**
     * Return group name, referrer url, or direct.
     *
     * @return string Referrer
     */
    // Todo - This is the one the table is doing...
    public function referrer() : string
    {
        return $this->referrer;
    }
    public function referrer_url() : string
    {
        return $this->domain;
    }
    public function referrer_favicon_url() : ?string
    {
        return Favicon::for($this->domain)->url();
    }
    public function fallback_favicon_color_id() : int
    {
        $options = [1, 2, 3, 4, 5];
        return $options[\abs(\crc32($this->row->domain ?? '')) % \count($options)];
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
    public function is_direct() : bool
    {
        return $this->is_direct;
    }
    /**
     * Should the referrer record link back to the referrering domain?
     *
     * @return bool
     */
    public function has_link() : bool
    {
        return !$this->is_direct() && $this->referrer_type !== 'Ad';
    }
    public function examiner_title() : ?string
    {
        return $this->referrer();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'referrers', 'examiner' => $this->id()]);
    }
}
