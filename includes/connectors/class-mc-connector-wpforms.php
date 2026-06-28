<?php
/**
 * WPForms connector (works with the free WPForms Lite — the hook ships there).
 * Hook: wpforms_process_complete($fields, $entry, $form_data, $entry_id).
 * $fields is keyed by field id; each has name + value.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_WPForms extends Mc_Connector_Base
{
    public function slug()
    {
        return 'wpforms';
    }

    public function label()
    {
        return 'WPForms';
    }

    public function is_active()
    {
        return function_exists('wpforms') || defined('WPFORMS_VERSION');
    }

    public function register_hooks()
    {
        add_action('wpforms_process_complete', array($this, 'on_submit'), 10, 4);
    }

    public function on_submit()
    {
        $this->capture($this->flatten_submission(func_get_args()));
    }

    public function flatten_submission($args)
    {
        $fields = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        $out    = array();
        foreach ($fields as $id => $field) {
            if (is_array($field) && isset($field['value'])) {
                $out[(string) $id] = $field['value'];
            }
        }
        return $out;
    }
}
