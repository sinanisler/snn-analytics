@php /** @var \IAWP\Chart $chart */ @endphp
@php /** @var \IAWP\Statistics\Intervals\Interval[] $intervals */ @endphp
@php /** @var \IAWP\Statistics\Intervals\Interval $current_interval */ @endphp
@php /** @var array $stimulus_values */ @endphp
@php /** @var array $available_datasets */ @endphp
@php /** @var string $primary_chart_metric_id */ @endphp
@php /** @var ?string $secondary_chart_metric_id */ @endphp

<div class="chart-container">
    <div class="chart-inner"
        data-testid="chart"
        data-controller="chart"
        <?php foreach($stimulus_values as $key => $value) : ?>
            data-chart-<?php echo esc_attr($key); ?>-value="<?php echo is_array($value) ? esc_attr($chart->encode_json($value)) : esc_attr($value); ?>"
        <?php endforeach; ?>
    >
        <div class="legend-container">
            <div class="legend" style="display: none;"></div>
            <?php if (!$chart->is_preview()) : ?>
                <div class="primary-metric-select-container metric-select-container">
                    <select id="primary-metric-select" 
                            data-chart-target="primaryMetricSelect"
                            data-action="chart#changePrimaryMetric"
                    >
                        <?php foreach($available_datasets as $group) : ?>
                            <optgroup label="<?php echo esc_attr($group['name']); ?>">
                                <?php foreach($group['items'] as $item) : ?>
                                    <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($primary_chart_metric_id, $item['id'], true); ?> <?php echo $secondary_chart_metric_id === $item['id'] ? 'disabled' : ''; ?>>
                                        <?php echo esc_html($item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="secondary-metric-select-container metric-select-container">
                    <select id="secondary-metric-select" 
                            data-chart-target="secondaryMetricSelect"
                            data-action="chart#changeSecondaryMetric"
                    >
                        <option value="no_comparison" <?php selected(is_null($secondary_chart_metric_id)); ?>><?php esc_html_e('No Comparison', 'independent-analytics'); ?></option>
                        <?php foreach($available_datasets as $group) : ?>
                            <optgroup label="<?php echo esc_attr($group['name']); ?>">
                                <?php foreach($group['items'] as $item) : ?>
                                    <option value="<?php echo esc_attr($item['id']); ?>" <?php selected($secondary_chart_metric_id, $item['id'], true); ?> <?php echo $primary_chart_metric_id === $item['id'] ? 'disabled' : ''; ?>>
                                        <?php echo esc_html($item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <select class="adaptive-select-width" data-chart-target="adaptiveWidthSelect">
                    <option></option>
                </select>

                <select id="chart-interval-select" class="chart-interval-select"
                        data-controller="chart-interval"
                        data-action="chart-interval#setChartInterval">
                    <?php foreach($intervals as $interval) :?>
                        <option value="<?php echo esc_attr($interval->id()); ?>" <?php selected($interval->equals($current_interval)); ?>>
                            <?php echo esc_html($interval->label()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <canvas id="independent-analytics-chart" data-chart-target="canvas"></canvas>
    </div>
</div>