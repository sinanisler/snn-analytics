<?php

namespace IAWP\Models;

use IAWPSCOPED\Carbon\CarbonImmutable;
use IAWPSCOPED\Carbon\CarbonInterval;
use IAWP\Favicon\Favicon;
use IAWP\Icon_Directory_Factory;
use IAWP\Journey\EngagementScore;
use IAWP\Utils\Currency;
use IAWP\Utils\Timezone;
/** @internal */
class Journey extends \IAWP\Models\Model
{
    protected $row;
    private $created_at;
    public function __construct($row)
    {
        $this->row = $row;
        $this->created_at = $row->created_at;
        if (\is_string($row->duration)) {
            $row->duration = (int) $row->duration;
        }
    }
    public function id() : int
    {
        return $this->row->session_id;
    }
    public function table_type() : string
    {
        return 'journeys';
    }
    /**
     * @return string Time ago the session was started
     */
    public function session_started_at() : string
    {
        $created_at = CarbonImmutable::parse($this->created_at)->timezone(Timezone::site_timezone());
        return $created_at->diffForHumans();
    }
    public function landing_page() : string
    {
        return $this->row->cached_title;
    }
    public function referrer()
    {
        return $this->row->referrer;
    }
    public function domain()
    {
        return \esc_url($this->row->domain);
    }
    public function is_direct() : bool
    {
        return $this->row->referrer_type === 'Direct';
    }
    public function referrer_favicon_url() : ?string
    {
        return Favicon::for($this->row->domain)->url();
    }
    public function fallback_favicon_color_id() : int
    {
        $options = [1, 2, 3, 4, 5];
        return $options[\abs(\crc32($this->row->domain ?? '')) % \count($options)];
    }
    public function has_domain()
    {
        $url = $this->domain();
        return \is_string($url) && \strlen($url) > 0;
    }
    public function views()
    {
        return $this->row->views;
    }
    public function views_engagement_score() : int
    {
        return EngagementScore::for_session_total_views($this->row->views);
    }
    public function duration() : ?string
    {
        if (!\is_int($this->row->duration)) {
            return null;
        }
        $interval = CarbonInterval::seconds($this->row->duration);
        return $interval->cascade()->forHumans(['short' => \true]);
    }
    public function duration_engagement_score() : int
    {
        return EngagementScore::for_session_duration($this->row->duration);
    }
    public function duration_in_seconds() : ?int
    {
        if (!\is_int($this->row->duration)) {
            return null;
        }
        return $this->row->duration;
    }
    public function has_link()
    {
        if ($this->referrer_url() === '') {
            return \false;
        }
        if ($this->row->referrer_type === 'Ad') {
            return \false;
        }
        return \true;
    }
    public function referrer_url()
    {
        return $this->row->referrer_url;
    }
    public function has_conversion() : bool
    {
        return $this->has_order() || $this->has_click() || $this->has_form_submission();
    }
    public function has_order() : bool
    {
        return $this->row->orders > 0;
    }
    public function has_click() : bool
    {
        return $this->row->clicks > 0;
    }
    public function has_form_submission() : bool
    {
        return $this->row->form_submissions > 0;
    }
    public function gross_sales() : string
    {
        $gross_sales = $this->row->wc_gross_sales ?? 0;
        return Currency::format($gross_sales, \false);
    }
    public function refunded_amount() : ?string
    {
        $refunded_amount = $this->row->wc_refunded_amount ?? null;
        if ($refunded_amount === 0) {
            return null;
        }
        return Currency::format($refunded_amount, \false);
    }
    public function country()
    {
        return $this->row->country ?? \__('Unknown country', 'independent-analytics');
    }
    public function country_code()
    {
        return $this->row->country_code;
    }
    public function country_flag_url()
    {
        $country_code = $this->row->country_code ?? '';
        return Icon_Directory_Factory::flags()->find_icon_url($country_code);
    }
    public function device_type()
    {
        return $this->row->device_type ?? \__('Unknown device type', 'independent-analytics');
    }
    public function device_type_icon_url()
    {
        $device_type = $this->row->device_type ?? '';
        return Icon_Directory_Factory::device_types()->find_icon_url($device_type);
    }
    public function device_browser()
    {
        return $this->row->device_browser ?? \__('Unknown browser', 'independent-analytics');
    }
    public function device_browser_icon_url()
    {
        $device_browser = $this->row->device_browser ?? '';
        return Icon_Directory_Factory::browsers()->find_icon_url($device_browser);
    }
    public function utm_source() : ?string
    {
        return $this->row->utm_source ?? null;
    }
    public function examiner_title() : ?string
    {
        return '';
        // return $this->title();
    }
    public function examiner_url() : string
    {
        return '';
        // return iawp_dashboard_url([
        //     'tab'      => 'campaigns',
        //     'examiner' => $this->id(),
        // ]);
    }
}
