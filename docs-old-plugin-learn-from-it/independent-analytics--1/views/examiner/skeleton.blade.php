<div class="examiner-skeleton">
    <div class="examiner-header" data-controller="examiner-header">
        <div class="report-title-bar">
            <div>
                <h2 class="report-title"></h2> <!-- Text updated in examiner_controller.js -->
                <span class="report-subtitle"></span> <!-- Text updated in examiner_controller.js -->
            </div>
            <div>
                <button class="close-examiner-button" data-action="examiner#close"><span class="dashicons dashicons-dismiss"></span></button>
            </div>
        </div>
        <div class="toolbar">
            <div class="date-picker-parent">
                <div class="modal-parent dates">
                    <button class="iawp-button">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span class="iawp-label"></span> <!-- Text updated in examiner_controller.js -->
                    </button>
                </div>
            </div>
            <div class="download-options-parent" >
                <div class="modal-parent downloads">
                    <button class="download-options">
                        <?php esc_html_e('Download Report', 'independent-analytics'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="loading-message-container">
        <p class="loading-message"><?php echo iawp_blade()->run('icons.download'); ?> <?php esc_html_e('Fetching data', 'independent-analytics'); ?></p>
        <p class="loading-message delay-1"><?php echo iawp_blade()->run('icons.preparing-report'); ?> <?php esc_html_e('Preparing report', 'independent-analytics'); ?></p>
        <p class="loading-message delay-2"><?php echo iawp_blade()->run('icons.complete'); ?> <?php esc_html_e('Almost ready', 'independent-analytics'); ?></p>
    </div>
</div>