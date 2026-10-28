<?php

namespace IAWP\Admin_Page;

use IAWP\Date_Range\Relative_Date_Range;
use IAWP\Tables\Table_Journeys;
/** @internal */
class Visitor_Page extends \IAWP\Admin_Page\Admin_Page
{
    protected function render_page()
    {
        $visitor_id = $this->visitor_id();
        $session_id = $this->session_id();
        if (!\is_int($visitor_id)) {
            echo '';
            return;
        }
        $table = new Table_Journeys();
        $sort_configuration = $table->sanitize_sort_parameters('created_at', 'DESC');
        $date_range = new Relative_Date_Range('ALL_TIME');
        $rows_class = $table->group()->rows_class();
        $rows_query = new $rows_class($date_range, $sort_configuration, null, []);
        $rows_query->limit_to_visitor($visitor_id);
        $rows = $rows_query->rows();
        echo \IAWPSCOPED\iawp_render('journeys.visitor-page', ['title' => \__('Visitor', 'independent-analytics') . ' #' . $visitor_id, 'rows' => $table->get_rendered_template($rows, \true, $sort_configuration->column(), $sort_configuration->direction()), 'session_id' => $session_id]);
    }
    private function visitor_id() : ?int
    {
        $visitor_id = $_GET['visitor'] ?? null;
        if (\ctype_digit($visitor_id)) {
            return (int) $visitor_id;
        }
        return null;
    }
    private function session_id() : ?int
    {
        $session_id = $_GET['session'] ?? null;
        if (\ctype_digit($session_id)) {
            return (int) $session_id;
        }
        return null;
    }
}
