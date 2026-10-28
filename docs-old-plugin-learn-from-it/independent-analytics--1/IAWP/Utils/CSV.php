<?php

namespace IAWP\Utils;

use IAWPSCOPED\League\Csv\EscapeFormula;
/** @internal */
class CSV
{
    private array $header;
    private array $rows;
    private EscapeFormula $formatter;
    /**
     * @param array $header
     * @param array[] $rows
     */
    public function __construct(array $header, array $rows)
    {
        $this->header = $header;
        $this->rows = $rows;
        $this->formatter = new EscapeFormula();
    }
    public function to_string() : string
    {
        $delimiter = ',';
        $enclosure = '"';
        $escape_character = '\\';
        $temporary_file = \fopen('php://memory', 'r+');
        \fputcsv($temporary_file, $this->header, $delimiter, $enclosure, $escape_character);
        foreach ($this->rows as $row) {
            $row = $this->escape_row($row);
            \fputcsv($temporary_file, $row, $delimiter, $enclosure, $escape_character);
        }
        \rewind($temporary_file);
        return \stream_get_contents($temporary_file);
    }
    private function escape_row(array $row) : array
    {
        $row = $this->formatter->escapeRecord($row);
        foreach ($row as &$cell) {
            if ($cell === "'-") {
                $cell = '-';
            }
        }
        return $row;
    }
}
