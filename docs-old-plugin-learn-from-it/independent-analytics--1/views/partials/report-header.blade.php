@php /** @var \IAWP\Report $report */ @endphp
@php /** @var bool $can_edit */ @endphp

<div id="report-title-bar" class="report-title-bar"><?php
    if ($report->is_saved_report()) : ?>
        <div data-controller="<?php echo $can_edit ? "rename-report" : ""; ?>"
             data-rename-report-id-value="<?php echo esc_attr($report->id()); ?>"
             data-rename-report-name-value="<?php echo esc_attr($report->name()); ?>"
             class="modal-parent small rename-report">
            <a id="rename-link" class="rename-link <?php echo !$can_edit ? 'no-edit' : ''; ?>" href="#"
               data-action="click->rename-report#toggleModal"
               data-rename-report-target="modalButton"
               title="<?php echo esc_attr($report->name()); ?>">
                <h1 data-name-for-report-id="<?php echo esc_attr($report->id()); ?>"
                    class="report-title"><?php echo esc_html($report->name()); ?></h1><?php 
                if ($can_edit) : ?>
                    <span class="dashicons dashicons-edit"></span><?php
                endif; ?>
            </a><?php
            if ($can_edit) : ?>
                <div class="iawp-modal small" data-rename-report-target="modal">
                    <div class="modal-inner">
                        <div class="title-small">
                            <?php esc_html_e('Rename report', 'independent-analytics'); ?>
                        </div>
                        <p><?php esc_html_e('Give this report a new name', 'independent-analytics'); ?></p>
                        <form data-action="rename-report#rename">
                            <input type="text" data-rename-report-target="input"
                                   placeholder="Report name" required>
                            <button data-rename-report-target="renameButton"
                                    class="iawp-button purple"><?php esc_html_e('Update title', 'independent-analytics'); ?>
                            </button>
                        </form>
                    </div>
                </div><?php
            endif; ?>
        </div><?php 
    else : ?>
        <div class="primary-report-title-container">
            <h1 class="report-title"><?php echo esc_html($report->name()); ?></h1>
        </div><?php
    endif; 
    if ($can_edit) : ?>
        <div class="buttons"><?php
            if ($report->is_saved_report()) : ?>
                <div data-controller="save-report" data-save-report-id-value="<?php echo esc_html($report->id()); ?>"
                     class="save-report">
                    <p data-save-report-target="warning" style="display: none;"
                       class="unsaved-warning"><span class="dashicons dashicons-warning"></span>
                        <span class="text"><?php esc_html_e('You have unsaved changes', 'independent-analytics'); ?></span></p>
                    <button id="save-report-button" 
                            data-save-report-target="button"
                            data-action="save-report#save"
                            class="save-report-button iawp-button"><?php esc_html_e('Save', 'independent-analytics'); ?></button>
                </div><?php
            endif; ?>

            <div data-controller="copy-report" <?php
                 if($report->is_saved_report()) : ?>
                     data-copy-report-id-value="<?php echo esc_attr($report->id()); ?>" <?php
                 else : ?>
                     data-copy-report-type-value="<?php echo esc_attr($report->type()); ?>" <?php
                 endif; ?>
                 class="modal-parent small copy-report"
            >
                <button id="save-as-report-button"
                        data-action="click->copy-report#toggleModal"
                        data-copy-report-target="modalButton"
                        class="save-as-report-button iawp-button"><?php esc_html_e('Save As', 'independent-analytics'); ?></button>
                <div class="iawp-modal small" data-copy-report-target="modal">
                    <div class="modal-inner">
                        <div class="title-small">
                            <?php esc_html_e('Create new report', 'independent-analytics'); ?>
                        </div>
                        <p><?php esc_html_e('Enter a name for the new report.', 'independent-analytics'); ?></p>
                        <form data-action="copy-report#copy">
                            <input type="text" data-copy-report-target="input"
                                   placeholder="Report name" required>
                            <button data-copy-report-target="copyButton" class="iawp-button purple">
                                <?php esc_html_e('Save as', 'independent-analytics'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div>
                <button id="favorite-report-button"
                        data-controller="set-favorite-report"
                        data-set-favorite-report-id-value="<?php echo esc_attr($report->is_saved_report() ? $report->id() : ''); ?>"
                        data-set-favorite-report-type-value="<?php echo esc_attr($report->is_saved_report() ? '' : $report->type()); ?>"
                        data-action="set-favorite-report#setFavoriteReport"
                        class="iawp-button favorite <?php echo $report->is_favorite() ? 'active' : ''; ?>"
                >
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Make default', 'independent-analytics'); ?>
                </button>
            </div><?php
            if ($report->is_saved_report()) : ?>
                <div data-controller="delete-report" data-delete-report-id-value="<?php echo esc_attr($report->id()); ?>"
                     class="modal-parent small delete-report">
                    <button id="delete-report-button"
                            data-action="delete-report#toggleModal"
                            data-delete-report-target="modalButton" class="iawp-button">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <div class="iawp-modal small" data-delete-report-target="modal">
                        <div class="modal-inner">
                            <div class="title-small">
                                <?php esc_html_e('Confirm', 'independent-analytics'); ?>
                            </div>
                            <p><?php esc_html_e('Are you sure you want to delete this report?', 'independent-analytics'); ?></p>
                            <button data-action="delete-report#delete"
                                    data-delete-report-target="deleteButton"
                                    class="iawp-button red"><?php esc_html_e('Delete report', 'independent-analytics'); ?>
                            </button>
                        </div>
                    </div>
                </div><?php
            endif; ?>
        </div><?php
    endif; ?>
</div>