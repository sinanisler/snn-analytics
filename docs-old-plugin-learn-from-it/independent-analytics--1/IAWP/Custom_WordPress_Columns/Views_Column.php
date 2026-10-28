<?php

namespace IAWP\Custom_WordPress_Columns;

use IAWP\Capability_Manager;
use IAWP\Utils\Number_Formatter;
/** @internal */
class Views_Column
{
    public static $meta_key = 'iawp_total_views';
    public static function initialize() : void
    {
        if (\get_option('iawp_disable_views_column', 0) === '1') {
            return;
        }
        \add_action('init', [self::class, 'register_custom_post_meta']);
        \add_filter('manage_posts_columns', [self::class, 'set_column_header'], 10, 2);
        \add_action('manage_posts_custom_column', [self::class, 'echo_cell_content'], 10, 2);
        \add_action('pre_get_posts', [self::class, 'configure_sorting']);
        \add_filter('manage_pages_columns', [self::class, 'set_column_header']);
        \add_action('manage_pages_custom_column', [self::class, 'echo_cell_content'], 10, 2);
        \add_action('pre_get_pages', [self::class, 'configure_sorting']);
    }
    public static function register_custom_post_meta() : void
    {
        foreach (self::post_type_slugs() as $post_type_slug) {
            \register_post_meta($post_type_slug, self::$meta_key, ['type' => 'integer', 'single' => \true, 'default' => 0, 'show_in_rest' => \true]);
            \add_filter("manage_edit-{$post_type_slug}_sortable_columns", [self::class, 'enable_sorting']);
        }
    }
    public static function set_column_header(array $columns, ?string $post_type = null) : array
    {
        // manage_pages_columns doesn't set the $post_type argument, so checking is_null() works for it
        if (\is_null($post_type) || \in_array($post_type, self::post_type_slugs())) {
            $columns[self::$meta_key] = \__('Views', 'independent-analytics') . ' <span class="iawp-hidden-label"><span class="hide">(</span>Independent Analytics<span class="hide">)</span></span>';
        }
        return $columns;
    }
    public static function echo_cell_content($column_id, $post_id) : void
    {
        // Do nothing for columns other than the views column
        if ($column_id !== self::$meta_key) {
            return;
        }
        $author_id = \intval(\get_post_field('post_author', $post_id));
        $is_your_post = \get_current_user_id() === $author_id;
        if (Capability_Manager::can_view_all_analytics() || $is_your_post) {
            $total_views = \intval(\get_post_meta($post_id, self::$meta_key, \true));
            echo Number_Formatter::decimal($total_views);
        } else {
            echo '-';
        }
    }
    public static function enable_sorting(array $columns) : array
    {
        $columns[self::$meta_key] = [self::$meta_key, 'DESC'];
        return $columns;
    }
    public static function configure_sorting($query) : void
    {
        // Limit to admin menus
        if (!\is_admin() || !$query->is_main_query() || \wp_doing_ajax() || \wp_doing_cron()) {
            return;
        }
        $order_by = $query->get('orderby');
        if ($order_by === self::$meta_key) {
            $meta_query = ['relation' => 'OR', ['key' => self::$meta_key, 'compare' => 'NOT EXISTS'], ['key' => self::$meta_key]];
            $query->set('meta_query', $meta_query);
            $query->set('orderby', 'meta_value_num');
        }
    }
    private static function post_type_slugs() : array
    {
        $built_in_post_type_slugs = ['post', 'page'];
        $post_type_slugs = \get_post_types(['public' => \true, '_builtin' => \false]);
        $slugs = \array_merge($built_in_post_type_slugs, $post_type_slugs);
        $disallowed = ['elementor_library', 'e-landing-page'];
        return \array_diff($slugs, $disallowed);
    }
}
