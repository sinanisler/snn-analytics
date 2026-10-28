@php /** @var \IAWP\Env $env */ @endphp
@php /** @var string $report_name */ @endphp
@php /** @var string $report_type */ @endphp
@php /** @var bool $can_edit_settings */ @endphp
@php /** @var bool $supports_saved_reports */ @endphp
@php /** @var bool $external */ @endphp
@php /** @var bool $upgrade */ @endphp
@php /** @var \IAWP\Report[] $reports */ @endphp

<div class="menu-section <?php echo esc_attr($report_type); ?> <?php echo $env->is_currently_viewed($report_type) ? 'current' : ''; ?> <?php echo $reports == null ? 'no-sub-items' : ''; ?> <?php echo $upgrade ? 'upgrade' : ''; ?> <?php echo $external ? 'external' : ''; ?>">
    <span class="collapsed-icon" data-testid="collapsed-icon-<?php echo esc_attr($report_type); ?>"><?php
        echo iawp_blade()->run('icons.' . $report_type); ?>
    </span>
    <div class="report-inner">
        <h3 class="report-name <?php echo (!$upgrade && $env->is_favorite($report_type)) ? 'favorite' : '' ; ?>"
            data-report-type="<?php echo esc_attr($report_type); ?>">
            <span class="icon-container">
                <span class="report-icon"><?php
                    echo iawp_blade()->run('icons.' . $report_type); ?>
                </span>
            </span>
            <a href="<?php echo esc_url($url) ?>"
               data-testid="menu-link-<?php echo esc_attr($report_type); ?>"
            >
                <?php echo esc_html($report_name); ?>
            </a><?php
            if ($upgrade) : ?>
                <span class="pro-label">Pro</span><?php
            endif;
            if ($supports_saved_reports && $can_edit_settings) : ?>
                <button class="add-new-report" data-controller="create-report"
                        data-action="create-report#create"
                        data-create-report-type-value="<?php echo esc_attr($report_type); ?>"
                        data-testid="add-new-report-<?php echo esc_attr($report_type); ?>"><span
                            class="dashicons dashicons-plus-alt2"></span></button><?php
            endif; ?>
        </h3><?php
        if ($reports != null) : ?>
            <ol data-controller="<?php echo $can_edit_settings ? "sortable-reports" : "" ; ?>"
                data-sortable-reports-type-value="<?php echo esc_attr($report_type); ?>"><?php
                foreach ($reports as $report) : ?>
                    <li data-report-id="<?php echo esc_attr($report->id()); ?>"
                        class="<?php echo $report->is_current() ? 'current' : ''; ?> <?php echo $report->is_favorite() ? 'favorite' : '' ; ?>">
                        <a href="<?php echo esc_url($report->url()); ?>"
                           data-name-for-report-id="<?php echo esc_attr($report->id()); ?>"
                           data-testid="menu-link-<?php echo esc_attr(sanitize_title($report->name())); ?>"><?php echo esc_html($report->name()); ?></a>
                    </li><?php
                endforeach; ?>
            </ol><?php
        endif; ?>
    </div>
    <a class="overlay-link" href="<?php echo esc_url($url); ?>" <?php echo $external ? 'target="_blank"' : '' ?>></a><?php 
    if ($collapsed_label) : ?>
        <span class="collapsed-label">
            <a href="<?php echo esc_url($url); ?>" <?php echo $external ? 'target="_blank"' : ''; ?>><?php
                echo esc_html($collapsed_label) . ' ' . ($external ? '<span class="dashicons dashicons-external"></span>' : ''); ?>
            </a>
        </span><?php
    endif; ?>
</div>