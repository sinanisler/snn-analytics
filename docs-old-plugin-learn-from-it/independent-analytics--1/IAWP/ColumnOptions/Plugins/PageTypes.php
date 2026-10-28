<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
/** @internal */
class PageTypes implements OptionsPlugin
{
    public function get_options() : array
    {
        $options = [];
        $options[] = new Option('post', \esc_html__('Post', 'independent-analytics'));
        $options[] = new Option('page', \esc_html__('Page', 'independent-analytics'));
        $options[] = new Option('attachment', \esc_html__('Attachment', 'independent-analytics'));
        foreach (\get_post_types(['public' => \true, '_builtin' => \false]) as $custom_type) {
            $options[] = new Option($custom_type, \get_post_type_object($custom_type)->labels->singular_name);
        }
        $options[] = new Option('category', \esc_html__('Category', 'independent-analytics'));
        $options[] = new Option('post_tag', \esc_html__('Tag', 'independent-analytics'));
        foreach (\get_taxonomies(['public' => \true, '_builtin' => \false]) as $taxonomy) {
            $label = \get_taxonomy_labels(\get_taxonomy($taxonomy))->singular_name;
            /**
             * WooCommerce category and tag taxonomies have the same singular name as WordPress
             * category and tag taxonomies, so use the name here instead
             */
            if (\in_array($taxonomy, ['product_cat', 'product_tag'])) {
                $label = \get_taxonomy_labels(\get_taxonomy($taxonomy))->name;
            }
            $options[] = new Option($taxonomy, \ucwords($label));
        }
        $options[] = new Option('blog-archive', \esc_html__('Blog Home', 'independent-analytics'));
        $options[] = new Option('author-archive', \esc_html__('Author Archive', 'independent-analytics'));
        $options[] = new Option('date-archive', \esc_html__('Date Archive', 'independent-analytics'));
        $options[] = new Option('search-archive', \esc_html__('Search Results', 'independent-analytics'));
        $options[] = new Option('not-found', \esc_html__('404', 'independent-analytics'));
        return $options;
    }
}
