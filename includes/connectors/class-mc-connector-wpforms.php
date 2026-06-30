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
        $this->handle(func_get_args());
    }

    public function get_forms()
    {
        $out = array();
        if (! function_exists('wpforms')) {
            return $out;
        }
        $forms = wpforms()->form->get();
        if (is_array($forms)) {
            foreach ($forms as $f) {
                $out[] = array('id' => (string) $f->ID, 'title' => $f->post_title);
            }
        }
        return $out;
    }

    public function get_form_fields($form_id)
    {
        $out = array();
        if (! function_exists('wpforms')) {
            return $out;
        }
        $form = wpforms()->form->get((int) $form_id);
        if (! $form) {
            return $out;
        }
        $data   = function_exists('wpforms_decode') ? wpforms_decode($form->post_content) : json_decode($form->post_content, true);
        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : array();
        foreach ($fields as $id => $field) {
            if (isset($field['type']) && in_array($field['type'], array('divider', 'html', 'pagebreak', 'captcha', 'entry-preview'), true)) {
                continue;
            }
            $label = isset($field['label']) && $field['label'] !== '' ? $field['label'] : ('Field ' . $id);
            $out[] = array('key' => (string) $id, 'label' => $label);
        }
        return $out;
    }

    public function get_form_id($args)
    {
        // wpforms_process_complete: ($fields, $entry, $form_data, $entry_id)
        if (isset($args[2]['id'])) {
            return (string) $args[2]['id'];
        }
        return '';
    }

    public function flatten_submission($args)
    {
        $fields = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        $out    = array();
        foreach ($fields as $id => $field) {
            if (! is_array($field) || ! isset($field['value'])) {
                continue;
            }
            // Map by field id AND by the field's label (WPForms 'name'), so admins can
            // use whichever they see. (Duplicate labels: last wins; use the id for those.)
            $out[(string) $id] = $field['value'];
            if (isset($field['name']) && $field['name'] !== '') {
                $out[$field['name']] = $field['value'];
            }
        }
        return $out;
    }
}
