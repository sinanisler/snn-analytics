@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var ?array $dataset */ @endphp

<?php

if ($is_empty) : ?>
    <p class="no-data-message"><span class="dashicons dashicons-chart-bar"></span> <?php echo esc_html__('No data found in this date range.', 'independent-analytics'); ?></p><?php
endif;

if ($is_loaded) : ?>
    <div data-controller="pie-chart"
            class="module-chart module-pie-chart"
            data-pie-chart-data-value="<?php echo esc_attr(json_encode($dataset)); ?>"
            data-pie-chart-locale-value="<?php echo esc_attr(get_bloginfo('language')); ?>"
    >
        <canvas data-pie-chart-target="canvas"></canvas>
    </div><?php
else : ?>
    <div class="loading-message">
        <img src="<?php echo esc_url(iawp_url_to('img/loading.svg')) ?>" />
        <p><?php echo esc_html__('Loading data...', 'independent-analytics'); ?></p>
    </div><?php
endif;