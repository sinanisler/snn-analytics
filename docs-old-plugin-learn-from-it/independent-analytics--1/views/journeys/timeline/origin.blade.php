@php
/** @var IAWP\Journey\Events\Origin $event */
@endphp

<?php if(is_string($event->referrer_url())): ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Referrer:', 'independent-analytics'); ?></span> 
        <a href="<?php echo esc_url($event->referrer_url()); ?>" target="_blank">
            <?php echo esc_html($event->label()); ?><span class="dashicons dashicons-external"></span>
        </a> 
        
    </p>
<?php else: ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Referrer:', 'independent-analytics'); ?></span> 
        <?php echo esc_html($event->label()); ?>
    </p>
<?php endif; ?>

<?php if($event->has_utm_parameters()): ?>
    <div>
        <?php if($event->utm_source()): ?>
            <p>
                <span class="timeline-contents-label"><?php esc_html_e('Source:', 'independent-analytics'); ?></span> 
                <?php echo esc_html($event->utm_source()); ?>
            </p>
        <?php endif; ?>
        <?php if($event->utm_medium()): ?>
            <p>
                <span class="timeline-contents-label"><?php esc_html_e('Medium:', 'independent-analytics'); ?></span> 
                <?php echo esc_html($event->utm_medium()); ?>
            </p>
        <?php endif; ?>
        <?php if($event->utm_campaign()): ?>
            <p>
                <span class="timeline-contents-label"><?php esc_html_e('Campaign:', 'independent-analytics'); ?></span> 
                <?php echo esc_html($event->utm_campaign()); ?>
            </p>
        <?php endif; ?>
        <?php if($event->utm_term()): ?>
            <p>
                <span class="timeline-contents-label"><?php esc_html_e('Term:', 'independent-analytics'); ?></span> 
                <?php echo esc_html($event->utm_term()); ?>
            </p>
        <?php endif; ?>
        <?php if($event->utm_content()): ?>
            <p>
                <span class="timeline-contents-label"><?php esc_html_e('Content:', 'independent-analytics'); ?></span> 
                <?php echo esc_html($event->utm_content()); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
