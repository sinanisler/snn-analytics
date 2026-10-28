@php
/** @var IAWP\Journey\Events\Order $event */
@endphp

<p>
    <span class="timeline-contents-label"><?php esc_html_e('Revenue:', 'independent-analytics'); ?></span> 
    <?php echo esc_html($event->total()); ?> 
    <?php if ($event->total_refunded()) : ?> 
        <span>(<?php echo esc_html($event->total_refunded()); ?>)</span>
    <?php endif; ?>
</p>

<?php if($event->admin_url()): ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Order ID:', 'independent-analytics') ?></span>
        <a href="<?php echo esc_url($event->admin_url()); ?>" target="_blank">
            <?php echo '#' . esc_html($event->order_id()); ?><span class="dashicons dashicons-external"></span>
        </a> 
    </p>
<?php else: ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Order ID:', 'independent-analytics'); ?></span> 
        <?php echo '#' . esc_html($event->order_id()); ?>
    </p>
<?php endif; ?>

<p>
    <span class="timeline-contents-label"><?php esc_html_e('Status:', 'independent-analytics'); ?></span> 
    <?php echo esc_html($event->status()); ?>
</p>
