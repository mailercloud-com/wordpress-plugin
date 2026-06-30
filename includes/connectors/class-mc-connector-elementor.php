<?php
/**
 * Elementor Forms connector (the Forms widget is Elementor Pro only). Hook:
 * elementor_pro/forms/new_record($record, $handler). $record->get('fields') is
 * keyed by field id, each with a 'value'.
 *
 * Code-verified against Elementor Pro's documented hook; live-test when a Pro
 * license is available.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_Elementor extends Mc_Connector_Base
{
    public function slug()
    {
        return 'elementor';
    }

    public function label()
    {
        return 'Elementor Forms';
    }

    public function is_active()
    {
        return defined('ELEMENTOR_PRO_VERSION');
    }

    public function register_hooks()
    {
        add_action('elementor_pro/forms/new_record', array($this, 'on_submit'), 10, 2);
    }

    public function on_submit()
    {
        // Elementor has no central form registry, so get_forms() is empty and the UI
        // keeps free-text/label mapping; handle() therefore won't form-gate (no form_id).
        $this->handle(func_get_args());
    }

    public function flatten_submission($args)
    {
        $record = isset($args[0]) ? $args[0] : null;
        if (! is_object($record) || ! method_exists($record, 'get')) {
            return array();
        }
        $fields = $record->get('fields');
        if (! is_array($fields)) {
            return array();
        }
        $out = array();
        foreach ($fields as $id => $field) {
            if (! is_array($field) || ! isset($field['value']) || $field['value'] === '') {
                continue;
            }
            // Map by Elementor field id (custom_id) AND by the field's label (title), so
            // admins can use whichever they see. (Duplicate labels: last wins; use the id.)
            $out[(string) $id] = $field['value'];
            if (isset($field['title']) && $field['title'] !== '') {
                $out[$field['title']] = $field['value'];
            }
        }
        return $out;
    }
}
