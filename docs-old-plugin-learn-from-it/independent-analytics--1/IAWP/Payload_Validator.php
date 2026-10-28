<?php

namespace IAWP;

use IAWP\Utils\Salt;
// TODO - Use this in REST_API and View
/** @internal */
class Payload_Validator
{
    private $payload;
    private $signature;
    private ?bool $is_valid = null;
    public function __construct(string $payload, string $signature)
    {
        $this->payload = $payload;
        $this->signature = $signature;
    }
    public function is_valid() : bool
    {
        if ($this->is_valid === null) {
            $this->is_valid = $this->validate();
        }
        return $this->is_valid;
    }
    public function payload() : ?array
    {
        if (!$this->is_valid()) {
            return null;
        }
        return \json_decode($this->payload, \true);
    }
    public function resource() : ?array
    {
        if (!$this->is_valid()) {
            return null;
        }
        $payload = $this->payload();
        $query = \IAWP\Illuminate_Builder::new()->from(\IAWP\Tables::resources())->where('resource', '=', $payload['resource']);
        switch ($payload['resource']) {
            case 'singular':
                $query->where('singular_id', '=', $payload['singular_id']);
                break;
            case 'author_archive':
                $query->where('author_id', '=', $payload['author_id']);
                break;
            case 'date_archive':
                $query->where('date_archive', '=', $payload['date_archive']);
                break;
            case 'post_type_archive':
                $query->where('post_type', '=', $payload['post_type']);
                break;
            case 'term_archive':
                $query->where('term_id', '=', $payload['term_id']);
                break;
            case 'search':
                $query->where('search_query', '=', $payload['search_query']);
                break;
            case 'home':
                break;
            case '404':
                $query->where('not_found_url', '=', $payload['not_found_url']);
                break;
            case 'virtual_page':
                $query->where('virtual_page_id', '=', $payload['virtual_page_id']);
                break;
        }
        $row = $query->first();
        if (!\is_object($row)) {
            return null;
        }
        return (array) $row;
    }
    private function validate() : bool
    {
        $signature = \md5(Salt::request_payload_salt() . $this->payload);
        if ($signature !== $this->signature) {
            return \false;
        }
        $decoded_payload = \json_decode($this->payload, \true);
        if ($decoded_payload === null) {
            return \false;
        }
        return \true;
    }
    public static function new(string $payload, string $signature)
    {
        return new self($payload, $signature);
    }
}
