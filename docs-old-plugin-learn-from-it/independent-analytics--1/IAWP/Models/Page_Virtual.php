<?php

namespace IAWP\Models;

use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Page_Virtual extends \IAWP\Models\Page
{
    private $virtual_page_id;
    private $surecart_product;
    private $surecart_collection;
    private $surecart_upsell;
    public function __construct($row)
    {
        $this->virtual_page_id = $row->virtual_page_id;
        parent::__construct($row);
    }
    protected function resource_key() : string
    {
        return 'virtual_page_id';
    }
    protected function resource_value() : string
    {
        return $this->virtual_page_id;
    }
    protected function calculate_is_deleted() : bool
    {
        return \false;
    }
    protected function calculate_url()
    {
        $surecart_product = $this->get_surecart_product();
        if ($surecart_product) {
            return $surecart_product->getPermalinkAttribute();
        }
        $surecart_collection = $this->get_surecart_collection();
        if ($surecart_collection) {
            return $surecart_collection->getPermalinkAttribute();
        }
        $surecart_upsell = $this->get_surecart_upsell();
        if ($surecart_upsell) {
            return $surecart_upsell->getPermalinkAttribute();
        }
        return null;
    }
    protected function calculate_title()
    {
        if ($this->virtual_page_id === 'wc_checkout_success') {
            return \__('Checkout Success', 'independent-analytics');
        }
        $surecart_product = $this->get_surecart_product();
        if ($surecart_product) {
            return $surecart_product->name;
        }
        $surecart_collection = $this->get_surecart_collection();
        if ($surecart_collection) {
            return $surecart_collection->name;
        }
        $surecart_upsell = $this->get_surecart_upsell();
        if ($surecart_upsell) {
            return $surecart_upsell->metadata->title;
        }
        return \__('Page', 'independent-analytics');
    }
    protected function calculate_type()
    {
        $surecart_product = $this->get_surecart_product();
        if ($surecart_product) {
            return 'sc_product';
        }
        $surecart_collection = $this->get_surecart_collection();
        if ($surecart_collection) {
            return 'sc_collection';
        }
        $surecart_upsell = $this->get_surecart_upsell();
        if ($surecart_upsell) {
            return 'sc_upsell';
        }
        return 'page';
    }
    protected function calculate_type_label()
    {
        $surecart_product = $this->get_surecart_product();
        if ($surecart_product) {
            return \__('Product', 'independent-analytics');
        }
        $surecart_collection = $this->get_surecart_collection();
        if ($surecart_collection) {
            return \__('Collection', 'independent-analytics');
        }
        $surecart_upsell = $this->get_surecart_upsell();
        if ($surecart_upsell) {
            return \__('Upsell', 'independent-analytics');
        }
        return 'Page';
    }
    protected function calculate_icon()
    {
        $surecart_product = $this->get_surecart_product();
        $surecart_collection = $this->get_surecart_collection();
        $surecart_upsell = $this->get_surecart_upsell();
        if ($surecart_product || $surecart_collection || $surecart_upsell) {
            return '<span class="img-container"><img src="' . \esc_url(\IAWPSCOPED\iawp_url_to('/img/surecart.png')) . '" width="20px" height="20px" /></span>';
        }
        return '<span class="dashicons dashicons-admin-page"></span>';
    }
    protected function calculate_author_id()
    {
        return null;
    }
    protected function calculate_author()
    {
        return null;
    }
    protected function calculate_avatar()
    {
        return null;
    }
    protected function calculate_date()
    {
        return null;
    }
    protected function calculate_category()
    {
        return [];
    }
    private function get_surecart_product() : ?object
    {
        if (\is_object($this->surecart_product)) {
            return $this->surecart_product;
        }
        if (Str::startsWith($this->virtual_page_id, 'sc_product_') && \class_exists('\\SureCart\\Models\\Product')) {
            try {
                $id = Str::after($this->virtual_page_id, 'sc_product_');
                $this->surecart_product = \SureCart\Models\Product::find($id);
                return $this->surecart_product;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
    private function get_surecart_collection() : ?object
    {
        if (\is_object($this->surecart_collection)) {
            return $this->surecart_collection;
        }
        if (Str::startsWith($this->virtual_page_id, 'sc_collection_') && \class_exists('IAWPSCOPED\\SureCart\\Models\\ProductCollection')) {
            try {
                $id = Str::after($this->virtual_page_id, 'sc_collection_');
                $this->surecart_collection = \IAWPSCOPED\SureCart\Models\ProductCollection::find($id);
                return $this->surecart_collection;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
    private function get_surecart_upsell() : ?object
    {
        if (\is_object($this->surecart_upsell)) {
            return $this->surecart_upsell;
        }
        if (Str::startsWith($this->virtual_page_id, 'sc_upsell_') && \class_exists('\\SureCart\\Models\\Upsell')) {
            try {
                $id = Str::after($this->virtual_page_id, 'sc_upsell_');
                $this->surecart_upsell = \SureCart\Models\Upsell::find($id);
                return $this->surecart_upsell;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
    public static function from(object $row) : \IAWP\Models\Page_Virtual
    {
        $virtual_page_id = $row->virtual_page_id;
        if (Str::startsWith($virtual_page_id, 'clickwhale_link_page')) {
            return new \IAWP\Models\ClickWhale_Link_Page($row);
        }
        return new self($row);
    }
}
