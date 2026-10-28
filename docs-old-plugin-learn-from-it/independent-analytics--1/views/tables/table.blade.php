@php /** @var \IAWP\Tables\Table $table */ @endphp

<div id="iawp-table-wrapper" class="iawp-table-wrapper" data-controller="table-columns">
    <div id="data-table-container" class="data-table-container">
        <div id="data-table"
             class="data-table"
             data-table-name="<?php echo esc_attr($table->id()); ?>"
             data-columns="<?php echo \IAWP\Utils\Security::json_encode($all_columns); ?>"
             data-column-count="<?php echo count($all_columns); ?>"
             data-total-number-of-rows=""
             style="min-width: <?php echo absint($visible_column_count) * 170; ?>px; --columns: <?php echo absint($visible_column_count) ; ?>; --columns-mobile: <?php echo absint($visible_column_count - 1); ?>"
        >

        <!-- Header -->
        <div id="iawp-columns" class="iawp-columns">
            <div class="iawp-row" data-controller="sort"><?php 
                foreach ($all_columns as $column) :
                    $cell_class = $column->is_visible() ? 'cell' : 'cell hide'; ?>
                    <div class="<?php echo esc_attr($cell_class); ?>"
                         data-column="<?php echo esc_attr($column->id()); ?>"
                         data-test-visibility="<?php echo $column->is_visible() ? 'visible' : 'hidden'; ?>"
                    >
                        <button class="sort-button"
                                data-sort-target="sortButton"
                                data-sort-direction="<?php echo $column->id() === esc_attr($sort_column) ? esc_attr($sort_direction) : ''; ?>"
                                data-default-sort-direction="<?php echo esc_attr($column->sort_direction()); ?>"
                                data-sort-column="<?php echo esc_attr($column->id()); ?>"
                                data-action="sort#sortColumnColumn"
                                title="<?php echo esc_html($column->name()); ?>"
                        >
                            <div class="row-number"></div>
                            <span class="name"><?php echo esc_html($column->name()); ?></span>
                            <span class="dashicons dashicons-arrow-right"></span>
                            <span class="dashicons dashicons-arrow-up"></span>
                            <span class="dashicons dashicons-arrow-down"></span>
                            <div class="animator"></div>
                        </button>
                    </div><?php
                endforeach; ?>
            </div>
        </div>

        <!-- Skeleton -->
        <?php
        if ($render_skeleton) : ?>
            <div id="iawp-rows" class="iawp-rows rendering"><?php
                foreach (range(1, $page_size) as $index) : ?>
                    <div class="iawp-row"><?php
                        foreach ($all_columns as $column) :
                            $class = $column->is_visible() ? 'cell' : 'cell hide'; ?>
                            <div class="<?php echo esc_attr($class); ?>"
                                 data-column="<?php echo esc_attr($column->id()); ?>"
                                 data-test-visibility="<?php echo $column->is_visible() ? 'visible' : 'hidden'; ?>"
                            >
                                <div class="row-number"></div>
                                <span class="cell-content">
                                    <span class="skeleton-loader"></span>
                                </span>
                                <span class="animator"></span>
                            </div><?php
                        endforeach; ?>
                    </div><?php 
                endforeach; ?>
            </div><?php
        endif;

        if (!$render_skeleton) {
            echo iawp_blade()->run('tables.rows', [
                'table'                => $table,
                'all_columns'          => $all_columns,
                'visible_column_count' => $visible_column_count,
                'number_of_shown_rows' => $number_of_shown_rows,
                'rows'                 => $rows,
                'render_skeleton'      => $render_skeleton,
                'page_size'            => $page_size,
                'sort_column'          => $sort_column,
                'sort_direction'       => $sort_direction,
                'has_campaigns'        => $has_campaigns,
            ]);
        } ?>
    </div>
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
            <?php esc_html(printf(__('Load Next %d Rows', 'independent-analytics'), $page_size)); ?>
        </span>
    </button>
</div>
</div>
