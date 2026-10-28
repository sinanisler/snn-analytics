<div id="iawp-parent" class="iawp-parent">
    <div class="header">
        <div class="logo">
            <img src="<?php echo esc_url(iawp_url_to('img/logo.png')); ?>" data-testid="logo"/>
        </div>
        <a href="https://independentwp.com/knowledgebase/"
           class="iawp-button purple"
           target="_blank">
            <span class="dashicons dashicons-sos"></span>
            <span><?php esc_html_e('Get Help', 'independent-analytics'); ?></span>
        </a>
    </div>
    <div class="settings-container interrupt-message"
        data-controller="migration-redirect"
    >
        <div id="iawp-update-running">
            <h2><?php esc_html_e('Update running', 'independent-analytics'); ?></h2>
            <p>
                <?php esc_html_e("We're running an update designed to speed up and improve Independent Analytics.
                This can take anywhere from 5 seconds to 5 minutes.", 'independent-analytics'); ?>
            </p>
            <p>
                <?php esc_html_e("Your site's performance is not impacted by this update. Analytics tracking will resume once the update is complete.", 'independent-analytics'); ?>
            </p>
            <p>
                <strong><?php esc_html_e("This page will automatically refresh when the update's finished.", 'independent-analytics'); ?></strong>
            </p>
            <p><span class="dashicons dashicons-update iawp-spin"></span></p>
        </div>
        <div id="iawp-migration-error" class="iawp-migration-error"></div>
    </div>
</div>


