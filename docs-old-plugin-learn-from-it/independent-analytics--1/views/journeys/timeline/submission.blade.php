@php
/** @var IAWP\Journey\Events\Submission $event */
@endphp

<p>
    <span class="timeline-contents-label"><?php esc_html_e('Plugin:', 'independent-analytics'); ?></span>
    <span><?php echo esc_html($event->plugin_name()); ?></span>
</p>
<p>
    <span class="timeline-contents-label"><?php esc_html_e('Form:', 'independent-analytics'); ?></span>
    <span><?php echo esc_html($event->form_title()); ?></span>
</p>
