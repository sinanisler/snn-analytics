<?php

namespace IAWP\ColumnOptions\Plugins;

use IAWP\ColumnOptions\Option;
use IAWP\ColumnOptions\OptionsPlugin;
/** @internal */
class Authors implements OptionsPlugin
{
    public function get_options() : array
    {
        $roles_that_can_edit_posts = [];
        foreach (\wp_roles()->roles as $role_name => $role_obj) {
            if ($role_obj['capabilities']['edit_posts'] ?? \false) {
                $roles_that_can_edit_posts[] = $role_name;
            }
        }
        $authors = \get_users(['role__in' => $roles_that_can_edit_posts]);
        return \array_map(function ($author) {
            return new Option($author->ID, $author->display_name);
        }, $authors);
    }
}
