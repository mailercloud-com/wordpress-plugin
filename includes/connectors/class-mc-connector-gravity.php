<?php
/**
 * Gravity Forms connector (Gravity is a paid plugin). Hook:
 * gform_after_submission($entry, $form). $entry is keyed by field input id
 * (e.g. "1", "2.3"); non-field meta keys are skipped.
 *
 * Code-verified against Gravity's documented hook; live-test when a license is
 * available.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_Gravity extends Mc_Connector_Base
{
    public function slug()
    {
        return 'gravity';
    }

    public function label()
    {
        return 'Gravity Forms';
    }

    public function is_active()
    {
        return class_exists('GFForms') || class_exists('GFCommon');
    }

    public function register_hooks()
    {
        add_action('gform_after_submission', array($this, 'on_submit'), 10, 2);
    }

    public function on_submit()
    {
        $this->handle(func_get_args());
    }

    public function get_forms()
    {
        $out = array();
        if (! class_exists('GFAPI')) {
            return $out;
        }
        foreach (GFAPI::get_forms() as $f) {
            $out[] = array('id' => (string) $f['id'], 'title' => isset($f['title']) ? $f['title'] : ('Form ' . $f['id']));
        }
        return $out;
    }

    public function get_form_fields($form_id)
    {
        $out = array();
        if (! class_exists('GFAPI')) {
            return $out;
        }
        $form = GFAPI::get_form((int) $form_id);
        if (! $form || empty($form['fields'])) {
            return $out;
        }
        foreach ($form['fields'] as $field) {
            $type = isset($field->type) ? $field->type : '';
            if (in_array($type, array('html', 'section', 'page', 'captcha', 'submit'), true)) {
                continue;
            }
            $id    = isset($field->id) ? (string) $field->id : '';
            $label = isset($field->label) ? $field->label : ('Field ' . $id);
            if ($id !== '') {
                $out[] = array('key' => $id, 'label' => $label);
            }
        }
        return $out;
    }

    public function get_form_id($args)
    {
        // gform_after_submission: ($entry, $form)
        return isset($args[1]['id']) ? (string) $args[1]['id'] : '';
    }

    public function flatten_submission($args)
    {
        $entry = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        $out   = array();
        foreach ($entry as $key => $value) {
            // Field input ids are numeric or numeric-with-dot ("2.3"); skip meta keys.
            if (is_scalar($value) && $value !== '' && preg_match('/^\d+(\.\d+)?$/', (string) $key)) {
                $out[(string) $key] = $value;
            }
        }
        return $out;
    }
}
