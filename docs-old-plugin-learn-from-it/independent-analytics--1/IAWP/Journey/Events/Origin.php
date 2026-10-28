<?php

namespace IAWP\Journey\Events;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWP\Icon_Directory_Factory;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Obj;
use IAWP\Utils\Timezone;
use IAWP\Utils\URL;
/** @internal */
class Origin extends \IAWP\Journey\Events\Event
{
    private int $session_id;
    private int $visitor_id;
    private string $created_at;
    private int $session_count;
    private ?string $referrer;
    private ?string $domain;
    private ?string $referrer_type;
    private ?string $utm_term;
    private ?string $utm_content;
    private ?string $utm_campaign;
    private ?string $utm_source;
    private ?string $utm_medium;
    private ?string $country;
    private ?string $country_code;
    private ?string $device_type;
    private ?string $device_browser;
    public function __construct(object $record)
    {
        $this->session_id = $record->session_id;
        $this->visitor_id = $record->visitor_id;
        $this->created_at = $record->created_at;
        $this->session_count = $record->session_count;
        $this->referrer = $record->referrer ?? null;
        $this->domain = $record->domain ?? null;
        $this->referrer_type = $record->referrer_type ?? null;
        $this->utm_term = $record->utm_term ?? null;
        $this->utm_content = $record->utm_content ?? null;
        $this->utm_campaign = $record->utm_campaign ?? null;
        $this->utm_source = $record->utm_source ?? null;
        $this->utm_medium = $record->utm_medium ?? null;
        $this->country = $record->country ?? null;
        $this->country_code = $record->country_code ?? null;
        $this->device_type = $record->device_type ?? null;
        $this->device_browser = $record->device_browser ?? null;
    }
    public function type() : string
    {
        return 'origin';
    }
    public function label() : string
    {
        if ($this->referrer === null) {
            return \__('Origin', 'independent-analytics');
        }
        return $this->referrer;
    }
    public function referrer_url() : ?string
    {
        if ($this->domain === null) {
            return null;
        }
        $value = \sanitize_url($this->domain, ['https']);
        $url = new URL($value);
        if (!$url->is_valid_url()) {
            return null;
        }
        return $url->get_url();
    }
    public function created_at() : ?CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at)->timezone(Timezone::site_timezone());
    }
    public function html() : string
    {
        return \IAWPSCOPED\iawp_render('journeys.timeline.origin', ['event' => $this]);
    }
    public function has_utm_parameters() : bool
    {
        $properties = [$this->utm_term, $this->utm_content, $this->utm_campaign, $this->utm_source, $this->utm_medium];
        foreach ($properties as $property) {
            if (\is_string($property)) {
                return \true;
            }
        }
        return \false;
    }
    public function utm_term() : ?string
    {
        return $this->utm_term;
    }
    public function utm_content() : ?string
    {
        return $this->utm_content;
    }
    public function utm_campaign() : ?string
    {
        return $this->utm_campaign;
    }
    public function utm_source() : ?string
    {
        return $this->utm_source;
    }
    public function utm_medium() : ?string
    {
        return $this->utm_medium;
    }
    public function visitor_id() : int
    {
        return $this->visitor_id;
    }
    public function country() : ?string
    {
        return $this->country;
    }
    public function country_flag_url() : ?string
    {
        if ($this->country_code === null) {
            return null;
        }
        return Icon_Directory_Factory::flags()->find_icon_url($this->country_code);
    }
    public function device_type() : ?string
    {
        return $this->device_type;
    }
    public function session_count() : int
    {
        return $this->session_count;
    }
    public function device_type_url() : ?string
    {
        if ($this->device_type === null) {
            return null;
        }
        return Icon_Directory_Factory::device_types()->find_icon_url($this->device_type);
    }
    public function device_browser() : ?string
    {
        return $this->device_browser;
    }
    public function device_browser_url() : ?string
    {
        if ($this->device_browser === null) {
            return null;
        }
        return Icon_Directory_Factory::browsers()->find_icon_url($this->device_browser);
    }
    public static function from_session(int $session_id) : ?self
    {
        $session_count = Illuminate_Builder::new()->selectRaw('count(*)')->from(Tables::sessions(), 'visitor_sessions')->whereColumn('visitor_sessions.visitor_id', 'sessions.visitor_id');
        $query = Illuminate_Builder::new()->select(['sessions.session_id', 'sessions.visitor_id', 'sessions.created_at', 'referrers.referrer', 'referrers.domain', 'referrer_types.referrer_type', 'campaigns.utm_term', 'campaigns.utm_content', 'utm_campaigns.utm_campaign', 'utm_sources.utm_source', 'utm_mediums.utm_medium', 'countries.country_code', 'countries.country', 'device_types.device_type', 'device_browsers.device_browser'])->selectSub($session_count, 'session_count')->from(Tables::sessions(), 'sessions')->leftJoin(Tables::referrers() . ' AS referrers', 'sessions.referrer_id', '=', 'referrers.id')->leftJoin(Tables::referrer_types() . ' AS referrer_types', 'referrers.referrer_type_id', '=', 'referrer_types.id')->leftJoin(Tables::campaigns() . ' AS campaigns', 'sessions.campaign_id', '=', 'campaigns.campaign_id')->leftJoin(Tables::utm_campaigns() . ' AS utm_campaigns', 'campaigns.utm_campaign_id', '=', 'utm_campaigns.id')->leftJoin(Tables::utm_sources() . ' AS utm_sources', 'campaigns.utm_source_id', '=', 'utm_sources.id')->leftJoin(Tables::utm_mediums() . ' AS utm_mediums', 'campaigns.utm_medium_id', '=', 'utm_mediums.id')->leftJoin(Tables::countries() . ' AS countries', 'sessions.country_id', '=', 'countries.country_id')->leftJoin(Tables::device_types() . ' AS device_types', 'sessions.device_type_id', '=', 'device_types.device_type_id')->leftJoin(Tables::device_browsers() . ' AS device_browsers', 'sessions.device_browser_id', '=', 'device_browsers.device_browser_id')->where('sessions.session_id', '=', $session_id);
        $record = $query->first();
        if ($record === null) {
            return null;
        }
        return new \IAWP\Journey\Events\Origin(Obj::empty_strings_to_null($record));
    }
}
