<?php

namespace IAWP\Migrations;

use IAWP\Tables;
/** @internal */
trait Creates_Reports
{
    public function build_report_insert_query(array $attributes) : string
    {
        global $wpdb;
        if (\array_key_exists('columns', $attributes) && \is_array($attributes['columns'])) {
            $attributes['columns'] = \json_encode($attributes['columns']);
        }
        if (\array_key_exists('filters', $attributes) && \is_array($attributes['filters'])) {
            $attributes['filters'] = \json_encode($attributes['filters']);
        }
        $tables = Tables::class;
        $columns = [];
        $values_placeholders = [];
        $values = [];
        foreach ($attributes as $key => $value) {
            $columns[] = $key;
            $values_placeholders[] = "%s";
            $values[] = $value;
        }
        $columns = \implode(', ', $columns);
        $values_placeholders = \implode(', ', $values_placeholders);
        return $wpdb->prepare("\n            INSERT INTO {$tables::reports()}\n                ({$columns})\n            VALUES\n                ({$values_placeholders});\n        ", ...$values);
    }
}
