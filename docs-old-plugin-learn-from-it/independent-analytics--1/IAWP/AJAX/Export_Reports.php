<?php

namespace IAWP\AJAX;

use IAWP\Report_Finder;
/** @internal */
class Export_Reports extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['ids'];
    }
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_export_reports';
    }
    /**
     * @inheritDoc
     */
    protected function action_callback() : void
    {
        $ids = $this->get_field('ids');
        $reports = [];
        if (\count($ids) === 0) {
            \wp_send_json_error([], 400);
        }
        foreach ($ids as $id) {
            $report = Report_Finder::new()->fetch_report_by_id($id);
            if (null === $report) {
                continue;
            }
            $reports[] = $report->to_array();
        }
        \wp_send_json_success(['json' => \json_encode(['plugin_version' => '2.14.1', 'database_version' => '52', 'export_version' => '1', 'reports' => $reports])]);
    }
}
