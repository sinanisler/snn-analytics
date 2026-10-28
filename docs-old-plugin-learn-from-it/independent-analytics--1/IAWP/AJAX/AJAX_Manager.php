<?php

namespace IAWP\AJAX;

use IAWP\Utils\Singleton;
/** @internal */
class AJAX_Manager
{
    use Singleton;
    /** @var AJAX[] */
    private $instances = [];
    private function __construct()
    {
        $this->instances[] = new \IAWP\AJAX\Archive_Link();
        $this->instances[] = new \IAWP\AJAX\Click_Tracking_Cache_Cleared();
        $this->instances[] = new \IAWP\AJAX\Configure_Pruner();
        $this->instances[] = new \IAWP\AJAX\Set_WooCommerce_Statuses_To_Track();
        $this->instances[] = new \IAWP\AJAX\Copy_Report();
        $this->instances[] = new \IAWP\AJAX\Create_Campaign();
        $this->instances[] = new \IAWP\AJAX\Create_Report();
        $this->instances[] = new \IAWP\AJAX\Delete_Campaign();
        $this->instances[] = new \IAWP\AJAX\Delete_Data();
        $this->instances[] = new \IAWP\AJAX\Delete_Link();
        $this->instances[] = new \IAWP\AJAX\Delete_Module();
        $this->instances[] = new \IAWP\AJAX\Delete_Report();
        $this->instances[] = new \IAWP\AJAX\Dismiss_Notice();
        $this->instances[] = new \IAWP\AJAX\Edit_Link();
        $this->instances[] = new \IAWP\AJAX\Edit_Module();
        $this->instances[] = new \IAWP\AJAX\Export_Report_Statistics();
        $this->instances[] = new \IAWP\AJAX\Export_Report_Table();
        $this->instances[] = new \IAWP\AJAX\Export_Reports();
        $this->instances[] = new \IAWP\AJAX\Filter();
        $this->instances[] = new \IAWP\AJAX\Get_Journey_Timeline();
        $this->instances[] = new \IAWP\AJAX\Get_Markup_For_Module();
        $this->instances[] = new \IAWP\AJAX\Get_Markup_For_Modules();
        $this->instances[] = new \IAWP\AJAX\Import_Reports();
        $this->instances[] = new \IAWP\AJAX\Migration_Status();
        $this->instances[] = new \IAWP\AJAX\Pause_Email_Reports();
        $this->instances[] = new \IAWP\AJAX\Preview_Email();
        $this->instances[] = new \IAWP\AJAX\Real_Time_Data();
        $this->instances[] = new \IAWP\AJAX\Refresh_Modules();
        $this->instances[] = new \IAWP\AJAX\Rename_Report();
        $this->instances[] = new \IAWP\AJAX\Reorder_Modules();
        $this->instances[] = new \IAWP\AJAX\Reset_Analytics();
        $this->instances[] = new \IAWP\AJAX\Reset_Overview();
        $this->instances[] = new \IAWP\AJAX\Save_Module();
        $this->instances[] = new \IAWP\AJAX\Save_Report();
        $this->instances[] = new \IAWP\AJAX\Set_Favorite_Report();
        $this->instances[] = new \IAWP\AJAX\Sort_Links();
        $this->instances[] = new \IAWP\AJAX\Sort_Reports();
        $this->instances[] = new \IAWP\AJAX\Test_Email();
        $this->instances[] = new \IAWP\AJAX\Update_Capabilities();
        $this->instances[] = new \IAWP\AJAX\Update_User_Settings();
    }
    public function get_action_signatures() : array
    {
        $action_signatures = [];
        foreach ($this->instances as $instance) {
            $action_signatures = \array_merge($action_signatures, $instance->get_action_signature());
        }
        return $action_signatures;
    }
}
