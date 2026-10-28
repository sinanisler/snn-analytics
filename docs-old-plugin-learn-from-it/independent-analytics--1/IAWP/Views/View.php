<?php

namespace IAWP\Views;

use IAWP\Custom_WordPress_Columns\Views_Column;
use IAWP\Illuminate_Builder;
use IAWP\Known_Referrers;
use IAWP\Models\Page;
use IAWP\Models\Page_Home;
use IAWP\Models\Page_Post_Type_Archive;
use IAWP\Models\Page_Singular;
use IAWP\Models\Visitor;
use IAWP\Query;
use IAWP\Tables;
use IAWP\Utils\Device;
use IAWP\Utils\String_Util;
use IAWP\Utils\URL;
use IAWPSCOPED\Illuminate\Database\Query\JoinClause;
/** @internal */
class View
{
    private $payload;
    private $referrer_url;
    private $visitor;
    private $campaign_parameters;
    private $viewed_at;
    private $resource;
    private $session;
    /**
     * @param array              $payload
     * @param string|null        $referrer_url
     * @param Visitor            $visitor
     * @param ?CampaignParameters $campaign_parameters
     * @param \DateTime|null     $viewed_at
     */
    public function __construct(array $payload, ?string $referrer_url, Visitor $visitor, ?\IAWP\Views\CampaignParameters $campaign_parameters, ?\DateTime $viewed_at = null)
    {
        $this->payload = $payload;
        $this->referrer_url = \is_null($referrer_url) ? '' : \trim($referrer_url);
        $this->visitor = $visitor;
        $this->campaign_parameters = $campaign_parameters;
        $this->viewed_at = $viewed_at instanceof \DateTime ? $viewed_at : new \DateTime();
        $this->resource = $this->fetch_or_create_resource();
        // If a resource can't be found or created, a view cannot be recorded
        if (\is_null($this->resource)) {
            return;
        }
        $this->session = $this->fetch_or_create_session();
        $view_id = $this->create_view();
        $this->link_with_previous_view($view_id);
        $this->set_session_total_views();
        $this->set_sessions_initial_view($view_id);
        $this->set_sessions_final_view($view_id);
        $this->set_views_postmeta($this->resource);
    }
    /**
     * @return int ID of newly created session
     */
    public function create_session() : int
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        return Illuminate_Builder::new()->from($sessions_table)->insertGetId(['visitor_id' => $this->visitor->id(), 'referrer_id' => $this->referrer_id(), 'country_id' => $this->fetch_or_create_country(), 'city_id' => $this->fetch_or_create_city(), 'campaign_id' => $this->fetch_or_create_campaign(), 'device_type_id' => Device::getInstance()->type_id(), 'device_os_id' => Device::getInstance()->os_id(), 'device_browser_id' => Device::getInstance()->browser_id(), 'is_first_session' => $this->visitor->is_first_session(), 'created_at' => $this->viewed_at()]);
    }
    public function fetch_or_create_country() : ?int
    {
        if (!$this->visitor->geoposition()->valid_location()) {
            return null;
        }
        $countries_table = Query::get_table_name(Query::COUNTRIES);
        $country_id = Illuminate_Builder::new()->from($countries_table)->where('country_code', '=', $this->visitor->geoposition()->country_code())->where('country', '=', $this->visitor->geoposition()->country())->where('continent', '=', $this->visitor->geoposition()->continent())->value('country_id');
        if (!\is_null($country_id)) {
            return $country_id;
        }
        Illuminate_Builder::new()->from($countries_table)->insertOrIgnore(['country_code' => $this->visitor->geoposition()->country_code(), 'country' => $this->visitor->geoposition()->country(), 'continent' => $this->visitor->geoposition()->continent()]);
        return Illuminate_Builder::new()->from($countries_table)->where('country_code', '=', $this->visitor->geoposition()->country_code())->where('country', '=', $this->visitor->geoposition()->country())->where('continent', '=', $this->visitor->geoposition()->continent())->value('country_id');
    }
    public function fetch_or_create_city() : ?int
    {
        if (!$this->visitor->geoposition()->valid_location()) {
            return null;
        }
        $country_id = $this->fetch_or_create_country();
        $cities_table = Query::get_table_name(Query::CITIES);
        $city_id = Illuminate_Builder::new()->from($cities_table)->where('country_id', $country_id)->where('subdivision', '=', $this->visitor->geoposition()->subdivision())->where('city', '=', $this->visitor->geoposition()->city())->value('city_id');
        if (!\is_null($city_id)) {
            return $city_id;
        }
        Illuminate_Builder::new()->from($cities_table)->insertOrIgnore(['country_id' => $country_id, 'subdivision' => $this->visitor->geoposition()->subdivision(), 'city' => $this->visitor->geoposition()->city()]);
        return Illuminate_Builder::new()->from($cities_table)->where('country_id', $country_id)->where('subdivision', '=', $this->visitor->geoposition()->subdivision())->where('city', '=', $this->visitor->geoposition()->city())->value('city_id');
    }
    /**
     * Fetch the last view, if any.
     *
     * @return int|null
     */
    private function fetch_last_viewed_resource() : ?int
    {
        global $wpdb;
        $views_table = Query::get_table_name(Query::VIEWS);
        $session = $this->fetch_current_session();
        if (\is_null($session)) {
            return null;
        }
        $view = $wpdb->get_row($wpdb->prepare("\n                SELECT * FROM {$views_table} WHERE session_id = %d ORDER BY viewed_at DESC LIMIT 1\n            ", $session->session_id));
        if (\is_null($view)) {
            return null;
        }
        return $view->resource_id;
    }
    private function viewed_at() : string
    {
        return $this->viewed_at->format('Y-m-d\\TH:i:s');
    }
    private function link_with_previous_view($view_id) : void
    {
        global $wpdb;
        $views_tables = Query::get_table_name(Query::VIEWS);
        $sessions_tables = Query::get_table_name(Query::SESSIONS);
        $session = Illuminate_Builder::new()->from($sessions_tables)->where('session_id', '=', $this->session)->first();
        if (\is_null($session)) {
            return;
        }
        $final_view_id = $session->final_view_id;
        $initial_view_id = $session->initial_view_id;
        if (!\is_null($final_view_id)) {
            $wpdb->update($views_tables, ['next_view_id' => $view_id, 'next_viewed_at' => $this->viewed_at()], ['id' => $final_view_id]);
        } elseif (!\is_null($initial_view_id)) {
            $wpdb->update($views_tables, ['next_view_id' => $view_id, 'next_viewed_at' => $this->viewed_at()], ['id' => $initial_view_id]);
        }
    }
    private function set_session_total_views()
    {
        global $wpdb;
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $views_table = Query::get_table_name(Query::VIEWS);
        $wpdb->query($wpdb->prepare("\n                    UPDATE {$sessions_table} AS sessions\n                    LEFT JOIN (\n                        SELECT\n                            session_id,\n                            COUNT(*) AS view_count\n                        FROM\n                            {$views_table} AS views\n                        WHERE\n                            views.session_id = %d\n                        GROUP BY\n                            session_id) AS view_counts ON sessions.session_id = view_counts.session_id\n                    SET\n                        sessions.total_views = COALESCE(view_counts.view_count, 0)\n                    WHERE sessions.session_id = %d\n                ", $this->session, $this->session));
    }
    private function set_sessions_initial_view(int $view_id)
    {
        global $wpdb;
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $wpdb->query($wpdb->prepare("UPDATE {$sessions_table} SET initial_view_id = %d WHERE session_id = %d AND initial_view_id IS NULL", $view_id, $this->session));
    }
    private function set_sessions_final_view(int $view_id)
    {
        global $wpdb;
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $wpdb->query($wpdb->prepare("\n                    UPDATE {$sessions_table} AS sessions\n                    SET\n                        sessions.final_view_id = %d,\n                        sessions.ended_at = %s\n                    WHERE sessions.session_id = %d AND sessions.initial_view_id IS NOT NULL AND sessions.initial_view_id != %d\n                ", $view_id, $this->viewed_at(), $this->session, $view_id));
    }
    private function create_view() : int
    {
        $views_table = Query::get_table_name(Query::VIEWS);
        return Illuminate_Builder::new()->from($views_table)->insertGetId(['resource_id' => $this->resource->id(), 'viewed_at' => $this->viewed_at(), 'page' => $this->payload['page'], 'session_id' => $this->session]);
    }
    private function fetch_resource()
    {
        global $wpdb;
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $query = '';
        $payload_copy = \array_merge($this->payload);
        unset($payload_copy['page']);
        switch ($payload_copy['resource']) {
            case 'singular':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND singular_id = %d", $payload_copy['resource'], $payload_copy['singular_id']);
                break;
            case 'author_archive':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND author_id = %d", $payload_copy['resource'], $payload_copy['author_id']);
                break;
            case 'date_archive':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND date_archive = %s", $payload_copy['resource'], $payload_copy['date_archive']);
                break;
            case 'post_type_archive':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND post_type = %s", $payload_copy['resource'], $payload_copy['post_type']);
                break;
            case 'term_archive':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND term_id = %s", $payload_copy['resource'], $payload_copy['term_id']);
                break;
            case 'search':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND search_query = %s", $payload_copy['resource'], $payload_copy['search_query']);
                break;
            case 'home':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s ", $payload_copy['resource']);
                break;
            case '404':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND not_found_url = %s", $payload_copy['resource'], $payload_copy['not_found_url']);
                break;
            case 'virtual_page':
                $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE resource = %s AND virtual_page_id = %s", $payload_copy['resource'], $payload_copy['virtual_page_id']);
                break;
        }
        $resource = $wpdb->get_row($query);
        if (\is_null($resource)) {
            return null;
        }
        return $resource;
    }
    private function fetch_or_create_resource() : ?Page
    {
        global $wpdb;
        $resources_table = Query::get_table_name(Query::RESOURCES);
        // Allow site owners to make any view for a virtual page
        $virtual_id = \apply_filters('iawp_convert_to_virtual_page', $this->payload);
        if (\is_string($virtual_id)) {
            $this->payload = ['resource' => 'virtual_page', 'virtual_page_id' => $virtual_id, 'page' => $this->payload['page'] ?? 1];
        }
        $resource = $this->fetch_resource();
        if (\is_null($resource)) {
            $payload_copy = \array_merge($this->payload);
            unset($payload_copy['page']);
            $wpdb->insert($resources_table, $payload_copy);
            $resource = $this->fetch_resource();
        }
        if (\is_null($resource)) {
            return null;
        }
        $page = Page::from_row($resource);
        $page->update_cache();
        return $page;
    }
    /**
     * @return int|null ID of the session that should be used for this view
     */
    private function fetch_or_create_session() : ?int
    {
        $session = $this->fetch_current_session();
        if (\is_null($session)) {
            return $this->create_session();
        }
        $is_same_referrer = $this->referrer_id() === \intval($session->referrer_id);
        $is_same_resource = \intval($this->fetch_resource()->id) === $this->fetch_last_viewed_resource();
        $same_as_previous_view = $is_same_referrer && $is_same_resource;
        // The goal here is to prevent opening multiple tabs to the site from creating multiple sessions
        if ($is_same_referrer) {
            return $session->session_id;
        }
        // The goal here is to prevent a page refresh from creating another session
        if ($this->is_internal_referrer($this->referrer_url) || $same_as_previous_view) {
            return $session->session_id;
        }
        return $this->create_session();
    }
    /**
     * @param string|null $referrer_url
     *
     * @return bool
     */
    private function is_internal_referrer(?string $referrer_url) : bool
    {
        return !empty($referrer_url) && String_Util::str_starts_with(\strtolower($referrer_url), \strtolower(\site_url()));
    }
    private function referrer_id() : int
    {
        $url = new URL($this->referrer_url);
        $domain = $url->get_domain() ?? $this->referrer_url;
        $known_referrer = Known_Referrers::get_group_for($domain);
        // Is the referrer one of a special set of predefined known referrers?
        if ($known_referrer) {
            return $this->fetch_referrer(['domain' => $known_referrer['domain'], 'type' => $known_referrer['type'], 'referrer' => $known_referrer['name']]);
        }
        // Is the referrer invalid or for the current site?
        if (!$url->is_valid_url() || $this->is_internal_referrer($this->referrer_url)) {
            return $this->fetch_referrer(['domain' => '', 'type' => 'Direct', 'referrer' => 'Direct']);
        }
        return $this->fetch_referrer(['domain' => $url->get_domain(), 'type' => 'Referrer', 'referrer' => $this->strip_www($url->get_domain())]);
    }
    private function fetch_referrer(array $attributes) : int
    {
        $referrer = Illuminate_Builder::new()->select(['referrers.id', 'referrers.domain', 'referrers.referrer', 'referrer_types.referrer_type'])->from(Tables::referrers(), 'referrers')->leftJoin(Tables::referrer_types() . ' AS referrer_types', 'referrers.referrer_type_id', '=', 'referrer_types.id')->where('domain', '=', $attributes['domain'])->first();
        if ($referrer === null) {
            return Illuminate_Builder::new()->from(Tables::referrers())->insertGetId(['domain' => $attributes['domain'], 'referrer_type_id' => $this->referrer_type_id($attributes['type']), 'referrer' => $attributes['referrer']]);
        }
        $updates = [];
        if ($referrer->referrer !== $attributes['referrer']) {
            $updates['referrer'] = $attributes['referrer'];
        }
        if ($referrer->referrer_type !== $attributes['type']) {
            $updates['referrer_type_id'] = $this->referrer_type_id($attributes['type']);
        }
        if (!empty($updates)) {
            Illuminate_Builder::new()->from(Tables::referrers())->where('id', '=', $referrer->id)->update($updates);
        }
        return $referrer->id;
    }
    private function referrer_type_id(string $type) : int
    {
        $referrer_type_id = Illuminate_Builder::new()->select('id')->from(Tables::referrer_types())->where('referrer_type', '=', $type)->value('id');
        if ($referrer_type_id === null) {
            $referrer_type_id = Illuminate_Builder::new()->from(Tables::referrer_types())->insertGetId(['referrer_type' => $type]);
        }
        return $referrer_type_id;
    }
    private function strip_www(string $string) : string
    {
        if (\strpos($string, "www.") !== 0) {
            return $string;
        }
        return \substr($string, 4);
    }
    private function fetch_or_create_campaign() : ?int
    {
        if (\is_null($this->campaign_parameters) || \is_null($this->resource->title())) {
            return null;
        }
        $campaign = new \IAWP\Views\Campaign($this->campaign_parameters, $this->resource->title());
        return $campaign->sync();
    }
    private function fetch_current_session() : ?object
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $session = Illuminate_Builder::new()->from($sessions_table, 'sessions')->selectRaw('IFNULL(ended_at, created_at) AS latest_view_at')->selectRaw('sessions.*')->where('visitor_id', '=', $this->visitor->id())->havingRaw('latest_view_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)')->orderBy('latest_view_at', 'DESC')->first();
        return $session;
    }
    private function set_views_postmeta(Page $resource) : void
    {
        if ($resource instanceof Page_Singular) {
            $this->set_views_postmeta_for_singular($resource);
        } elseif ($resource instanceof Page_Home) {
            $this->set_views_postmeta_for_home($resource);
        } elseif ($resource instanceof Page_Post_Type_Archive && $resource->post_type() === 'product') {
            $this->set_views_postmeta_for_shop_page($resource);
        }
    }
    private function set_views_postmeta_for_singular(Page_Singular $resource) : void
    {
        $singular_id = $resource->get_singular_id();
        if ($singular_id === null) {
            return;
        }
        $views_table = Query::get_table_name(Query::VIEWS);
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('resources.id', '=', 'views.resource_id');
        })->where('singular_id', '=', $singular_id)->value('views');
        \update_post_meta($singular_id, Views_Column::$meta_key, $total_views);
    }
    private function set_views_postmeta_for_home(Page_Home $resource) : void
    {
        $blog_page_id = \get_option('page_for_posts');
        if (\is_string($blog_page_id) && \ctype_digit($blog_page_id)) {
            $blog_page_id = \intval($blog_page_id);
        }
        $blog_page = \get_post($blog_page_id);
        if ($blog_page === null) {
            return;
        }
        $views_table = Query::get_table_name(Query::VIEWS);
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
            $join->on('resources.id', '=', 'views.resource_id');
        })->where('resource', '=', 'home')->value('views');
        \update_post_meta($blog_page_id, Views_Column::$meta_key, $total_views);
    }
    private function set_views_postmeta_for_shop_page(Page_Post_Type_Archive $resource) : void
    {
        try {
            $shop_id = wc_get_page_id('shop');
            if ($shop_id === -1) {
                return;
            }
            $views_table = Query::get_table_name(Query::VIEWS);
            $resources_table = Query::get_table_name(Query::RESOURCES);
            $total_views = Illuminate_Builder::new()->selectRaw('COUNT(*) AS views')->from("{$resources_table} as resources")->join("{$views_table} AS views", function (JoinClause $join) {
                $join->on('resources.id', '=', 'views.resource_id');
            })->where('resource', '=', 'post_type_archive')->where('post_type', '=', 'product')->value('views');
            \update_post_meta($shop_id, Views_Column::$meta_key, $total_views);
        } catch (\Throwable $e) {
        }
    }
}
