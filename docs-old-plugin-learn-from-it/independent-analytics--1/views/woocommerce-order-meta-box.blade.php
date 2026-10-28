<p class="iawp-referrer-box-title"><?php esc_html_e('Time of Arrival', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="time-of-arrival">{{ $record->arrived_at }}</p>

<p class="iawp-referrer-box-title"><?php esc_html_e('Referrer', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="referrer"><?php echo $record->referrer ?></p>

<?php if (!is_null($record->utm_source)) : ?>
    <p class="iawp-referrer-box-title"><?php esc_html_e('Campaign Data', 'independent-analytics'); ?></p>
    <div>
        <p class="iawp-referrer-box-campaign-value" data-testid="source"><strong><?php esc_html_e('Source:', 'independent-analytics'); ?></strong> <?php echo $record->utm_source ?></p>
        <p class="iawp-referrer-box-campaign-value" data-testid="medium"><strong><?php esc_html_e('Medium:', 'independent-analytics'); ?></strong> <?php echo $record->utm_medium ?></p>
        <p class="iawp-referrer-box-campaign-value" data-testid="campaign"><strong><?php esc_html_e('Campaign:', 'independent-analytics'); ?></strong> <?php echo $record->utm_campaign ?></p>
        <?php if ($record->utm_term) : ?>
            <p class="iawp-referrer-box-campaign-value" data-testid="term"><strong><?php esc_html_e('Term:', 'independent-analytics'); ?></strong> <?php echo $record->utm_term ?></p>
        <?php endif; ?>
        <?php if ($record->utm_content) : ?>
            <p class="iawp-referrer-box-campaign-value" data-testid="content"><strong><?php esc_html_e('Content:', 'independent-analytics'); ?></strong> <?php echo $record->utm_content ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p class="iawp-referrer-box-title"><?php esc_html_e('Landing Page', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="landing-page"><a href="<?php echo $record->initial_page_url ?>" target="_blank"><?php echo $record->initial_page_title ?></a></p>

<p class="iawp-referrer-box-title"><?php esc_html_e('Pages viewed', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="pages-viewed"><?php echo $record->total_views ?></p>

<p class="iawp-referrer-box-title"><?php esc_html_e('Time of Purchase', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="time-of-purchase">{{ $record->ordered_at }}</p>

<p class="iawp-referrer-box-title"><?php esc_html_e('Device Data', 'independent-analytics'); ?></p>
<p class="iawp-referrer-box-value" data-testid="device-data"><?php echo $record->device_type ?> &bull; <?php echo $record->device_os ?> &bull; <?php echo $record->device_browser ?></p>

<p class="iawp-referrer-box-title"><?php esc_html_e('Location', 'independent-analytics'); ?></p>
<?php if($record->country && $record->city): ?>
    <p class="iawp-referrer-box-value" data-testid="geolocation"><?php echo $record->country ?> &bull; <?php echo $record->city ?></p>
<?php else: ?>
    <p class="iawp-referrer-box-value" data-testid="geolocation"><?php esc_html_e('Unknown', 'independent-analytics'); ?></p>
<?php endif; ?>

<p class="iawp-view-journey">
    <a href="<?php echo esc_url($journey_url); ?>"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('View full journey', 'independent-analytics'); ?> <span class="iawp-arrow">&rarr;</span></a>
</p>

<p class="iawp-referrer-box-attribution"><?php 
    printf(esc_html__('Powered by %s.', 'independent-analytics'), iawp_is_pro() ? 'Independent Analytics Pro' : 'Independent Analytics'); 
?></p>
