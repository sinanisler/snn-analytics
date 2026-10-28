<?php

namespace IAWP\Models;

use IAWP\Illuminate_Builder;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class ClickWhale_Link_Page extends \IAWP\Models\Page_Virtual
{
    private $database_record;
    public function __construct($row)
    {
        $link_page_id = (int) Str::after($row->virtual_page_id, 'clickwhale_link_page_');
        global $wpdb;
        $table_name = $wpdb->prefix . 'clickwhale_linkpages';
        $this->database_record = Illuminate_Builder::new()->from($table_name)->find($link_page_id);
        parent::__construct($row);
    }
    protected function calculate_url()
    {
        if (\is_null($this->database_record)) {
            return null;
        }
        return \esc_url(\home_url('/' . $this->database_record->slug . '/'));
    }
    protected function calculate_title()
    {
        if (\is_null($this->database_record)) {
            return 'ClickWhale Link Page';
        }
        return $this->database_record->title;
    }
    protected function calculate_type()
    {
        return 'clickwhale_link_page';
    }
    protected function calculate_type_label()
    {
        return 'ClickWhale Link Page';
    }
}
