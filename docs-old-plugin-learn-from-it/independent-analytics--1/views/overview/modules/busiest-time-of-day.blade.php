@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Busiest_Time_Of_Day_Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var ?array $dataset */ @endphp

<?php
if (is_array($dataset)) : ?>
    <div data-controller="chart"
            class="module-chart"
            data-chart-labels-value="<?php echo esc_attr(json_encode($module->get_labels($dataset))); ?>"
            data-chart-data-value="<?php echo esc_attr(json_encode([
                'sessions' => $module->get_sessions($dataset),
            ])); ?>"
            data-chart-locale-value="<?php echo esc_attr(get_bloginfo('language')); ?>"
            data-chart-currency-value="<?php echo esc_attr(iawp()->get_currency_code()); ?>"
            data-chart-is-preview-value="1"
            data-chart-primary-chart-metric-id-value="sessions"
            data-chart-primary-chart-metric-name-value="<?php esc_html_e('Sessions', 'independent-analytics'); ?>"
            data-chart-secondary-has-multiple-datasets-value="0"
    >
        <canvas data-chart-target="canvas" height="300"></canvas>
    </div><?php
else : ?>
    <div class="loading-message">
        <img src="<?php echo esc_url(iawp_url_to('img/loading.svg')) ?>" />
        <p><?php esc_html_e('Loading data...', 'independent-analytics'); ?></p>
    </div><?php
endif;