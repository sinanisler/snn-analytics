<?php

namespace IAWP\Models;

use IAWP\Geoposition;
use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Utils\Request;
use IAWP\Utils\Salt;
/**
 * How to use:
 *
 * Example IP from the Netherlands
 * $visitor = new Visitor('92.111.145.208', 'some ua string');
 *
 * Example IP from the United States
 * $visitor = new Visitor('98.111.145.208', 'some ua string');
 *
 * Access visitor token
 * $visitor->id();
 * @internal
 */
class Visitor
{
    private $id;
    private $geoposition;
    private $current_session;
    /**
     * New instances should be created with a string ip address
     *
     * @param string $ip
     * @param string $user_agent
     */
    public function __construct(string $ip, string $user_agent)
    {
        $this->id = $this->fetch_visitor_id_by_hash($this->calculate_hash($ip, $user_agent));
        $this->geoposition = new Geoposition($ip);
        $this->current_session = $this->fetch_current_session();
    }
    public function geoposition() : Geoposition
    {
        return $this->geoposition;
    }
    public function has_recorded_session() : bool
    {
        return \is_object($this->current_session);
    }
    public function most_recent_session_id() : ?int
    {
        $session_id = \IAWPSCOPED\iawp_intify($this->current_session->session_id);
        if ($this->has_recorded_session() && \is_int($session_id)) {
            return $session_id;
        } else {
            return null;
        }
    }
    public function most_recent_initial_view_id() : ?int
    {
        $initial_view_id = \IAWPSCOPED\iawp_intify($this->current_session->initial_view_id);
        if ($this->has_recorded_session() && \is_int($initial_view_id)) {
            return $initial_view_id;
        } else {
            return null;
        }
    }
    public function most_recent_final_view_id() : ?int
    {
        $final_view_id = \IAWPSCOPED\iawp_intify($this->current_session->final_view_id);
        if ($this->has_recorded_session() && \is_int($final_view_id)) {
            return $final_view_id;
        } else {
            return null;
        }
    }
    public function most_recent_view_id() : ?int
    {
        return $this->most_recent_final_view_id() ?? $this->most_recent_initial_view_id();
    }
    /**
     * Return the database id for a visitor
     *
     * @return string
     */
    public function id() : string
    {
        return $this->id;
    }
    public function is_first_session() : bool
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        return Illuminate_Builder::new()->select('session_id')->from($sessions_table, 'sessions')->where('visitor_id', '=', $this->id)->doesntExist();
    }
    /**
     * @param string $ip
     * @param string $user_agent
     * @return string
     */
    private function calculate_hash(string $ip, string $user_agent) : string
    {
        $salt = Salt::visitor_token_salt();
        $result = $salt . $ip . $user_agent;
        return \md5($result);
    }
    private function fetch_current_session()
    {
        $sessions_table = Query::get_table_name(Query::SESSIONS);
        $session = Illuminate_Builder::new()->from($sessions_table, 'sessions')->selectRaw('IFNULL(ended_at, created_at) AS latest_view_at')->selectRaw('sessions.*')->where('visitor_id', '=', $this->id)->havingRaw('latest_view_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)')->orderBy('latest_view_at', 'DESC')->first();
        return $session;
    }
    public static function fetch_visitor_id_by_hash(string $hash) : int
    {
        $visitors_table = Query::get_table_name(Query::VISITORS);
        Illuminate_Builder::new()->from($visitors_table)->insertOrIgnore([['hash' => $hash]]);
        $id = Illuminate_Builder::new()->from($visitors_table)->where('hash', '=', $hash)->value('visitor_id');
        return (int) $id;
    }
    public static function fetch_current_visitor() : self
    {
        return new \IAWP\Models\Visitor(Request::ip(), Request::user_agent());
    }
}
