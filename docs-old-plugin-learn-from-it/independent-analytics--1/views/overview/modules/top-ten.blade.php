@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Top_Ten_Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var ?array $dataset */ @endphp

<?php
if ($is_empty) : ?>
    <p class="no-data-message"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('No data found in this date range.', 'independent-analytics'); ?></p><?php
endif; ?>

<div class="iawp-module-table"><?php 
    if (!$is_empty) : ?>
        <span class="iawp-module-table-heading"><?php echo sanitize_text_field($module->primary_column_name()); ?></span>
        <span class="iawp-module-table-heading"><?php echo sanitize_text_field($module->metric_column_name()); ?></span><?php
    endif; 
    if ($is_loaded) :
        $counter = 1;
        foreach ($dataset as $item) : ?>
            <span>
                <span class="module-row-number"><?php echo sanitize_text_field($counter); ?></span>
                <span><?php echo sanitize_text_field($item[0]); ?></span>
            </span>
            <span><?php echo sanitize_text_field($item[1]); ?></span><?php
            $counter++;
        endforeach;
    else :
        for ($i = 0; $i < 20; $i++) : ?>
            <span class="skeleton-loader"></span><?php
        endfor;
    endif; ?>
</div>