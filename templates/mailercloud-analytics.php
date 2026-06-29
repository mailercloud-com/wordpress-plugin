<?php
/**
 * Analytics admin page — lead-capture growth from this plugin.
 *
 * In-scope variables (set by create_mailercloud_analytics_page):
 *   $stats          array slug => { ok, fail, days: {Y-m-d => n} }
 *   $total_captured int
 *   $labels         array slug => label
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sum captures within the last N days for a stats row.
 */
function mc_analytics_last_days($row, $days)
{
    if (empty($row['days']) || ! is_array($row['days'])) {
        return 0;
    }
    $cutoff = strtotime('-' . intval($days) . ' days');
    $sum = 0;
    foreach ($row['days'] as $d => $n) {
        if (strtotime($d) >= $cutoff) {
            $sum += intval($n);
        }
    }
    return $sum;
}
?>
<div class="wrap mailercloud-analytics">
    <h1><?php esc_html_e('Mailercloud Analytics', 'mailercloud'); ?></h1>
    <p class="description"><?php esc_html_e('Leads captured by this plugin and pushed to Mailercloud. Campaign analytics live in your Mailercloud dashboard.', 'mailercloud'); ?></p>

    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:28px 32px;margin:20px 0;max-width:820px;box-sizing:border-box;">
        <h2 style="margin:0 0 8px;font-size:34px;line-height:1.2;font-weight:600;"><?php echo esc_html(number_format_i18n($total_captured)); ?></h2>
        <p style="margin:0;color:#555;font-size:14px;"><?php esc_html_e('Total contacts captured by this plugin', 'mailercloud'); ?></p>
    </div>

    <?php if (empty($stats)) : ?>
        <p><?php esc_html_e('No captures yet. Connect a form on the Integrations page to start collecting leads.', 'mailercloud'); ?></p>
    <?php else : ?>
        <table class="widefat striped" style="max-width:820px;">
            <thead><tr>
                <th><?php esc_html_e('Source', 'mailercloud'); ?></th>
                <th><?php esc_html_e('Captured', 'mailercloud'); ?></th>
                <th><?php esc_html_e('Last 7 days', 'mailercloud'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($stats as $slug => $row) : ?>
                <tr>
                    <td><?php echo esc_html(isset($labels[$slug]) ? $labels[$slug] : $slug); ?></td>
                    <td><?php echo esc_html(number_format_i18n(isset($row['ok']) ? intval($row['ok']) : 0)); ?></td>
                    <td><?php echo esc_html(number_format_i18n(mc_analytics_last_days($row, 7))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
