<?php
/**
 * Ninja Forms connector (free). Hook: ninja_forms_after_submission($form_data).
 * $form_data['fields'] is a list of field arrays with key + value.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_Ninja extends Mc_Connector_Base
{
    public function slug()
    {
        return 'ninja';
    }

    public function label()
    {
        return 'Ninja Forms';
    }

    public function is_active()
    {
        return class_exists('Ninja_Forms') || function_exists('Ninja_Forms');
    }

    public function register_hooks()
    {
        add_action('ninja_forms_after_submission', array($this, 'on_submit'));
    }

    public function on_submit()
    {
        $this->capture($this->flatten_submission(func_get_args()));
    }

    public function flatten_submission($args)
    {
        $form_data = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        $fields    = isset($form_data['fields']) && is_array($form_data['fields']) ? $form_data['fields'] : array();
        $out       = array();
        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['value'])) {
                continue;
            }
            $key = isset($field['key']) ? $field['key'] : (isset($field['id']) ? (string) $field['id'] : null);
            if ($key !== null) {
                $out[$key] = $field['value'];
            }
        }
        return $out;
    }
}
