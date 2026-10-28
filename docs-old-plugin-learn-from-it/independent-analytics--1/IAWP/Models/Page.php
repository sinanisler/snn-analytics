<?php

namespace IAWP\Models;

use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Tables;
use IAWP\Utils\Request;
use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
abstract class Page extends \IAWP\Models\Model
{
    use \IAWP\Models\Universal_Model_Columns;
    protected $row;
    private $id;
    private $resource;
    private $entrances;
    private $exits;
    private $exit_percent;
    private $is_deleted;
    private $cache;
    private $cached_title;
    private $cached_type;
    private $cached_type_label;
    private $cached_icon;
    private $cached_author_id;
    private $cached_author;
    private $cached_avatar;
    private $cached_date;
    private $cached_category;
    public function __construct($row)
    {
        $this->row = $row;
        $this->id = $row->id ?? null;
        $this->resource = $row->resource ?? null;
        $this->entrances = $row->entrances ?? 0;
        $this->exits = $row->exits ?? 0;
        $this->exit_percent = $row->exit_percent ?? 0;
        // If $row is a full row from the database, use that for the cache
        // Eventually, I'd like to avoid selecting resources.* and just get the cached_.*
        //  fields for the rows that are going to be shown.
        if (\is_string($row->cached_title ?? null)) {
            $this->cache = $row;
        }
    }
    protected abstract function resource_key();
    protected abstract function resource_value();
    protected abstract function calculate_is_deleted() : bool;
    protected abstract function calculate_url();
    protected abstract function calculate_title();
    protected abstract function calculate_type();
    protected abstract function calculate_type_label();
    protected abstract function calculate_icon();
    protected abstract function calculate_author();
    protected abstract function calculate_author_id();
    protected abstract function calculate_avatar();
    protected abstract function calculate_date();
    protected abstract function calculate_category();
    public function id() : int
    {
        return $this->id;
    }
    public function table_type() : string
    {
        return 'views';
    }
    public function entrances() : int
    {
        return $this->entrances;
    }
    public function exits() : int
    {
        return $this->exits;
    }
    public function exit_percent() : float
    {
        return $this->exit_percent;
    }
    /**
     * By default, pages don't have the ability to have comments.
     * This can be overridden by a subclass to return an actual comments value.
     *
     * @return int|null
     */
    public function comments() : ?int
    {
        return null;
    }
    public function get_singular_id() : ?int
    {
        if ($this->resource_key() !== 'singular_id') {
            return null;
        }
        return $this->resource_value();
    }
    public final function is_deleted() : bool
    {
        if (!\is_null($this->is_deleted)) {
            return $this->is_deleted;
        }
        $this->is_deleted = $this->calculate_is_deleted();
        return $this->is_deleted;
    }
    public final function update_cache() : void
    {
        $url = $this->calculate_url();
        $cache = ['cached_title' => $this->calculate_title(), 'cached_url' => \is_string($url) ? Str::limit($url, 2083) : $url, 'cached_type' => $this->calculate_type(), 'cached_type_label' => $this->calculate_type_label(), 'cached_author_id' => $this->calculate_author_id(), 'cached_author' => $this->calculate_author(), 'cached_date' => $this->calculate_date(), 'cached_category' => !empty($this->calculate_category()) ? \implode(', ', $this->calculate_category()) : null];
        if ($this instanceof \IAWP\Models\Page_Virtual) {
            // Allow site owners to override page values such as the title, author, etc
            $modified_cache = \apply_filters('iawp_override_virtual_page_details', $cache, $this->resource_value());
            if (\is_array($modified_cache)) {
                $cache = $modified_cache;
            }
        }
        try {
            Illuminate_Builder::new()->from(Tables::resources())->where($this->resource_key(), '=', $this->resource_value())->update($cache);
        } catch (\PDOException $e) {
            // Do nothing
        }
    }
    public final function url($full_url = \false)
    {
        if ($this->use_cache()) {
            $url = $this->cache->cached_url;
        } else {
            $url = $this->calculate_url();
        }
        if (\is_null($url)) {
            return null;
        }
        if ($full_url) {
            return $url;
        } else {
            return Request::path_relative_to_site_url($url);
        }
    }
    public final function title()
    {
        if ($this->use_cache()) {
            return $this->cache->cached_title;
        }
        if (\is_null($this->cached_title)) {
            $this->cached_title = $this->calculate_title();
        }
        return \strlen($this->cached_title) > 0 ? $this->cached_title : '(no title)';
    }
    public final function type($raw = \false)
    {
        if ($raw) {
            if ($this->use_cache()) {
                return $this->cache->cached_type;
            }
            if (\is_null($this->cached_type)) {
                $this->cached_type = $this->calculate_type();
            }
            return $this->cached_type;
        } else {
            if ($this->use_cache()) {
                return $this->cache->cached_type_label;
            }
            if (\is_null($this->cached_type_label)) {
                $this->cached_type_label = $this->calculate_type_label();
            }
            return $this->cached_type_label;
        }
    }
    public final function icon()
    {
        if (\is_null($this->cached_icon)) {
            $this->cached_icon = $this->calculate_icon();
        }
        return $this->cached_icon;
    }
    public final function author()
    {
        if ($this->use_cache()) {
            return $this->cache->cached_author;
        }
        if (\is_null($this->cached_author)) {
            $this->cached_author = $this->calculate_author();
        }
        return $this->cached_author;
    }
    public final function author_id()
    {
        if ($this->use_cache()) {
            return $this->cache->cached_author_id;
        }
        if (\is_null($this->cached_author_id)) {
            $this->cached_author_id = $this->calculate_author_id();
        }
        return $this->cached_author_id;
    }
    public final function avatar()
    {
        if (\is_null($this->cached_avatar)) {
            $this->cached_avatar = $this->calculate_avatar();
        }
        return $this->cached_avatar;
    }
    public final function date()
    {
        if (\is_null($this->cached_date)) {
            $this->cached_date = $this->calculate_date();
        }
        return $this->cached_date;
    }
    public final function formatted_category() : ?string
    {
        $categories = $this->category(\false);
        $category_names = [];
        if (\count($categories) === 0) {
            return null;
        }
        foreach ($categories as $category_id) {
            $category = \get_the_category_by_ID($category_id);
            if (!\is_wp_error($category)) {
                $category_names[] = $category;
            }
        }
        return \implode(', ', $category_names);
    }
    public final function category($formatted = \true)
    {
        if ($formatted === \true) {
            return $this->formatted_category();
        }
        if (\is_null($this->cached_category)) {
            $this->cached_category = $this->calculate_category();
        }
        return $this->cached_category;
    }
    public function most_popular_subtitle() : ?string
    {
        return null;
    }
    public function examiner_title() : ?string
    {
        return $this->title();
    }
    public function examiner_url() : string
    {
        return \IAWPSCOPED\iawp_dashboard_url(['tab' => 'views', 'examiner' => $this->id()]);
    }
    private function use_cache() : bool
    {
        if (!\is_null($this->cache)) {
            return \true;
        }
        $deleted = $this->is_deleted();
        if ($deleted) {
            $this->cache = $this->get_cache();
        }
        return $deleted;
    }
    private function get_cache()
    {
        global $wpdb;
        $resources_table = Query::get_table_name(Query::RESOURCES);
        $resource_key = $this->resource_key();
        $resource_value = $this->resource_value();
        $query = $wpdb->prepare("SELECT * FROM {$resources_table} WHERE {$resource_key} = %s", $resource_value);
        return $wpdb->get_row($query);
    }
    public static function from_row(object $row) : \IAWP\Models\Page
    {
        switch ($row->resource) {
            case 'singular':
                return new \IAWP\Models\Page_Singular($row);
            case 'author_archive':
                return new \IAWP\Models\Page_Author_Archive($row);
            case 'date_archive':
                return new \IAWP\Models\Page_Date_Archive($row);
            case 'post_type_archive':
                return new \IAWP\Models\Page_Post_Type_Archive($row);
            case 'term_archive':
                return new \IAWP\Models\Page_Term_Archive($row);
            case 'search':
                return new \IAWP\Models\Page_Search($row);
            case 'home':
                return new \IAWP\Models\Page_Home($row);
            case 'virtual_page':
                return \IAWP\Models\Page_Virtual::from($row);
            default:
                return new \IAWP\Models\Page_Not_Found($row);
        }
    }
}
