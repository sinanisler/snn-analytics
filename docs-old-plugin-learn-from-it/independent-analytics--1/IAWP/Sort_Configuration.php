<?php

namespace IAWP;

use IAWP\Tables\Columns\Column;
/** @internal */
class Sort_Configuration
{
    public const ASCENDING = 'asc';
    public const DESCENDING = 'desc';
    private Column $column;
    private string $direction;
    public function __construct(Column $column, ?string $direction = null)
    {
        $this->column = $column;
        $this->direction = self::DESCENDING;
        if ($direction === self::ASCENDING || $direction === self::DESCENDING) {
            $this->direction = $direction;
        }
    }
    public function column() : string
    {
        return $this->column->id();
    }
    public function the_column() : Column
    {
        return $this->column;
    }
    public function direction() : string
    {
        return $this->direction;
    }
}
