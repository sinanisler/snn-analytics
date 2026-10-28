@php
/** @var IAWP\Journey\Events\Click $event */
@endphp

<?php if($event->has_multiple_rules()): ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Link Patterns:', 'independent-analytics'); ?></span>
        <ol>
            <?php foreach ($event->rules() as $rule): ?>
                <li><?php echo esc_html($rule); ?></li>
            <?php endforeach; ?>
        </ol>
    </p>
<?php else: ?>
    <p>
        <span class="timeline-contents-label"><?php esc_html_e('Link Pattern:', 'independent-analytics'); ?></span>
        <?php echo esc_html($event->rule()); ?>
    </p>
<?php endif; ?>
<p>
    <span class="timeline-contents-label"><?php esc_html_e('Target:', 'independent-analytics'); ?></span>
    <?php if ($event->is_url_target()) : ?>
        <a href="<?php echo esc_url($event->target()); ?>" target="_blank">
            <?php echo esc_html($event->target()); ?><span class="dashicons dashicons-external"></span>
        </a>
    <?php else: ?>
        <?php echo esc_html($event->target()); ?>
    <?php endif; ?>
</p>
