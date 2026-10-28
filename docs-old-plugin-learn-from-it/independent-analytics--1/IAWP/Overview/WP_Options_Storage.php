<?php

namespace IAWP\Overview;

use IAWPSCOPED\Illuminate\Support\Collection;
/**
 * Storing an array of data in wp_options? Easily manipulate it with WP_Options_Storage!
 * @internal
 */
class WP_Options_Storage
{
    private $option_name;
    public function __construct(string $option_name)
    {
        $this->option_name = $option_name;
    }
    public function all() : array
    {
        return \get_option($this->option_name, []);
    }
    /**
     * Find a record by its id.
     *
     * @param string|null $id
     *
     * @return array|null
     */
    public function find_by_id(?string $id) : ?array
    {
        if ($id === null) {
            return null;
        }
        $records = \get_option($this->option_name, []);
        return Collection::make($records)->first(function ($module) use($id) {
            return $module['id'] === $id;
        });
    }
    /**
     * Check if a record exists.
     *
     * @param string $id
     *
     * @return bool
     */
    public function exists(?string $id) : bool
    {
        return $this->find_by_id($id) !== null;
    }
    /**
     * Insert a record. If it contains an id that's already in use, the existing record will be
     * completely replaced by the new one.
     *
     * @param array $attributes
     *
     * @return string Inserted records id
     */
    public function insert(array $attributes) : ?string
    {
        // TODO It needs to preserve the position if it's updating an existing record...
        if (!\is_array($attributes)) {
            return null;
        }
        if (!\array_key_exists('id', $attributes)) {
            $attributes['id'] = $this->generate_id();
        }
        $match_found = \false;
        $records = \get_option($this->option_name, []);
        $new_records = Collection::make($records)->map(function ($record) use($attributes, &$match_found) {
            // Replace the existing record, if any
            if ($record['id'] === $attributes['id']) {
                $match_found = \true;
                return $attributes;
            }
            return $record;
        });
        if (!$match_found) {
            $new_records->push($attributes);
        }
        \update_option($this->option_name, $new_records->all(), \false);
        return $attributes['id'];
    }
    /**
     * Delete a record by its id.
     *
     * @param string|null $id
     *
     * @return bool
     */
    public function delete(?string $id) : bool
    {
        if ($id === null) {
            return \false;
        }
        $records = \get_option($this->option_name, []);
        $new_records = Collection::make($records)->reject(function ($module) use($id) {
            return $module['id'] === $id;
        })->values()->all();
        \update_option($this->option_name, $new_records, \false);
        // Return true if a record was deleted
        return \count($records) === \count($new_records) + 1;
    }
    public function generate_id() : string
    {
        return \bin2hex(\random_bytes(16));
    }
    /**
     * Reorder the records
     *
     * @param string[] $ids
     *
     * @return void
     */
    public function set_order(array $ids) : void
    {
        $records = \get_option($this->option_name, []);
        $sorted_records = Collection::make($records)->sort(function ($a, $b) use($ids) {
            $a_index = \array_search($a['id'], $ids);
            $b_index = \array_search($b['id'], $ids);
            return $a_index <=> $b_index;
        })->values()->all();
        \update_option($this->option_name, $sorted_records, \false);
    }
}
