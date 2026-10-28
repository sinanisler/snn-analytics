@php /** @var \IAWP\Tables\Table $table */ @endphp

<div id="iawp-table-wrapper">
    <div id="data-table-container">
        <div id="data-table" data-table-name="<?php echo esc_attr($table->id()); ?>">
            <!-- Skeleton -->
            <?php if ($render_skeleton) : ?>
                <div id="iawp-rows">
                    <?php echo iawp_blade()->run('journeys.table-heading'); ?>
                    <?php for ($journey = 0; $journey < 50; $journey++) : ?>
                        <div class="journey">
                            <div class="journey-preview">
                                <?php for ($cell = 0; $cell < 8; $cell++) : ?>
                                    <p class="journey-cell skeleton">
                                        <span class="skeleton-loader"></span>
                                        <span class="animator"></span>
                                    </p>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <!-- Card -->
            <?php if (!$render_skeleton) : ?>
                <?php echo iawp_blade()->run('tables.rows', [
                    'table' => $table,
                    'rows'  => $rows,
                ]); ?>
            <?php endif; ?>
        </div>

        <div class="pagination">
            <button id="pagination-button"
                    class="iawp-button purple"
                    data-report-target="loadMore"
                    data-action="report#loadMore"
            >
                <span class="disabled-button-text">
                    <?php esc_html_e('Showing All Rows', 'independent-analytics'); ?>
                </span>
                <span class="enabled-button-text">
                    <?php echo esc_html(sprintf(__('Load Next %d Rows', 'independent-analytics'), $page_size)); ?>
                </span>
            </button>
        </div>
    </div>
</div>
