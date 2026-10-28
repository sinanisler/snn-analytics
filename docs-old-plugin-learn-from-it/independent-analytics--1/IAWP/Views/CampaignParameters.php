<?php

namespace IAWP\Views;

/** @internal */
class CampaignParameters
{
    private string $utm_source;
    private string $utm_medium;
    private string $utm_campaign;
    private ?string $utm_term;
    private ?string $utm_content;
    private static array $required_parameters = ['utm_source', 'utm_medium', 'utm_campaign'];
    private function __construct(string $utm_source, string $utm_medium, string $utm_campaign, ?string $utm_term = null, ?string $utm_content = null)
    {
        $this->utm_source = $utm_source;
        $this->utm_medium = $utm_medium;
        $this->utm_campaign = $utm_campaign;
        $this->utm_term = $utm_term;
        $this->utm_content = $utm_content;
    }
    public function utm_source() : string
    {
        return $this->utm_source;
    }
    public function utm_medium() : string
    {
        return $this->utm_medium;
    }
    public function utm_campaign() : string
    {
        return $this->utm_campaign;
    }
    public function utm_term() : ?string
    {
        return $this->utm_term;
    }
    public function utm_content() : ?string
    {
        return $this->utm_content;
    }
    public static function make(?string $utm_source = null, ?string $utm_medium = null, ?string $utm_campaign = null, ?string $utm_term = null, ?string $utm_content = null) : ?self
    {
        foreach (self::$required_parameters as $parameter) {
            if (!isset(${$parameter}) || !\is_string(${$parameter})) {
                return null;
            }
        }
        return new \IAWP\Views\CampaignParameters($utm_source, $utm_medium, $utm_campaign, $utm_term, $utm_content);
    }
}
