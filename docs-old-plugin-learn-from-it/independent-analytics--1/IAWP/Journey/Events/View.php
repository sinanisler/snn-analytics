<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Obj;
use IAWP\Utils\Timezone;
/** @internal */
class View extends \IAWP\Journey\Events\Event
{
    private int $session_id;
    private string $viewed_at;
    private ?string $next_viewed_at;
    private string $cached_title;
    private ?string $cached_url;
    public function __construct(object $record)
    {
        $this->session_id = $record->session_id;
        $this->viewed_at = $record->viewed_at;
        $this->next_viewed_at = $record->next_viewed_at ?? null;
        $this->cached_title = $record->cached_title;
        $this->cached_url = $record->cached_url ?? null;
    }
    public function type() : string
    {
        return 'view';
    }
    public function label() : string
    {
        return \__('View', 'independent-analytics');
    }
    public function created_at() : ?CarbonImmutable
    {
        return CarbonImmutable::parse($this->viewed_at)->timezone(Timezone::site_timezone());
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_render('journeys.timeline.view', ['event' => $this]);
    }
    public function duration() : ?string
    {
        if ($this->next_viewed_at === null) {
            return null;
        }
        $date = CarbonImmutable::parse($this->viewed_at);
        $next_date = CarbonImmutable::parse($this->next_viewed_at);
        $interval = $date->diffAsCarbonInterval($next_date);
        return $interval->cascade()->forHumans(['short' => \true]);
    }
    public function title() : string
    {
        return $this->cached_title;
    }
    public function url() : ?string
    {
        return $this->cached_url;
    }
    public static function from_session(int $session_id) : array
    {
        $query = Illuminate_Builder::new()->select(['sessions.session_id', 'views.viewed_at', 'views.next_viewed_at', 'resources.cached_title', 'resources.cached_url'])->from(Tables::sessions(), 'sessions')->leftJoin(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->leftJoin(Tables::resources() . ' AS resources', 'views.resource_id', '=', 'resources.id')->where('sessions.session_id', '=', $session_id);
        $records = $query->get()->all();
        return \array_map(function ($record) {
            return new \IAWP\Journey\Events\View(Obj::empty_strings_to_null($record));
        }, $records);
    }
}
