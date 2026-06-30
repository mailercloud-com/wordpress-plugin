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
        $this->handle(func_get_args());
    }

    public function get_forms()
    {
        $out = array();
        if (! class_exists('Ninja_Forms')) {
            return $out;
        }
        foreach (Ninja_Forms()->form()->get_forms() as $f) {
            $out[] = array('id' => (string) $f->get_id(), 'title' => $f->get_setting('title'));
        }
        return $out;
    }

    public function get_form_fields($form_id)
    {
        $out = array();
        if (! class_exists('Ninja_Forms')) {
            return $out;
        }
        foreach (Ninja_Forms()->form((int) $form_id)->get_fields() as $f) {
            $type = $f->get_setting('type');
            if (in_array($type, array('submit', 'hr', 'html', 'recaptcha', 'spam'), true)) {
                continue;
            }
            $key   = $f->get_setting('key');
            $label = $f->get_setting('label');
            $out[] = array('key' => $key !== '' ? $key : (string) $f->get_id(), 'label' => $label !== '' ? $label : $key);
        }
        return $out;
    }

    public function get_form_id($args)
    {
        $d = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        if (isset($d['form_id'])) {
            return (string) $d['form_id'];
        }
        return isset($d['id']) ? (string) $d['id'] : '';
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
            $value = $field['value'];
            // Expose the value under the field's key, id AND label so admins can map by
            // whatever they see — Ninja auto-generates keys like "textbox_1782..." that
            // don't match the visible label. (If two fields share a label, last wins;
            // use the key for those.)
            if (isset($field['key']) && $field['key'] !== '') {
                $out[$field['key']] = $value;
            }
            if (isset($field['id']) && $field['id'] !== '') {
                $out[(string) $field['id']] = $value;
            }
            if (isset($field['label']) && $field['label'] !== '') {
                $out[$field['label']] = $value;
            }
        }
        return $out;
    }
}
