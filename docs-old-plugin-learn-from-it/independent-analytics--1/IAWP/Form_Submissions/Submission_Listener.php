<?php

namespace IAWP\Form_Submissions;

use IAWP\Illuminate_Builder;
use IAWP\Utils\Security;
/** @internal */
class Submission_Listener
{
    public function __construct()
    {
        // Fluent forms
        \add_action('fluentform/before_insert_submission', function ($entryId, $formData, $form) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(1, \intval($form->id), Security::string($form->title));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 20, 3);
        // WPForms
        \add_action('wpforms_process_complete', function ($fields, $entry, $form_data, $entry_id) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(2, \intval($form_data['id']), Security::string($form_data['settings']['form_title']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 4);
        // Contact Form 7
        \add_action('wpcf7_mail_sent', function ($form) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(3, \intval($form->id()), Security::string($form->title()));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        });
        // Gravity Forms
        \add_action('gform_after_submission', function ($entry, $form) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(4, \intval($form['id']), Security::string($form['title']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Ninja Forms
        \add_action('ninja_forms_after_submission', function ($form_data) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(5, \intval($form_data['form_id']), Security::string($form_data['settings']['title']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // MailOptin
        \add_action('mailoptin_track_conversions', function ($lead_data) {
            try {
                if (!\class_exists('\\MailOptin\\Core\\Repositories\\OptinCampaignsRepository')) {
                    return;
                }
                $form_title = \MailOptin\Core\Repositories\OptinCampaignsRepository::get_optin_campaign_name($lead_data['optin_campaign_id']);
                if (\is_null($form_title)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(6, \intval($lead_data['optin_campaign_id']), Security::string($form_title));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Convert Pro
        \add_action('cpro_form_submit', function ($response, $post_data) {
            try {
                $post_id = \intval($post_data['style_id']);
                $post = \get_post($post_id);
                if (\is_null($post)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(7, \intval($post_id), Security::string($post->post_title));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Elementor
        \add_action('elementor_pro/forms/new_record', function ($record) {
            // Elementor form ids are generated using dechex(rand()), so hexdec is required to
            // convert the id back into an integer
            try {
                $submission = new \IAWP\Form_Submissions\Submission(8, \intval(\hexdec($record->get_form_settings('id'))), Security::string($record->get_form_settings('form_name')));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // JetFormBuilder
        \add_action('jet-form-builder/form-handler/after-send', function ($form) {
            try {
                if (!$form->is_success) {
                    return;
                }
                $post_id = \intval($form->form_id);
                $post = \get_post($post_id);
                if (\is_null($post)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(9, \intval($post_id), Security::string($post->post_title));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Formidable Forms
        \add_action('frm_after_create_entry', function ($entry_id, $form_id) {
            try {
                if (!\class_exists('\\FrmForm')) {
                    return;
                }
                $form = \FrmForm::getOne($form_id);
                $submission = new \IAWP\Form_Submissions\Submission(10, \intval($form_id), Security::string($form->name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // WS Form
        \add_action('wsf_submit_post_complete', function ($submission) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(11, \intval($submission->form_id), Security::string($submission->form_object->label));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Amelia
        \add_action('amelia_after_appointment_booking_saved', function ($booking, $reservation) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(12, 1, 'Amelia ' . \__('Appointment', 'independent-analytics'));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Amelia
        \add_action('amelia_after_event_booking_saved', function ($booking, $reservation) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(12, 2, 'Amelia ' . \__('Event', 'independent-analytics'));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Bricks Builder
        \add_action('bricks/form/custom_action', function ($form) {
            try {
                $fields = $form->get_fields();
                if (!\array_key_exists('iawp-form-id', $fields) || \intval($fields['iawp-form-id']) === 0) {
                    return;
                }
                if (!\array_key_exists('iawp-form-title', $fields) || \strlen($fields['iawp-form-title']) === 0) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(13, \intval($fields['iawp-form-id']), Security::string($fields['iawp-form-title']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // ARForms Pro
        \add_action('arfaftercreateentry', function ($entry_id, $form_id) {
            try {
                global $wpdb;
                $forms_table = "{$wpdb->prefix}arf_forms";
                $form_name = Illuminate_Builder::new()->from($forms_table)->where('id', $form_id)->value('name');
                if (\is_null($form_name)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(14, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // ARForms Lite
        \add_action('arfliteaftercreateentry', function ($entry_id, $form_id) {
            try {
                global $wpdb;
                $forms_table = "{$wpdb->prefix}arf_forms";
                $form_name = Illuminate_Builder::new()->from($forms_table)->where('id', $form_id)->value('name');
                if (\is_null($form_name)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(14, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Custom form submissions
        \add_action('iawp_custom_form_submissions', function (int $form_id, string $form_title) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(15, \intval($form_id), Security::string($form_title));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Bit Form
        \add_action('bitform_submit_success', function ($form_id, $entry_id, $form_data) {
            try {
                if (!\class_exists('\\BitCode\\BitForm\\Core\\Form\\FormManager')) {
                    return;
                }
                $form = new \BitCode\BitForm\Core\Form\FormManager($form_id);
                $form_name = $form->getFormName();
                $submission = new \IAWP\Form_Submissions\Submission(16, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 3);
        // Forminator
        \add_action('forminator_form_submit_response', function ($response, $form_id) {
            if (!\function_exists('forminator_get_form_name')) {
                return $response;
            }
            $form_name = \forminator_get_form_name($form_id);
            try {
                $submission = new \IAWP\Form_Submissions\Submission(17, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
            return $response;
        }, 10, 2);
        // Forminator (ajax)
        \add_action('forminator_form_ajax_submit_response', function ($response, $form_id) {
            if (!\function_exists('forminator_get_form_name')) {
                return $response;
            }
            $form_name = \forminator_get_form_name($form_id);
            try {
                $submission = new \IAWP\Form_Submissions\Submission(17, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
            return $response;
        }, 10, 2);
        // Hustle
        \add_action('hustle_form_after_handle_submit', function ($module_id, $response) {
            try {
                if ($response['success'] === \false) {
                    return;
                }
                if (!\class_exists('\\Hustle_Model')) {
                    return;
                }
                $module = \Hustle_Model::get_module($module_id);
                if (\is_wp_error($module)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(18, \intval($module_id), Security::string($module->module_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // Avada
        \add_action('fusion_form_submission_data', function ($form_data, $form_post_id) {
            try {
                $form_id = $form_data['submission']['form_id'];
                $form_name = \get_the_title($form_post_id);
                $submission = new \IAWP\Form_Submissions\Submission(19, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 2);
        // WP Store Locator
        \add_action('wpsl_store_search', function () {
            try {
                $is_autoloaded = isset($_GET['autoload']) && $_GET['autoload'];
                // This hooks fires after the locations are fetched on page load. We only want to track
                // manual form submissions.
                if ($is_autoloaded) {
                    return;
                }
                // There's only one possible form for this plugin. This is why the form id and the forms
                // name are hardcoded.
                $submission = new \IAWP\Form_Submissions\Submission(20, \intval(1), Security::string('WP Store Locator'));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 0);
        // Thrive
        \add_action('tcb_api_form_submit', function ($data) {
            try {
                // This parsing of the object is copied from Thrive Leads own tve_leads_process_conversion function
                $form_id = !empty($data['thrive_leads']['tl_data']['form_type_id']) ? $data['thrive_leads']['tl_data']['form_type_id'] : null;
                $form_name = !empty($data['thrive_leads']['tl_data']['form_name']) ? $data['thrive_leads']['tl_data']['form_name'] : null;
                if (!\is_numeric($form_id) || !\is_string($form_name)) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(21, \intval($form_id), Security::string($form_name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // SureForms
        \add_action('srfm_form_submit', function ($data) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(22, \intval($data['form_id']), Security::string($data['form_name']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Kali Forms
        \add_action('kaliforms_after_form_process_action', function ($data) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(23, \intval($data['data']['formId']), Security::string(\get_the_title($data['data']['formId'])));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        \add_action('et_pb_contact_form_submit', function ($values, $has_error, $form_info) {
            try {
                if ($has_error === \true) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(24, \intval(1), Security::string(\__('Divi Contact Form', 'independent-analytics')));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 3);
        // MailPoet
        \add_action('mailpoet_subscription_before_subscribe', function ($data, $segmentIds, $form) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(25, \intval($form->getId()), Security::string($form->getName()));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 3);
        // Mailchimp
        \add_action('mc4wp_form_success', function ($form) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(26, \intval($form->ID), Security::string($form->name));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Kadence
        \add_action('kadence_blocks_advanced_form_submission', function ($form, $fields, $post_id) {
            try {
                $submission = new \IAWP\Form_Submissions\Submission(27, \intval($post_id), Security::string(\get_the_title($post_id)));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 3);
        // Newletter
        \add_action('newsletter_user_post_subscribe', function ($user) {
            try {
                $form = \IAWP\Form_Submissions\Newsletter::get_form($user->referrer ?? null);
                if (!$form) {
                    return;
                }
                $submission = new \IAWP\Form_Submissions\Submission(29, \intval($form['id']), Security::string($form['title']));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 1);
        // Everest Forms
        \add_action('everest_forms_process_complete', function ($fields, $entry, $form_data, $entry_id) {
            try {
                $id = $form_data['id'];
                $submission = new \IAWP\Form_Submissions\Submission(30, \intval($id), Security::string(\get_the_title($id)));
                $submission->record_submission();
            } catch (\Throwable $e) {
            }
        }, 10, 4);
        // Template
        // add_action('iawp_some_form_callback', function () {
        //     try {
        //         return;
        //         $submission = new Submission(
        //             0,
        //             intval(0), // Form id
        //             Security::string('') // Form title
        //         );
        //         $submission->record_submission();
        //     } catch (\Throwable $e) {
        //
        //     }
        // }, 10, 0);
    }
}
