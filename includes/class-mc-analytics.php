<?php
/**
 * Mc_Analytics — lightweight, WordPress-local capture counters.
 *
 * Stores per-source / per-day capture counts in the mailercloud_capture_stats
 * option so the Analytics admin page can show lead-capture growth without any
 * extra MailerCloud API call. No PII is stored — counts only.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Analytics
{
    const OPTION = 'mailercloud_capture_stats';

    /**
     * Record one capture attempt for a source (connector slug).
     *
     * @param string $source  Connector slug, e.g. 'cf7'.
     * @param bool   $success Whether MailerCloud accepted the contact.
     */
    public static function record($source, $success)
    {
        $stats = get_option(self::OPTION, array());
        if (! is_array($stats)) {
            $stats = array();
        }

        $day = gmdate('Y-m-d');
        if (! isset($stats[$source])) {
            $stats[$source] = array('ok' => 0, 'fail' => 0, 'days' => array());
        }
        if ($success) {
            $stats[$source]['ok']++;
        } else {
            $stats[$source]['fail']++;
        }
        if (! isset($stats[$source]['days'][$day])) {
            $stats[$source]['days'][$day] = 0;
        }
        if ($success) {
            $stats[$source]['days'][$day]++;
        }

        // Keep the per-day history bounded (last 60 days) to avoid option bloat.
        if (count($stats[$source]['days']) > 60) {
            ksort($stats[$source]['days']);
            $stats[$source]['days'] = array_slice($stats[$source]['days'], -60, null, true);
        }

        update_option(self::OPTION, $stats, false);
    }

    /**
     * @return array Raw stats keyed by source slug.
     */
    public static function all()
    {
        $stats = get_option(self::OPTION, array());
        return is_array($stats) ? $stats : array();
    }

    /**
     * @return int Total successful captures across all sources.
     */
    public static function total_captured()
    {
        $total = 0;
        foreach (self::all() as $row) {
            $total += isset($row['ok']) ? intval($row['ok']) : 0;
        }
        return $total;
    }
}
