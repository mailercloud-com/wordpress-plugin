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
        $this->capture($this->flatten_submission(func_get_args()));
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
