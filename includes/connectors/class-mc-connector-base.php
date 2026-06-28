<?php
/**
 * Mc_Connector_Base — abstract base for form-plugin connectors.
 *
 * A connector hooks a third-party form plugin's OWN server-side submit action
 * (after that plugin has validated + spam-checked the submission), flattens the
 * submitted data to a key => value array, maps it via the per-connector mapping
 * config, and upserts the contact to MailerCloud. No new public endpoint is
 * created — we only react to the host form plugin's hook.
 *
 * Each concrete connector implements: slug(), label(), is_active(),
 * register_hooks(), and flatten_submission().
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

abstract class Mc_Connector_Base
{
    /** @return string Stable connector slug, e.g. 'cf7'. */
    abstract public function slug();

    /** @return string Human label, e.g. 'Contact Form 7'. */
    abstract public function label();

    /** @return bool Whether the host form plugin is active. */
    abstract public function is_active();

    /** Register the host plugin's submit hook -> $this->capture(...). */
    abstract public function register_hooks();

    /**
     * Flatten a host-plugin submit payload into a flat [field-key => value] map.
     *
     * @param array $args Hook arguments (connector-specific).
     * @return array
     */
    abstract public function flatten_submission($args);

    /** @return string Option key holding this connector's mapping config. */
    public function option_key()
    {
        return 'mailercloud_connector_map_' . $this->slug();
    }

    /**
     * @return array { enabled: bool, list_id: string, mapping: array<rows> }
     */
    public function get_config()
    {
        $config = get_option($this->option_key(), array());
        if (! is_array($config)) {
            $config = array();
        }
        return wp_parse_args(
            $config,
            array('enabled' => false, 'list_id' => '', 'mapping' => array())
        );
    }

    /**
     * Map + upsert a flattened submission to MailerCloud. Safe no-op unless the
     * connector is enabled, configured with a list, and an API key is present.
     *
     * @param array $source Flat [field-key => value] submission map.
     */
    public function capture($source)
    {
        $config = $this->get_config();
        if (empty($config['enabled']) || empty($config['list_id'])) {
            return;
        }
        $api_key = get_option('mailercloud_api_key');
        if (empty($api_key) || empty($source) || ! is_array($source)) {
            return;
        }

        // Sanitize raw submitted values before they are mapped and sent to the API.
        $clean = array();
        foreach ($source as $k => $v) {
            if (is_scalar($v)) {
                $clean[$k] = sanitize_text_field((string) $v);
            }
        }

        $contact = Mc_Contact_Sync::map_fields($config['mapping'], $clean);

        // An email is required — and must be a valid address — to identify the contact.
        $contact['email'] = isset($contact['email']) ? sanitize_email($contact['email']) : '';
        if (empty($contact['email']) || ! is_email($contact['email'])) {
            if (class_exists('Mc_Analytics')) {
                Mc_Analytics::record($this->slug(), false);
            }
            return;
        }

        $result = Mc_Contact_Sync::send($contact, $config['list_id'], $api_key, 'upsert');

        if (class_exists('Mc_Analytics')) {
            Mc_Analytics::record($this->slug(), ! empty($result['ok']));
        }
    }
}
