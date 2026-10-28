@php /** @var array $tables */ @endphp
@php /** @var string $active */ @endphp

<ol class="examiner-table-tabs"><?php
    foreach ($tables as $table) : ?>
        <li>
            <button data-table-type="<?php echo esc_attr($table['table_type']); ?>"
                    data-action="report#changeTable"
                    disabled="disabled"
                    class="examiner-table-tab <?php echo $table['table_type'] === $active ? 'active' : ''; ?>"
            ><?php echo iawp_blade()->run('icons.' . $table['table_type']); ?> <?php echo esc_html($table['name']); ?></button>
        </li><?php
    endforeach; ?>
</ol>
