<?php

namespace IAWP;

/** @internal */
class Examiner_Config
{
    private string $type;
    private string $group;
    private int $id;
    private function __construct(string $type, string $group, int $id)
    {
        $this->type = $type;
        $this->group = $group;
        $this->id = $id;
    }
    public function type() : string
    {
        return $this->type;
    }
    public function group() : string
    {
        return $this->group;
    }
    public function id() : int
    {
        return $this->id;
    }
    public static function make(array $attributes) : ?self
    {
        if (!\IAWPSCOPED\iawp_is_pro()) {
            return null;
        }
        if (!\is_string($attributes['type'])) {
            return null;
        }
        if (!\is_string($attributes['group'])) {
            return null;
        }
        if (!\is_int($attributes['id'])) {
            return null;
        }
        return new self($attributes['type'], $attributes['group'], $attributes['id']);
    }
}
