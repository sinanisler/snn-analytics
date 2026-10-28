<div id="report-title-bar" class="report-title-bar">
    <div class="primary-report-title-container">
        <h1 class="report-title"><?php esc_html_e('Real-Time', 'independent-analytics'); ?></h1>
    </div>

    <div class="buttons">
        <div>
            <button id="favorite-report-button"
                    data-controller="set-favorite-report"
                    data-set-favorite-report-type-value="real-time"
                    data-action="set-favorite-report#setFavoriteReport"
                    class="iawp-button favorite <?php echo $env->is_favorite('real-time') ? 'active' : ''; ?>"
            >
                <span class="dashicons dashicons-star-filled"></span>
                <?php esc_html_e('Make default', 'independent-analytics'); ?>
            </button>
        </div>
    </div>
</div>


<div id="real-time-dashboard" class="real-time-dashboard refreshed"
     data-controller="real-time"
     data-real-time-chart-data-value="<?php echo esc_attr(json_encode($chart_data)) ?>"
     data-real-time-nonce-value="<?php echo wp_create_nonce('iawp_real_time') ?>"
>
    <div class="iawp-heading">
        <div class="iawp-title">
            <div class="iawp-title-inner">
                <svg width="32px" height="32px" viewBox="0 0 32 32" version="1.1"
                     xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g>
                            <circle class="recording-icon-outer" fill="#F2998F" cx="16" cy="16"
                                    r="16"></circle>
                            <circle class="recording-icon-inner" fill="#D93B29" cx="16" cy="16"
                                    r="9.70491803"></circle>
                        </g>
                    </g>
                </svg>
                <span data-real-time-target="visitorMessage" data-testid="real-time-title"><?php echo esc_html($visitor_message) ?></span>
                <a class="learn-more" href="#" data-controller="tooltip" data-tooltip-text-value="Active Visitors is the number of people who have viewed a page within the last 5 minutes.">
                    <span class="dashicons dashicons-info-outline"></span>
                </a>
            </div>
        </div>
        <div class="iawp-overview"><span
                    data-real-time-target="pageMessage" data-testid="page-count"><?php echo esc_html($page_message) ?></span>
            &#8226; <span
                    data-real-time-target="referrerMessage" data-testid="referrer-count"><?php echo esc_html($referrer_message); ?></span>
            &#8226; <span
                    data-real-time-target="countryMessage" data-testid="country-count"><?php echo esc_html($country_message) ?></span>
        </div>
    </div>

    <div id="charts" class="charts">
        <div class="chart-container">
            <div class="chart-inner">
                <div class="legend-container">
                    <h2 class="legend-title"><?php esc_html_e('The Last 5 Minutes', 'independent-analytics') ?></h2>
                    <div class="legend"></div>
                </div>
                <canvas data-real-time-target="secondChart"
                        width="400"
                        height="200"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <div class="chart-inner">
                <div class="legend-container">
                    <h2 class="legend-title"><?php esc_html_e('The Last 30 Minutes', 'independent-analytics') ?></h2>
                    <div class="legend"></div>
                </div>
                <canvas data-real-time-target="minuteChart"
                        width="400"
                        height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="most-popular-container">
        <?php foreach ($lists as $list_id => $list) : ?>
            <div class="most-popular-list" data-testid="<?php echo esc_attr(sanitize_title($list['title'])); ?>">
                <div class="heading">
                    <div class="title-med"><?php echo esc_html($list['title']) ?></div>
                    <div class="views-heading"><?php esc_html_e('Views', 'independent-analytics'); ?></div>
                </div>
                <ol data-real-time-target="<?php echo esc_attr($list_id) ?>List">
                    <?php foreach ($list['entries'] as $index => $item): ?>
                        <li data-id="<?php echo esc_attr($item['id']) ?>"
                            data-position="<?php echo esc_attr($index + 1); ?>"
                        >
                            <span class="real-time-position"><?php echo absint($index + 1) ?>.</span>
                            <?php if (!empty($item['flag'])): ?>
                                <?php echo $item['flag'] ?>
                            <?php endif; ?>
                            <span class="real-time-resource">
                                <?php echo esc_html($item['title']) ?>

                                <?php if (!empty($item['subtitle'])): ?>
                                    <span class="real-time-subtitle"><?php echo esc_html($item['subtitle']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="real-time-stat"><?php echo absint($item['views']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="most-popular-empty-message <?php echo count($list['entries']) > 0 ? esc_attr('hide') : '' ?>"><?php esc_html_e('No results in the last 5 minutes.', 'independent-analytics') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
