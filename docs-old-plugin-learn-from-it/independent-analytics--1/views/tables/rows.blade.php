@php /** @var \IAWP\Tables\Table $table */ @endphp

<div id="iawp-rows" class="iawp-rows" data-number-of-shown-rows="<?php echo esc_attr($number_of_shown_rows) ?>">
    <!-- Empty table message --><?php
    if($number_of_shown_rows === 0) :
        if ($table->id() === 'views') : ?>
            <p id="data-error" class="data-error">
                <?php esc_html_e('No views found', 'independent-analytics'); ?>
            </p><?php
        elseif ($table->id() === 'referrers') : ?>
            <p id="data-error" class="data-error">
                <?php esc_html_e('No referrers found', 'independent-analytics'); ?>
            </p><?php
        elseif ($table->id() === 'geo') : ?>
            <p id="data-error" class="data-error">
                <?php esc_html_e('No geographic data found', 'independent-analytics'); ?>
            </p><?php
        elseif ($table->id() === 'devices') : ?>
            <p id="data-error" class="data-error">
                <?php esc_html_e('No device data found', 'independent-analytics'); ?>
            </p><?php
        elseif ($table->id() === 'campaigns') : ?>
            <div class="data-error">
                <p>
                    <?php esc_html_e('No campaign data found', 'independent-analytics'); ?>
                </p><?php
                if (!$has_campaigns) : ?>
                    <p>
                        <a href="?page=independent-analytics-campaign-builder" class="iawp-button purple">
                            <?php esc_html_e('Create your first campaign', 'independent-analytics'); ?>
                        </a>
                    </p><?php
                endif; ?>
            </div><?php
        elseif ($table->id() === 'clicks') : ?>
            <div class="data-error">
                <p>
                    <?php esc_html_e('No click data found', 'independent-analytics'); ?>
                </p><?php
                if (!$has_campaigns) : ?>
                    <p>
                        <a href="?page=independent-analytics-click-tracking" class="iawp-button purple">
                            <?php esc_html_e('Edit your tracked links', 'independent-analytics'); ?>
                        </a>
                    </p><?php
                endif; ?>
            </div><?php
        endif;
    endif;

    if ($number_of_shown_rows > 0) :
        foreach ($rows as $index => $row) :
            $class = $table->id() === 'views' && $row->is_deleted() ? 'iawp-row deleted' : 'iawp-row'; ?>
            <div class="<?php echo esc_attr($class); ?>" <?php echo $table->get_row_data_attributes($row); ?>><?php
                foreach ($all_columns as $column) :
                    $class = $column->is_visible() ? 'cell' : 'cell hide'; ?>
                    <div class="<?php echo esc_attr($class); ?>"
                         data-column="<?php echo esc_attr($column->id()); ?>"
                         data-test-visibility="<?php echo $column->is_visible() ? 'visible' : 'hidden'; ?>"
                    >
                        <div class="row-number">
                            <span><?php echo $index + 1; ?></span>
                            <?php if($is_pro): ?>
                                <button class="open-examiner-button"
                                        data-action="report#showExaminer"
                                        data-url="<?php echo esc_url($row->examiner_url()); ?>"
                                        data-title="<?php echo esc_html($row->examiner_title()); ?>"
                                >
                                <span class="dashicons dashicons-search"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <span class="cell-content"><?php echo wp_kses_post($table->get_cell_content($row, $column)); ?></span>
                        <span class="animator"></span>
                    </div><?php
                endforeach; ?>
            </div><?php 
        endforeach;
    endif; ?>
</div>