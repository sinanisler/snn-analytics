<?php

namespace IAWP\Form_Submissions;

use IAWP\Illuminate_Builder;
use IAWP\Models\Visitor;
use IAWP\Query;
/** @internal */
class Submission
{
    public $plugin_id;
    public $plugin_form_id;
    public $form_title;
    public function __construct(int $plugin_id, int $plugin_form_id, string $form_title)
    {
        $this->plugin_id = $plugin_id;
        $this->plugin_form_id = $plugin_form_id;
        $this->form_title = $form_title;
    }
    public function record_submission() : void
    {
        $form_submissions_table = Query::get_table_name(Query::FORM_SUBMISSIONS);
        $visitor = Visitor::fetch_current_visitor();
        if (!$visitor->has_recorded_session()) {
            return;
        }
        Illuminate_Builder::new()->from($form_submissions_table)->insert(['form_id' => $this->get_form_id(), 'session_id' => $visitor->most_recent_session_id(), 'view_id' => $visitor->most_recent_view_id(), 'initial_view_id' => $visitor->most_recent_initial_view_id(), 'created_at' => (new \DateTime())->format('Y-m-d\\TH:i:s')]);
    }
    private function get_form_id() : int
    {
        $forms_table = Query::get_table_name(Query::FORMS);
        Illuminate_Builder::new()->from($forms_table)->updateOrInsert(['plugin_id' => $this->plugin_id, 'plugin_form_id' => $this->plugin_form_id], ['cached_form_title' => \substr($this->form_title, 0, 64)]);
        return Illuminate_Builder::new()->from($forms_table)->where(['plugin_id' => $this->plugin_id, 'plugin_form_id' => $this->plugin_form_id])->value('form_id');
    }
}
