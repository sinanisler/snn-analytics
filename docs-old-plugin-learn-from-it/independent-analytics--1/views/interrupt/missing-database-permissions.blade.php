@php /** @var string[] $missing_privileges */ @endphp
<div class="settings-container interrupt-message"
     data-controller="migration-redirect"
>
    <h2><?php esc_html_e('Missing Database Permissions', 'independent-analytics'); ?></h2>
    <p>
        <?php esc_html_e("Your site is missing the following database privileges required to run Independent Analytics:", 'independent-analytics'); ?> 
        <?php echo implode(', ', $missing_privileges); ?>
    </p>
    <p>
        <?php esc_html_e('Please follow our tutorial to enable these permissions on your site and analytics tracking will begin right away:', 'independent-analytics'); ?>
        
    </p>
    <p>
        <a href="https://independentwp.com/knowledgebase/common-questions/missing-database-permissions/"
            class="iawp-button purple"
            target="_blank">
            <span class="dashicons dashicons-sos"></span>
            <span><?php esc_html_e('Follow Tutorial', 'independent-analytics'); ?></span>
        </a>
    </p>
</div>