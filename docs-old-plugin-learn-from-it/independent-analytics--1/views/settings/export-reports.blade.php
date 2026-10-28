@php /** @var \IAWP\Report_Finder $report_finder */ @endphp

<div class="settings-container export-reports" data-controller="export-reports">
    <div class="heading">
        <h2><?php
            esc_html_e('Export Report Settings', 'independent-analytics'); ?></h2>
        <a class="tutorial-link"
           href="https://independentwp.com/knowledgebase/dashboard/export-import-custom-reports/"
           target="_blank">
            <?php
            esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <p class="setting-description"><?php
        esc_html_e('Export any of your report settings, so they can be duplicated on another website running Independent Analytics.', 'independent-analytics'); ?></p>
    <label>
        <input type="checkbox" data-export-reports-target="selectAllCheckbox"
            data-action="export-reports#handleToggleSelectAll">
        <?php
        esc_html_e('Select all reports', 'independent-analytics'); ?>
    </label>
    <div class="reports"><?php
        foreach ($report_finder->get_reports_grouped_by_type() as $report_type) : ?>
            <div>
                <h3><?php echo esc_html($report_type['base_report']->name()); ?></h3>
                <ol><?php
                    if (count($report_type['saved_reports']) == 0) : ?>
                        <li class="empty">
                            <p><?php esc_html_e('No reports found.', 'independent-analytics'); ?></p>
                        </li><?php
                    else : 
                        foreach ($report_type['saved_reports'] as $report) : ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="report_id" value="<?php echo esc_attr($report->id()); ?>"
                                        data-action="export-reports#handleToggleReport">
                                    <?php echo esc_html($report->name()); ?>
                                </label>
                            </li><?php 
                        endforeach;
                    endif; ?>
                </ol>
            </div><?php
        endforeach; ?>
    </div>
    <button class="iawp-button purple" data-export-reports-target="submitButton"
        data-action="export-reports#export" disabled><?php
            esc_html_e('Export Reports', 'independent-analytics'); ?>
    </button>
</div>

<div class="settings-container import-reports" data-controller="import-reports"
     data-import-reports-database-version-value="<?php echo '52'; ?>">
    <div class="heading">
        <h2><?php
            esc_html_e('Import Custom Reports', 'independent-analytics'); ?>
        </h2>
        <a class="tutorial-link"
           href="https://independentwp.com/knowledgebase/dashboard/export-import-custom-reports/"
           target="_blank">
            <?php
            esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <button class="iawp-button purple" data-import-reports-target="submitButton"
        data-action="import-reports#import" disabled><?php
            esc_html_e('Import Reports', 'independent-analytics'); ?>
        </button>
    <input type="file" accept="application/json"
           data-action="import-reports#handleFileSelected click->import-reports#clearFileInput"
           data-import-reports-target="fileInput">
    <p data-import-reports-target="warningMessage" style="display:none;"></p>
</div>