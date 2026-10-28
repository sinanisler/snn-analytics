<div>
    <h1>Independent Analytics Debugging</h1>
    <section class="settings-container">
        <h2>IP Address Debugging</h2>
        <div class="ip-addresses">
            <p <?php echo $detected_ip ? "" : 'class=empty'; ?>><span>Detected IP:</span> <span><?php echo esc_html($detected_ip); ?></span></p>
            <p <?php echo $custom_ip_header ? "" : 'class=empty'; ?>><span>Custom IP header:</span> <span><?php echo esc_html($custom_ip_header); ?></span></p>
                <?php foreach($header_details as $detail) : ?>
                    <p <?php echo $detail[1] ? "" : 'class=empty'; ?>><span><?php echo esc_html($detail[0]); ?>:</span> <span><?php echo esc_html($detail[1]); ?></span></p>
                <?php endforeach; ?>
        </div>
    </section>
</div>
