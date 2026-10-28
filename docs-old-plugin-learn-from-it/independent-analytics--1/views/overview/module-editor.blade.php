@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Module $module */ @endphp

<div class="iawp-module module-editor <?php echo $module !== null && $module->is_full_width() ? 'full-width' : ''; ?>"
     data-controller="module-editor"
     data-module-editor-module-id-value="<?php echo esc_attr($module->id()); ?>"
     data-module-editor-reports-value="<?php echo esc_attr(json_encode($module->get_report_details())); ?>">
    <header class="module-header">
        <div class="module-icon"><?php 
            echo iawp_blade()->run('icons.overview.' . $module->module_type()); ?>
        </div>
        <div class="module-title-container">
            <h2><?php echo esc_html($module->module_name()); ?></h2>
        </div>
        <button class="iawp-button module-editing-buttons change-module-type" data-action="module-editor#changeModuleType"><?php esc_html_e('Change Module Type', 'independent-analytics'); ?></button>
        <button class="iawp-button module-editing-buttons cancel-module-edit" data-module-editor-target="cancelButton" data-action="module-editor#cancel"><?php esc_html_e('Cancel', 'independent-analytics'); ?></button>
    </header>

    <div class="module-contents">
        <div>
            <form class="iawp-module-editor-form" data-action="module-editor#save">
                <input type="hidden" name="module_type" value="<?php echo esc_attr($module->module_type()); ?>">
                <div>
                    <label><?php esc_html_e('Name', 'independent-analytics'); ?></label>
                    <input type="text"
                        name="name"
                        value="<?php echo esc_attr($module->is_saved() ? $module->name() : $module->module_name()); ?>"
                        autofocus
                        required
                        data-1p-ignore
                    >
                </div>

                <?php echo $module->get_form_fields_html(); ?>

                <footer>
                    <button type="submit"
                            class="module-save-button iawp-button purple"
                            data-module-editor-target="saveButton"
                            data-loading-text="<?php esc_html_e('Saving...'); ?>"
                    >
                    <?php echo $module->is_saved() ? esc_html__('Save', 'independent-analytics') : esc_html__('Add Module', 'independent-analytics'); ?>
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>