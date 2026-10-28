<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Click_Tracking;
use IAWP\Illuminate_Builder;
use IAWP\Tables;
use IAWP\Utils\Link_Validator;
/** @internal */
class Edit_Link extends \IAWP\AJAX\AJAX
{
    protected function action_name() : string
    {
        return 'iawp_edit_link';
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    protected function action_callback() : void
    {
        if (!Capability_Manager::can_edit()) {
            return;
        }
        $link_id = $this->get_int_field('id');
        $link_properties = ['name' => $this->get_field('name'), 'type' => $this->get_field('type'), 'value' => $this->get_field('value')];
        // Validate
        foreach ($link_properties as $key => $value) {
            $error = Link_Validator::validate($key, $value, $link_properties['type']);
            if ($error) {
                \wp_send_json_error(['error' => $error, 'property' => $key]);
            }
        }
        // Sanitize
        $link_properties['name'] = \sanitize_text_field($link_properties['name']);
        $link_properties['type'] = \sanitize_text_field($link_properties['type']);
        if ($link_properties['type'] == 'domain') {
            $link_properties['value'] = Link_Validator::sanitize_domain($link_properties['value']);
        } elseif ($link_properties['type'] == 'subdirectory') {
            $link_properties['value'] = Link_Validator::sanitize_subdirectory($link_properties['value']);
        } elseif ($link_properties['type'] == 'external') {
            // There's no value for external
            $link_properties['value'] = '';
        } else {
            $link_properties['value'] = \sanitize_text_field($link_properties['value']);
        }
        $link_rule = null;
        if (\is_int($link_id)) {
            $link_rule = Click_Tracking\Link_Rule::find($link_id);
            if ($link_rule->type() !== $link_properties['type'] || $link_rule->value() !== $link_properties['value']) {
                Click_Tracking\Link_Rule_Finder::require_cleared_cache();
            }
        } else {
            Click_Tracking\Link_Rule_Finder::require_cleared_cache();
        }
        if ($link_rule) {
            Illuminate_Builder::new()->from(Tables::link_rules())->where('link_rule_id', '=', $link_id)->update($link_properties);
        } else {
            $link_properties['created_at'] = (new \DateTime())->format('Y-m-d H:i:s');
            $link_id = Illuminate_Builder::new()->from(Tables::link_rules())->insertGetId($link_properties);
        }
        // Fetch a fresh copy
        $link_rule = Click_Tracking\Link_Rule::find($link_id);
        \wp_send_json_success(['shouldShowCacheMessage' => \get_option('iawp_click_tracking_cache_cleared') === \false, 'html' => \IAWPSCOPED\iawp_blade()->run('click-tracking.link', ['link' => $link_rule->to_array(), 'types' => Click_Tracking::types(), 'extensions' => Click_Tracking::extensions(), 'protocols' => Click_Tracking::protocols()])]);
    }
}
