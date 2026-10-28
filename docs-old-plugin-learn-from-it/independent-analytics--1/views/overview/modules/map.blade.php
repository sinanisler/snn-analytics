@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var ?array $dataset */ @endphp

<?php

if ($is_loaded) : ?>
    <div class="chart-container">
        <div class="chart-inner">
            <div id="independent-analytics-chart"
                    data-controller="map"
                    data-map-data-value="<?php echo esc_attr(json_encode($dataset)) ?>"
                    data-map-flags-url-value="<?php echo esc_url(iawp_url_to('/img/flags')) ?>"
                    data-map-locale-value="<?php echo esc_attr(get_bloginfo('language')); ?>"
            >
                <div data-map-target="chart"></div>
            </div>
        </div>
    </div><?php
else : ?>
    <div class="loading-message">
        <img src="<?php echo esc_url(iawp_url_to('img/loading.svg')) ?>" />
        <p><?php esc_html_e('Loading data...', 'independent-analytics'); ?></p>
    </div><?php
endif;