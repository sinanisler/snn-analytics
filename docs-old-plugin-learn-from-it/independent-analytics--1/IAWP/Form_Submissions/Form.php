<?php

namespace IAWP\Form_Submissions;

use IAWP\Illuminate_Builder;
use IAWP\Query;
use IAWP\Utils\Plugin;
use IAWPSCOPED\Illuminate\Support\Str;
/**
 * Form tracking requires dynamic columns. If you have 2 forms, there will be 6 new columns. 2 for
 * a summary and 2 per form. This class makes it easy to get all the forms a given site has, while
 * caching the result, so it doesn't need to re-fetch from the database.
 * @internal
 */
class Form
{
    private $id;
    private $title;
    private $plugin_id;
    private static $forms = null;
    private static $is_plugin_active_cache = [];
    private static $plugins = [['id' => 1, 'name' => 'Fluent Forms', 'plugin_slugs' => ['fluentform/fluentform.php']], ['id' => 2, 'name' => 'WPForms', 'plugin_slugs' => ['wpforms-lite/wpforms.php', 'wpforms/wpforms.php']], ['id' => 3, 'name' => 'Contact Form 7', 'plugin_slugs' => ['contact-form-7/wp-contact-form-7.php']], ['id' => 4, 'name' => 'Gravity Forms', 'plugin_slugs' => ['gravityforms/gravityforms.php']], ['id' => 5, 'name' => 'Ninja Forms', 'plugin_slugs' => ['ninja-forms/ninja-forms.php']], ['id' => 6, 'name' => 'MailOptin', 'plugin_slugs' => ['mailoptin/mailoptin.php']], ['id' => 7, 'name' => 'Convert Pro', 'plugin_slugs' => ['convertpro/convertpro.php']], ['id' => 8, 'name' => 'Elementor Pro', 'plugin_slugs' => ['elementor-pro/elementor-pro.php']], ['id' => 9, 'name' => 'JetFormBuilder', 'plugin_slugs' => ['jetformbuilder/jet-form-builder.php']], ['id' => 10, 'name' => 'Formidable Forms', 'plugin_slugs' => ['formidable/formidable.php']], ['id' => 11, 'name' => 'WS Form', 'plugin_slugs' => ['ws-form/ws-form.php', 'ws-form-pro/ws-form.php']], ['id' => 12, 'name' => 'Amelia', 'plugin_slugs' => ['ameliabooking/ameliabooking.php']], ['id' => 13, 'name' => 'Bricks Builder', 'theme' => 'bricks'], ['id' => 14, 'name' => 'ARForms', 'plugin_slugs' => ['arforms-form-builder/arforms-form-builder.php']], ['id' => 15, 'name' => 'Custom form submissions'], ['id' => 16, 'name' => 'Bit Form', 'plugin_slugs' => ['bit-form/bitforms.php']], ['id' => 17, 'name' => 'Forminator', 'plugin_slugs' => ['forminator/forminator.php']], ['id' => 18, 'name' => 'Hustle', 'plugin_slugs' => ['wordpress-popup/popover.php', 'hustle/opt-in.php']], ['id' => 19, 'name' => 'Avada', 'plugin_slugs' => ['fusion-builder/fusion-builder.php', 'fusion-core/fusion-core.php']], ['id' => 20, 'name' => 'WP Store Locator', 'plugin_slugs' => ['wp-store-locator/wp-store-locator.php']], ['id' => 21, 'name' => 'Thrive Leads', 'plugin_slugs' => ['thrive-leads/thrive-leads.php']], ['id' => 22, 'name' => 'SureForms', 'plugin_slugs' => ['sureforms/sureforms.php']], ['id' => 23, 'name' => 'Kali Forms', 'plugin_slugs' => ['kali-forms/kali-forms.php']], ['id' => 24, 'name' => 'Divi', 'theme' => 'Divi'], ['id' => 25, 'name' => 'MailPoet', 'plugin_slugs' => ['mailpoet/mailpoet.php']], ['id' => 26, 'name' => 'Mailchimp', 'plugin_slugs' => ['mailchimp-for-wp/mailchimp-for-wp.php']], ['id' => 27, 'name' => 'Kadence', 'plugin_slugs' => ['kadence-blocks/kadence-blocks.php']], ['id' => 29, 'name' => 'Newsletter', 'plugin_slugs' => ['newsletter/plugin.php']], ['id' => 30, 'name' => 'Everest Forms', 'plugin_slugs' => ['everest-forms/everest-forms.php']]];
    /**
     * @var array A key/value pair (plugin_id/bool) of plugin IDs
     */
    private static $has_any_tracked_submissions_cache = [];
    private function __construct(int $id, string $title, int $plugin_id)
    {
        $this->id = $id;
        $this->title = $title;
        $this->plugin_id = $plugin_id;
    }
    public function id() : int
    {
        return $this->id;
    }
    public function title() : string
    {
        return $this->title;
    }
    public function icon() : string
    {
        $lowercase = \strtolower($this->plugin_name());
        $hyphenated = \str_replace(' ', '_', $lowercase);
        return $hyphenated;
    }
    public function plugin_name() : string
    {
        return \IAWP\Form_Submissions\Form::find_plugin_by_id($this->plugin_id)['name'];
    }
    public function is_plugin_active() : bool
    {
        return self::static_is_plugin_active($this->plugin_id);
    }
    public function submissions_column() : string
    {
        return "form_submissions_for_{$this->id}";
    }
    public function conversion_rate_column() : string
    {
        return "form_conversion_rate_for_{$this->id}";
    }
    public static function has_active_form_plugin() : bool
    {
        return \is_array(self::get_first_active_form_plugin());
    }
    public static function get_first_active_form_plugin_name() : ?string
    {
        $active_plugin = self::get_first_active_form_plugin();
        return \is_array($active_plugin) ? $active_plugin['name'] : null;
    }
    public static function find_form_by_column_name(string $column_name) : ?\IAWP\Form_Submissions\Form
    {
        $id = Str::match('/\\d+\\z/', $column_name);
        if (!\ctype_digit($id)) {
            return null;
        }
        $id = \intval($id);
        $forms = self::get_forms();
        foreach ($forms as $form) {
            if ($id === $form->id()) {
                return $form;
            }
        }
        return null;
    }
    /**
     * @return Form[]
     */
    public static function get_forms() : array
    {
        if (\is_array(self::$forms)) {
            return self::$forms;
        }
        $forms_table = Query::get_table_name(Query::FORMS);
        $query = Illuminate_Builder::new()->select(['form_id', 'cached_form_title', 'plugin_id'])->from($forms_table);
        $forms = \array_filter(\array_map(function ($form) {
            if (\is_null(self::find_plugin_by_id($form->plugin_id))) {
                return null;
            }
            return new self($form->form_id, $form->cached_form_title, $form->plugin_id);
        }, $query->get()->all()));
        \usort($forms, function (\IAWP\Form_Submissions\Form $a, \IAWP\Form_Submissions\Form $b) {
            // First level sort by plugin name
            $plugin_name_comparison = \strcmp($a->plugin_name(), $b->plugin_name());
            // If types are equal, sort by 'name'
            if ($plugin_name_comparison === 0) {
                return \strcmp($a->title(), $b->title());
            }
            return $plugin_name_comparison;
        });
        self::$forms = $forms;
        return self::$forms;
    }
    public static function find_plugin_by_id(int $id) : ?array
    {
        foreach (self::$plugins as $plugin) {
            if ($plugin['id'] === $id) {
                return $plugin;
            }
        }
        return null;
    }
    private static function has_any_tracked_submissions(int $plugin_id) : bool
    {
        if (\array_key_exists($plugin_id, self::$has_any_tracked_submissions_cache)) {
            return self::$has_any_tracked_submissions_cache[$plugin_id];
        }
        $forms_table = Query::get_table_name(Query::FORMS);
        $form_submissions_table = Query::get_table_name(Query::FORM_SUBMISSIONS);
        $has_submissions = Illuminate_Builder::new()->from($forms_table, 'forms')->join("{$form_submissions_table} AS form_submissions", 'forms.form_id', '=', 'form_submissions.form_id')->where('forms.plugin_id', '=', $plugin_id)->exists();
        self::$has_any_tracked_submissions_cache[$plugin_id] = $has_submissions;
        return $has_submissions;
    }
    private static function get_first_active_form_plugin() : ?array
    {
        foreach (\IAWP\Form_Submissions\Form::$plugins as $plugin) {
            if (self::static_is_plugin_active($plugin['id'])) {
                return $plugin;
            }
        }
        return null;
    }
    private static function static_is_plugin_active(int $plugin_id) : bool
    {
        if (\array_key_exists($plugin_id, self::$is_plugin_active_cache)) {
            return self::$is_plugin_active_cache[$plugin_id];
        }
        $plugin = \IAWP\Form_Submissions\Form::find_plugin_by_id($plugin_id);
        if (!\array_key_exists('plugin_slugs', $plugin) && !\array_key_exists('theme', $plugin)) {
            if (self::has_any_tracked_submissions($plugin_id)) {
                self::$is_plugin_active_cache[$plugin_id] = \true;
                return \true;
            }
        }
        if (\array_key_exists('theme', $plugin)) {
            if (\strtolower(\get_template()) === \strtolower($plugin['theme'])) {
                self::$is_plugin_active_cache[$plugin_id] = \true;
                return \true;
            }
        }
        if (\array_key_exists('plugin_slugs', $plugin)) {
            foreach ($plugin['plugin_slugs'] as $slug) {
                if (\is_plugin_active($slug)) {
                    self::$is_plugin_active_cache[$plugin_id] = \true;
                    return \true;
                }
            }
        }
        self::$is_plugin_active_cache[$plugin_id] = \false;
        return \false;
    }
}
