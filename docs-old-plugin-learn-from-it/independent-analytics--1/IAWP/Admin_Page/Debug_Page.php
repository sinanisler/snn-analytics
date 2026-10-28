<?php

namespace IAWP\Admin_Page;

use IAWP\Utils\Request;
/** @internal */
class Debug_Page extends \IAWP\Admin_Page\Admin_Page
{
    protected function render_page()
    {
        echo \IAWPSCOPED\iawp_blade()->run('debug', ['detected_ip' => Request::ip(), 'custom_ip_header' => $this->custom_ip_header(), 'header_details' => $this->header_details()]);
    }
    private function custom_ip_header() : string
    {
        $custom_ip_header = Request::custom_ip_header();
        if ($custom_ip_header === null) {
            $custom_ip_header = '';
        }
        return $custom_ip_header;
    }
    private function header_details() : array
    {
        $result = [];
        foreach (Request::ip_headers() as $header) {
            if (isset($_SERVER[$header])) {
                $result[] = [$header, $_SERVER[$header]];
            } else {
                $result[] = [$header, ''];
            }
        }
        return $result;
    }
}
