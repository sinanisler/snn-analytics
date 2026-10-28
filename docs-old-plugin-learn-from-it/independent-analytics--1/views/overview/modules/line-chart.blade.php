@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var ?array $dataset */ @endphp

<?php
if ($is_loaded) : ?>
    <div data-controller="chart"
        class="module-chart"
        data-chart-labels-value="<?php echo esc_attr(json_encode($dataset['labels'])); ?>"
        data-chart-data-value="<?php echo esc_attr(json_encode([
            $dataset['primary_dataset_id'] => $dataset['primary_dataset'],
            $dataset['secondary_dataset_id'] => $dataset['secondary_dataset'],
        ])); ?>"
        data-chart-locale-value="<?php echo esc_attr(get_bloginfo('language')); ?>"
        data-chart-currency-value="<?php echo esc_attr(iawp()->get_currency_code()); ?>"
        data-chart-is-preview-value="1"
        data-chart-show-legend-value="1"
        data-chart-primary-chart-metric-id-value="<?php echo esc_attr($dataset['primary_dataset_id']); ?>"
        data-chart-primary-chart-metric-name-value="<?php echo esc_attr($dataset['primary_dataset_name']); ?>"
        data-chart-secondary-chart-metric-id-value="<?php echo esc_attr($dataset['secondary_dataset_id']); ?>"
        data-chart-secondary-chart-metric-name-value="<?php echo esc_attr($dataset['secondary_dataset_name']); ?>"
        data-chart-secondary-has-multiple-datasets-value="<?php echo is_array($dataset['secondary_dataset']) ? '1' : '0'; ?>"
    >
        <canvas data-chart-target="canvas" height="300"></canvas>
    </div><?php
else : ?>
    <div class="loading-message">
        <img src="<?php echo esc_url(iawp_url_to('img/loading.svg')) ?>" />
        <p><?php esc_html_e('Loading data...', 'independent-analytics'); ?></p>
    </div><?php 
endif;