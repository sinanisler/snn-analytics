@php /** @var $pruner IAWP\Data_Pruning\Pruning_Scheduler */ @endphp

<div data-controller="pruner" class="settings-container prune">
    <div class="heading">
        <h2><?php
            esc_html_e('Automatically Delete Old Data', 'independent-analytics'); ?></h2>
        <a class="tutorial-link" href="https://independentwp.com/knowledgebase/your-data/automatically-delete-old-data/" target="_blank">
            <?php
            esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <div class="schedule-notification <?php echo $pruner->is_enabled() ? 'is-scheduled' : ''; ?>"
         data-pruner-target="statusMessage"
         data-testid="data-pruner-notice">
        <span class="dashicons dashicons-yes-alt"></span>
        <span class="dashicons dashicons-dismiss"></span>
        <p><?php
            $status_message = $pruner->status_message();
            if (!is_null($status_message)) {
                echo wp_kses_post($status_message);
            } ?>
        </p>
    </div>
    <div class="iawp-section">
        <select data-pruner-target="cutoffs" data-action="pruner#selectChanged" data-testid="data-pruner-select"><?php
            foreach ($pruner->cutoff_options() as $cutoff_option) : ?>
                <option value="<?php echo esc_attr($cutoff_option[0]); ?>" <?php
                    echo $cutoff_option[0] === $pruner->get_pruning_cutoff() ? 'selected' : ''; ?>><?php echo esc_html($cutoff_option[1]); ?>
                </option><?php
            endforeach; ?>
        </select>
    </div>
    <div class="button-group">
        <button class="iawp-button purple"
                data-pruner-target="saveButton"
                data-action="pruner#saveClick"
                data-original-text="<?php esc_html_e('Save', 'independent-analytics'); ?>"
                data-loading-text="<?php esc_html_e('Saving...', 'independent-analytics'); ?>"
                disabled="disabled"
                data-testid="save-data-pruner"
        >
            <?php esc_html_e('Save', 'independent-analytics'); ?>
        </button>
    </div>
    <!-- Confirmation modal -->
    <div id="prune-modal" aria-hidden="true" class="mm micromodal-slide" data-testid="prune-modal">
        <div tabindex="-1" class="mm__overlay" data-action="click->pruner#cancelConfirmation">
                <div role="dialog" aria-modal="true" aria-labelledby="raa-modal-title"
                     class="mm__container">
                    <h1><?php
                        esc_html_e('Enable automatic data deletion', 'independent-analytics'); ?></h1>
                    <p>
                        <?php
                        esc_html_e(
                            'This will delete all data older then the selected timeframe, reducing the size of the database tables that Independent Analytics uses.',
                            'independent-analytics'
                        ) ?>
                    </p>
                    <p>
                        <strong data-pruner-target="confirmationText" data-testid="date-confirmation"></strong>
                    </p>
                    <button type="submit"
                            class="iawp-button purple"
                            data-action="pruner#confirmClick"
                            data-pruner-target="confirmButton"
                            data-original-text="<?php esc_attr_e('Enable Automatic Data Deletion', 'independent-analytics'); ?>"
                            data-loading-text="<?php esc_attr_e('Enabling Automatic Data Deletion...', 'independent-analytics'); ?>"
                            data-testid="submit-data-pruner"
                    >
                        <?php
                        esc_html_e('Enable Automatic Data Deletion', 'independent-analytics'); ?>
                    </button>
                    <button class="iawp-button ghost-purple"
                            data-action="pruner#cancelConfirmation"
                            data-testid="close-data-pruner"
                    >
                        <?php
                        esc_html_e('Cancel', 'independent-analytics') ?>
                    </button>
            </div>
        </div>
    </div>
</div>
