<?php

namespace IAWP;

/** @internal */
interface Plugin_Group_Option
{
    public function id() : string;
    public function name() : string;
    public function plugin_group_header() : ?string;
    public function is_member_of_plugin_group(string $plugin_group) : bool;
    public function is_visible() : bool;
    public function is_group_plugin_enabled() : bool;
    public function is_subgroup_plugin_enabled() : bool;
}
