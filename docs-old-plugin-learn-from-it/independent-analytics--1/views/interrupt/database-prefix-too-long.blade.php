@php /** @var string $prefix */ @endphp
@php /** @var int $length */ @endphp

<div class="settings-container interrupt-message"
     data-controller="migration-redirect"
>
    <h2><?php esc_html_e("Your database prefix is longer than 25 characters", "independent-analytics"); ?></h2>
    <p>
        <?php esc_html_e("Your current database prefix", "independent-analytics"); ?> <strong>"<?php echo esc_html($prefix); ?>"</strong> <?php esc_html_e("is longer than the allowed 25 characters.", "independent-analytics"); ?>
    </p>
    <p>
        <?php esc_html_e("Please click the button below to follow a brief tutorial that will show you how to easily change your database prefix.", "independent-analytics"); ?>
    </p>
    <p>
        <a href="https://independentwp.com/knowledgebase/common-questions/database-prefix-length-error/"
            class="iawp-button purple"
            target="_blank">
            <span class="dashicons dashicons-sos"></span>
            <span><?php esc_html_e('Follow Tutorial', 'independent-analytics'); ?></span>
        </a>
    </p>
</div>