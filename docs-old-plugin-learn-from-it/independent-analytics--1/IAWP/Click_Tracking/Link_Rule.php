<?php

namespace IAWP\Click_Tracking;

use IAWP\Illuminate_Builder;
use IAWP\Tables;
/** @internal */
class Link_Rule
{
    private $attributes;
    public function __construct(object $attributes)
    {
        // There's a small chance that is_active is a string instead of an int. In that case,
        // it should be converted to a string.
        // https://github.com/andrewjmead/independent-analytics/issues/1335
        if (\is_string($attributes->is_active)) {
            $attributes->is_active = (int) $attributes->is_active;
        }
        $this->attributes = $attributes;
    }
    public function id() : int
    {
        return $this->attributes->link_rule_id;
    }
    public function name() : string
    {
        return $this->attributes->name;
    }
    public function type() : string
    {
        return $this->attributes->type;
    }
    public function value() : string
    {
        return $this->attributes->value;
    }
    public function toggle_active() : bool
    {
        $new_is_active = !$this->is_active() ? 1 : 0;
        $records_updated = Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $this->id())->update(['is_active' => $new_is_active]);
        if ($records_updated === 1) {
            $this->attributes->is_active = $new_is_active;
            return \true;
        } else {
            return \false;
        }
    }
    public function is_active() : bool
    {
        return $this->attributes->is_active === 1;
    }
    public function to_array() : array
    {
        $array = (array) $this->attributes;
        // Rename id
        $array['id'] = $array['link_rule_id'];
        unset($array['link_rule_id']);
        // Convert is_active to a boolean
        $array['is_active'] = $array['is_active'] === 1 ? \true : \false;
        return $array;
    }
    public static function find(int $id) : ?self
    {
        $link_rule = Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $id)->first();
        if (\is_null($link_rule)) {
            return null;
        }
        return new self($link_rule);
    }
}
