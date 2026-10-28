<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Security;
use IAWP\Utils\Timezone;
use IAWP\Utils\URL;
use IAWPSCOPED\Illuminate\Support\Arr;
/** @internal */
class Click extends \IAWP\Journey\Events\Event
{
    private int $session_id;
    private string $created_at;
    private int $click_id;
    private string $target;
    private array $rules;
    public function __construct(object $record)
    {
        $this->session_id = $record->session_id;
        $this->created_at = $record->created_at;
        $this->click_id = $record->click_id;
        $this->target = $record->target;
        $this->rules = $record->rules;
    }
    public function type() : string
    {
        return 'click';
    }
    public function label() : string
    {
        return \__('Click', 'independent-analytics');
    }
    public function created_at() : ?CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at)->timezone(Timezone::site_timezone());
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_render('journeys.timeline.click', ['event' => $this]);
    }
    public function target() : string
    {
        return $this->target;
    }
    public function is_url_target() : bool
    {
        $url = new URL($this->target);
        return $url->is_valid_url();
    }
    public function rules() : array
    {
        return $this->rules;
    }
    public function rule() : string
    {
        return Arr::first($this->rules);
    }
    public function has_multiple_rules() : bool
    {
        return \count($this->rules) > 1;
    }
    public static function from_session(int $session_id) : array
    {
        $query = Illuminate_Builder::new()->select(['sessions.session_id', 'clicks.click_id', 'clicks.created_at', 'click_targets.target', 'click_targets.protocol', 'link_rules.name'])->from(Tables::sessions(), 'sessions')->join(Tables::views() . ' AS views', 'sessions.session_id', '=', 'views.session_id')->join(Tables::clicks() . ' AS clicks', 'views.id', '=', 'clicks.view_id')->join(Tables::clicked_links() . ' AS clicked_links', 'clicks.click_id', '=', 'clicked_links.click_id')->join(Tables::links() . ' AS links', 'clicked_links.link_id', '=', 'links.id')->join(Tables::click_targets() . ' AS click_targets', 'links.click_target_id', '=', 'click_targets.click_target_id')->join(Tables::link_rules() . ' AS link_rules', 'links.link_rule_id', '=', 'link_rules.link_rule_id')->where('sessions.session_id', '=', $session_id);
        $records = $query->get()->groupBy('click_id')->map(function ($group) {
            $first = $group->first();
            if ($first->protocol === 'tel' || $first->protocol === 'sms') {
                $first->target = Security::string($first->target);
            }
            return ['session_id' => $first->session_id, 'click_id' => $first->click_id, 'created_at' => $first->created_at, 'target' => $first->target, 'rules' => $group->pluck('name')->unique()->values()->all()];
        })->all();
        return \array_map(function ($record) {
            return new \IAWP\Journey\Events\Click((object) $record);
        }, $records);
    }
}
