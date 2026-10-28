<div class="examiner-header" data-controller="examiner-header">
    <div class="report-title-bar">
        <div>
            <h2 class="report-title"><?php echo esc_html($model->examiner_title()); ?></h2>
            <span class="report-subtitle"><?php echo iawp_blade()->run('icons.' . $type); ?><?php echo esc_html($group->singular()); ?></span>
        </div>
        <div>
            <button class="close-examiner-button" data-action="examiner-header#askToBeClosed"><span class="dashicons dashicons-dismiss"></span></button>
        </div>
    </div>
</div>
