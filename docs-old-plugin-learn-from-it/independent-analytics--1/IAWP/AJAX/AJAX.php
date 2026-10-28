<?php

namespace IAWP\AJAX;

use IAWP\Capability_Manager;
use IAWP\Migrations;
use IAWP\Utils\Security;
use IAWP\Utils\Server;
/** @internal */
abstract class AJAX
{
    public function __construct()
    {
        if (\IAWPSCOPED\iawp_is_pro() || !$this->requires_pro()) {
            \add_action('wp_ajax_' . $this->action_name(), [$this, 'intercept_ajax']);
        }
    }
    /**
     * Classes must define an action name for the ajax request
     *
     * The required nonce for the ajax request will be the action name with a "_nonce" postfix
     * Example: "iawp_delete_data" require a "iawp_delete_data_nonce" nonce field
     *
     * @return string
     */
    protected abstract function action_name() : string;
    /**
     * Classes must define an action callback to run when an ajax request is made
     *
     * @return void
     */
    protected abstract function action_callback() : void;
    /**
     * This is the direct handler for ajax requests.
     * Permissions and nonce values are checked before executing the ajax action_callback function.
     *
     * @return void
     */
    public function intercept_ajax() : void
    {
        // Todo - Should this be can_edit() instead?
        $is_not_migrating = $this->allowed_during_migrations() || !Migrations\Migrations::is_migrating();
        $valid_fields = !$this->missing_fields();
        $can_view = Capability_Manager::can_view();
        \check_ajax_referer($this->action_name(), 'nonce');
        if ($is_not_migrating && $valid_fields && $can_view) {
            Server::increase_max_execution_time();
            $this->action_callback();
        } else {
            \wp_send_json_error(['errorMessage' => 'Unable to process IAWP AJAX request'], 400);
        }
        \wp_die();
    }
    public function get_action_signature() : array
    {
        $shorthand_name = $this->action_name();
        if (\strpos($this->action_name(), "iawp_") === 0) {
            $shorthand_name = \substr($this->action_name(), 5);
        }
        return [$shorthand_name => ['action' => $this->action_name(), 'nonce' => \wp_create_nonce($this->action_name())]];
    }
    /**
     * Classes can define a set of required fields for an ajax request
     *
     * @return array
     */
    protected function action_required_fields() : array
    {
        return [];
    }
    /**
     * Override method to allow the AJAX request to run during migrations
     *
     * @return bool false by default
     */
    protected function allowed_during_migrations() : bool
    {
        return \false;
    }
    protected function requires_pro() : bool
    {
        return \false;
    }
    /**
     * Get a field value. This method supports text and arrays. Returns array if no field found.
     *
     * @param $field
     *
     * @return array|string|null
     */
    protected function get_field($field)
    {
        if (!\array_key_exists($field, $_POST)) {
            return null;
        }
        $type = \gettype($_POST[$field]);
        if ($type == 'array') {
            if ($this->array_is_list($_POST[$field])) {
                return \rest_sanitize_array($_POST[$field]);
            } else {
                return \rest_sanitize_object($_POST[$field]);
            }
        } else {
            return \stripslashes(\sanitize_text_field($_POST[$field]));
        }
    }
    protected function get_int_field($field) : ?int
    {
        if (!\array_key_exists($field, $_POST)) {
            return null;
        }
        $type = \gettype($_POST[$field]);
        if ($type === 'int') {
            return $_POST[$field];
        } elseif ($type === 'string') {
            return \ctype_digit($_POST[$field]) ? (int) $_POST[$field] : null;
        } else {
            return null;
        }
    }
    protected function get_boolean_field($field) : ?bool
    {
        if (!\array_key_exists($field, $_POST)) {
            return null;
        }
        $type = \gettype($_POST[$field]);
        if ($type === 'string') {
            return $_POST[$field] === 'true';
        } else {
            return null;
        }
    }
    protected function get_array_field($field) : ?array
    {
        if (!\array_key_exists($field, $_POST)) {
            return null;
        }
        $value = $_POST[$field];
        if (!\is_array($value)) {
            return null;
        }
        return Security::array($value);
    }
    // This is the recommended polyfill: https://wiki.php.net/rfc/is_list
    private function array_is_list(array $array) : bool
    {
        $expectedKey = 0;
        foreach ($array as $i => $_) {
            if ($i !== $expectedKey) {
                return \false;
            }
            $expectedKey++;
        }
        return \true;
    }
    private function missing_fields() : bool
    {
        foreach ($this->action_required_fields() as $required_field) {
            if (!\array_key_exists($required_field, $_POST)) {
                return \true;
            }
        }
        return \false;
    }
}
