@php /** @var IAWP\Models\Referrer $row */ @endphp

<?php if($row->referrer_favicon_url()): ?>
    <img src="<?php echo esc_url($row->referrer_favicon_url()); ?> " class="iawp-favicon" alt="<?php echo esc_attr($row->referrer()); ?>"/>
<?php elseif ($row->is_direct()): ?>
    <img src="<?php echo esc_url(iawp_url_to('img/direct.png')); ?>" class="iawp-favicon" alt="<?php echo esc_attr($row->referrer()); ?>"/>
<?php else: ?>
    <span data-favicon-id="<?php echo esc_attr($row->fallback_favicon_color_id()); ?>" class="iawp-favicon backup-icon"><?php echo esc_html(substr($row->referrer(), 0, 1)); ?></span>
<?php endif; ?>

<?php if($row->has_link()): ?>
    <a href="<?php echo esc_url($row->referrer()); ?>" class="external-link" target="_blank"><?php echo esc_html($row->referrer()); ?><span class="dashicons dashicons-external"></span></a>
<?php else: ?>
    <span><?php echo esc_html($row->referrer()); ?></span>
<?php endif; ?>
