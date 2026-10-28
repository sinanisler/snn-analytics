<?php

namespace IAWP\ColumnOptions;

/** @internal */
class Option
{
    /** @var mixed */
    public $id;
    public string $label;
    /** @var mixed */
    public $parent_id;
    public function __construct($id, string $label, $parent_id = null)
    {
        $this->id = $id;
        $this->label = $label;
        $this->parent_id = $parent_id;
    }
    public function is_parent() : bool
    {
        return \is_null($this->parent_id);
    }
}
