<?php

namespace IAWP\ColumnOptions;

/** @internal */
class Options
{
    private array $options;
    public function __construct(\IAWP\ColumnOptions\OptionsPlugin $options_plugin)
    {
        $this->options = $options_plugin->get_options();
    }
    /**
     * @return Option[]
     */
    public function all() : array
    {
        return $this->options;
    }
    public function contains($option_id) : ?bool
    {
        return $this->find_by_id($option_id) !== null;
    }
    public function label_for($option_id) : ?string
    {
        $option = $this->find_by_id($option_id);
        if (!$option) {
            return null;
        }
        return $option->label;
    }
    private function find_by_id($option_id) : ?\IAWP\ColumnOptions\Option
    {
        foreach ($this->options as $option) {
            // Don't make strict
            if ($option->id == $option_id) {
                return $option;
            }
        }
        return null;
    }
}
