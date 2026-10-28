<?php

namespace IAWP;

use IAWP\Utils\Request;
/** @internal */
class Resource_Identifier
{
    private $type;
    private $meta_key;
    private $meta_value;
    /**
     * @param string $type
     * @param string|null $meta_key
     * @param int|string|null $meta_value
     */
    private function __construct(string $type, ?string $meta_key = null, $meta_value = null)
    {
        $this->type = $type;
        $this->meta_key = $meta_key;
        $this->meta_value = $meta_value;
    }
    /**
     * @return string
     */
    public function type() : string
    {
        return $this->type;
    }
    /**
     * @return string|null
     */
    public function meta_key() : ?string
    {
        return $this->meta_key;
    }
    /**
     * @return int|string|null
     */
    public function meta_value()
    {
        return $this->meta_value;
    }
    /**
     * @return bool
     */
    public function has_meta() : bool
    {
        return !\is_null($this->meta_key) && !\is_null($this->meta_value);
    }
    /**
     * @return self|null
     */
    public static function for_resource_being_viewed() : ?self
    {
        if (self::is_searchiq_results()) {
            $type = 'search';
            $meta_key = 'search_query';
            $meta_value = \get_query_var(\get_option('_siq_search_query_param_name', 'q'));
        } elseif (\is_string(self::get_virtual_page_id())) {
            $type = 'virtual_page';
            $meta_key = 'virtual_page_id';
            $meta_value = self::get_virtual_page_id();
        } elseif (\is_singular()) {
            $singular_id = \get_queried_object_id();
            if (\get_post($singular_id)) {
                $type = 'singular';
                $meta_key = 'singular_id';
                $meta_value = $singular_id;
            } else {
                return null;
            }
        } elseif (\is_author()) {
            $author_id = \get_queried_object_id();
            if (\get_user_by('id', $author_id)) {
                $type = 'author_archive';
                $meta_key = 'author_id';
                $meta_value = $author_id;
            } else {
                return null;
            }
        } elseif (\is_date()) {
            $type = 'date_archive';
            $meta_key = 'date_archive';
            $meta_value = self::get_date_archive_date();
        } elseif (\is_search()) {
            $type = 'search';
            $meta_key = 'search_query';
            $meta_value = \get_search_query();
        } elseif (\is_post_type_archive()) {
            $type = 'post_type_archive';
            $meta_key = 'post_type';
            $meta_value = \get_queried_object()->name;
        } elseif (\is_category()) {
            $category_id = \get_queried_object_id();
            $category_name = \get_the_category_by_ID($category_id);
            if (\is_wp_error($category_name)) {
                return null;
            }
            $type = 'term_archive';
            $meta_key = 'term_id';
            $meta_value = $category_id;
        } elseif (\is_tag()) {
            $tag_id = \get_queried_object_id();
            $tag = \get_tag($tag_id);
            if ($tag === null || \is_wp_error($tag)) {
                return null;
            }
            $type = 'term_archive';
            $meta_key = 'term_id';
            $meta_value = $tag_id;
        } elseif (\is_tax()) {
            $term_id = \get_queried_object_id();
            $term = \get_term($term_id);
            if ($term === null || \is_wp_error($term)) {
                return null;
            }
            $type = 'term_archive';
            $meta_key = 'term_id';
            $meta_value = $term_id;
        } elseif (\is_home()) {
            $type = 'home';
            $meta_key = null;
            $meta_value = null;
        } elseif (\is_404()) {
            $path = Request::path_relative_to_site_url();
            if (\is_null($path)) {
                return null;
            }
            $type = '404';
            $meta_key = 'not_found_url';
            $meta_value = $path;
        } else {
            return null;
        }
        return new self($type, $meta_key, $meta_value);
    }
    /**
     * @return self|null
     */
    public static function for_resource_being_edited() : ?self
    {
        if (!\is_admin()) {
            return null;
        }
        $screen = \get_current_screen();
        $is_post_editing_screen = !\is_null($screen) && $screen->base === 'post';
        if (!$is_post_editing_screen) {
            return null;
        }
        // Check if the current screen is post editing page
        $singular_id = \get_the_ID();
        if (\is_int($singular_id)) {
            $singular_id = \strval($singular_id);
        } else {
            return null;
        }
        // Add an exception for the WooCommerce shop page
        if (\IAWPSCOPED\iawp()->is_woocommerce_support_enabled()) {
            $shop_id = \strval(wc_get_page_id('shop'));
            if ($shop_id === $singular_id) {
                return new self('post_type_archive', 'post_type', 'product');
            }
        }
        return new self('singular', 'singular_id', $singular_id);
    }
    public static function for_post_id(int $post_id) : ?self
    {
        return new self('singular', 'singular_id', $post_id);
    }
    private static function get_virtual_page_id() : ?string
    {
        if (\is_404()) {
            return null;
        }
        $post = \get_post();
        if (\IAWPSCOPED\iawp()->is_woocommerce_support_enabled() && is_checkout() && is_wc_endpoint_url('order-received')) {
            return 'wc_checkout_success';
        }
        if (\IAWPSCOPED\iawp()->is_surecart_support_enabled() && $post->post_type === 'sc_product' && \property_exists($post, 'sc_id')) {
            return 'sc_product_' . $post->sc_id;
        }
        if (\IAWPSCOPED\iawp()->is_surecart_support_enabled() && $post->post_type === 'sc_collection' && \property_exists($post, 'sc_id')) {
            return 'sc_collection_' . $post->sc_id;
        }
        if (\IAWPSCOPED\iawp()->is_surecart_support_enabled() && $post->post_type === 'sc_upsell' && \property_exists($post, 'sc_id')) {
            return 'sc_upsell_' . $post->sc_id;
        }
        // TODO - What's the pro slug?
        if (\is_plugin_active('clickwhale/clickwhale.php') && \is_object($post) && \property_exists($post, 'linkpage') && \is_array($post->linkpage)) {
            return 'clickwhale_link_page_' . $post->linkpage['id'];
        }
        return null;
    }
    /**
     * Build a date archive string with the year and possibly a month and date
     *
     * Examples would be "2023", "2023-01", or "2023-01-21"
     *
     * @return mixed|string
     */
    private static function get_date_archive_date()
    {
        $str = \get_query_var('year');
        if (\is_month() || \is_day()) {
            $month = \get_query_var('monthnum');
            $str = $str . '-' . \str_pad($month, 2, '0', \STR_PAD_LEFT);
        }
        if (\is_day()) {
            $day = \get_query_var('day');
            $str = $str . '-' . \str_pad($day, 2, '0', \STR_PAD_LEFT);
        }
        return $str;
    }
    private static function is_searchiq_results()
    {
        if (!\is_plugin_active('searchiq/searchiq.php')) {
            return \false;
        }
        if (\get_query_var(\get_option('_siq_search_query_param_name', 'q')) !== '') {
            $post = \get_post(\get_queried_object_id());
            if (\has_shortcode($post->post_content, 'siq_ajax_search')) {
                return \true;
            }
        }
        return \false;
    }
}
