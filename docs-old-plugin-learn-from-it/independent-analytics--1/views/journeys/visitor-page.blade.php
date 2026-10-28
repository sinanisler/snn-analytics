@php
/** @var string $title */
/** @var string $rows */
/** @var ?int $session_id */
@endphp

<div id="report-header-container" class="report-header-container">
    <div id="report-title-bar" class="report-title-bar overview-report">
        <div class="primary-report-title-container">
            <h1 class="report-title"><?php echo esc_html($title); ?></h1>
        </div>
    </div>
</div>

<div class="journeys journeys-for-single-visitor" data-session-to-highlight="<?php echo is_int($session_id) ? $session_id : '' ?>">
    <?php echo $rows; // This output is escaped in journeys.rows.blade.php ?>
</div>
