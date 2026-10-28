<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
use IAWPSCOPED\Illuminate\Support\Collection;
/** @internal */
class Categories implements OptionsPlugin
{
    public function get_options() : array
    {
        $top_level_categories = \get_categories(['parent' => 0, 'hide_empty' => \false]);
        $options = Collection::make();
        foreach ($top_level_categories as $top_level_category) {
            $options->push(new Option($top_level_category->term_id, $top_level_category->name));
            $options->push(...self::get_subcategories_for($top_level_category->term_id));
        }
        return $options->all();
    }
    private static function get_subcategories_for(int $parent_category_id) : array
    {
        $subcategories = \get_categories(['child_of' => $parent_category_id, 'hide_empty' => \false]);
        return Collection::make($subcategories)->sortBy('name')->map(function ($category) use($parent_category_id) {
            return new Option($category->term_id, $category->name, $parent_category_id);
        })->all();
    }
}
