@php
/** @var IAWP\Journey\Events\View $event */
@endphp

<?php if($event->url()): ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Page:', 'independent-analytics'); ?></span>
        <a href="<?php echo esc_url($event->url()); ?>" target="_blank">
            <?php esc_html_e($event->title()) ?><span class="dashicons dashicons-external"></span>
        </a> 
    </p>
<?php else: ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Page:', 'independent-analytics'); ?></span> 
        <?php echo esc_html($event->title()); ?>
    </p>
<?php endif; ?>
<?php if($event->duration()): ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Duration:', 'independent-analytics'); ?></span> 
        <?php echo esc_html($event->duration()); ?>
    </p>
<?php endif; ?>
