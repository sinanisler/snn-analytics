<?php

namespace IAWP\Overview;

use JsonSerializable;
/** @internal */
class Form_Field_Option implements JsonSerializable
{
    private $id;
    private $name;
    private $group;
    public function __construct(string $id, string $name, ?string $group = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->group = $group;
    }
    public function id() : string
    {
        return $this->id;
    }
    public function name() : string
    {
        return $this->name;
    }
    public function has_group() : bool
    {
        return \is_string($this->group);
    }
    public function group() : ?string
    {
        return $this->group;
    }
    // Fix deprecation warning in PHP 8 while still working in PHP 7
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [$this->id, $this->name, $this->group];
    }
    public static function new(string $id, string $name, ?string $group = null) : self
    {
        return new self($id, $name, $group);
    }
}
