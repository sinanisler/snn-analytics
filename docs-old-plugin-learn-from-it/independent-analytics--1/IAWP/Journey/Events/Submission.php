<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Form_Submissions\Form;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Obj;
use IAWP\Utils\Timezone;
/** @internal */
class Submission extends \IAWP\Journey\Events\Event
{
    private int $session_id;
    private string $created_at;
    private string $form_title;
    private int $plugin_id;
    public function __construct(object $record)
    {
        $this->session_id = $record->session_id;
        $this->created_at = $record->created_at;
        $this->form_title = $record->form_title;
        $this->plugin_id = $record->plugin_id;
    }
    public function type() : string
    {
        return 'submission';
    }
    public function label() : string
    {
        return \__('Form', 'independent-analytics');
    }
    public function created_at() : ?CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at)->timezone(Timezone::site_timezone());
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_render('journeys.timeline.submission', ['event' => $this]);
    }
    public function form_title() : string
    {
        return $this->form_title;
    }
    public function plugin_name() : string
    {
        return Form::find_plugin_by_id($this->plugin_id)['name'] ?? '';
    }
    public static function from_session(int $session_id) : array
    {
        $query = Illuminate_Builder::new()->select(['form_submissions.session_id', 'form_submissions.created_at', 'forms.cached_form_title AS form_title', 'forms.plugin_id'])->from(Tables::form_submissions(), 'form_submissions')->leftJoin(Tables::forms() . ' AS forms', 'form_submissions.form_id', '=', 'forms.form_id')->where('form_submissions.session_id', '=', $session_id);
        $records = $query->get()->all();
        return \array_map(function ($record) {
            return new \IAWP\Journey\Events\Submission(Obj::empty_strings_to_null($record));
        }, $records);
    }
}
