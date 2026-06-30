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
     * Raw stored config. New shape: { feeds: [ {id,enabled,form_id,list_id,mapping[]}, ... ] }.
     *
     * @return array
     */
    public function get_config()
    {
        $config = get_option($this->option_key(), array());
        return is_array($config) ? $config : array();
    }

    /**
     * Return this connector's form-mappings ("feeds"). Migrates the legacy single-config
     * shape ({enabled,list_id,form_id,mapping}) into a one-element feeds array.
     *
     * @return array<int,array{id:string,enabled:bool,form_id:string,list_id:string,mapping:array}>
     */
    public function get_feeds()
    {
        $config = $this->get_config();
        if (isset($config['feeds']) && is_array($config['feeds'])) {
            return array_values($config['feeds']);
        }
        // Legacy single-config -> one feed (backward compatible).
        if (isset($config['mapping']) || isset($config['list_id']) || isset($config['enabled'])) {
            return array(array(
                'id'      => '1',
                'enabled' => ! empty($config['enabled']),
                'form_id' => isset($config['form_id']) ? $config['form_id'] : '',
                'list_id' => isset($config['list_id']) ? $config['list_id'] : '',
                'mapping' => isset($config['mapping']) && is_array($config['mapping']) ? $config['mapping'] : array(),
            ));
        }
        return array();
    }

    /**
     * List the host plugin's forms for the field-mapping UI.
     *
     * @return array<int,array{id:string,title:string}>
     */
    public function get_forms()
    {
        return array();
    }

    /**
     * List a form's fields (for the mapping dropdown). Override per connector.
     *
     * @param string $form_id
     * @return array<int,array{key:string,label:string}>
     */
    public function get_form_fields($form_id)
    {
        return array();
    }

    /**
     * Extract the submitted form's id from the host plugin's hook arguments.
     * Override per connector. Empty string when the connector can't determine it.
     *
     * @param array $args
     * @return string
     */
    public function get_form_id($args)
    {
        return '';
    }

    /**
     * Hook entry point: pick the feed for the submitted form, then map + capture.
     * Connectors call this from their submit hook with func_get_args().
     *
     * Feed selection: match the submitted form id to a feed's form_id. When the form
     * id can't be determined (e.g. Elementor) and exactly one feed is enabled, use it.
     *
     * @param array $args Hook arguments (connector-specific).
     */
    public function handle($args)
    {
        $feeds = $this->get_feeds();
        if (empty($feeds)) {
            return;
        }
        $fid  = (string) $this->get_form_id($args);
        $feed = null;

        if ($fid !== '') {
            foreach ($feeds as $f) {
                if (! empty($f['enabled']) && (string) (isset($f['form_id']) ? $f['form_id'] : '') === $fid) {
                    $feed = $f;
                    break;
                }
            }
        } else {
            // Form id unknown: only safe if a single feed is enabled.
            $enabled = array();
            foreach ($feeds as $f) {
                if (! empty($f['enabled'])) {
                    $enabled[] = $f;
                }
            }
            if (count($enabled) === 1) {
                $feed = $enabled[0];
            }
        }

        if (! $feed) {
            return;
        }
        $this->capture_feed($feed, $this->flatten_submission($args));
    }

    /**
     * Map + upsert one feed's flattened submission to MailerCloud. Safe no-op unless the
     * feed is enabled, has a list, and an API key is present.
     *
     * @param array $feed   { enabled, list_id, mapping[] }
     * @param array $source Flat [field-key => value] submission map.
     */
    public function capture_feed(array $feed, $source)
    {
        if (empty($feed['enabled']) || empty($feed['list_id'])) {
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

        $mapping = isset($feed['mapping']) && is_array($feed['mapping']) ? $feed['mapping'] : array();
        $contact = Mc_Contact_Sync::map_fields($mapping, $clean);

        // An email is required — and must be a valid address — to identify the contact.
        $contact['email'] = isset($contact['email']) ? sanitize_email($contact['email']) : '';
        if (empty($contact['email']) || ! is_email($contact['email'])) {
            if (class_exists('Mc_Analytics')) {
                Mc_Analytics::record($this->slug(), false);
            }
            return;
        }

        $result = Mc_Contact_Sync::send($contact, $feed['list_id'], $api_key, 'upsert');

        if (class_exists('Mc_Analytics')) {
            Mc_Analytics::record($this->slug(), ! empty($result['ok']));
        }
    }

    /**
     * Back-compat: capture a flattened source using the first enabled feed.
     * (Connectors now route through handle(); kept for any direct callers/tests.)
     *
     * @param array $source
     */
    public function capture($source)
    {
        foreach ($this->get_feeds() as $f) {
            if (! empty($f['enabled'])) {
                $this->capture_feed($f, $source);
                return;
            }
        }
    }
}
