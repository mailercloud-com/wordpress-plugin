<?php
/**
 * Formidable Forms connector (free). Hook: frm_after_create_entry($entry_id, $form_id).
 * Submitted values are in $_POST['item_meta'] keyed by field id (the entry meta
 * is also queryable, but reading the posted item_meta avoids an extra DB load).
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_Formidable extends Mc_Connector_Base
{
    public function slug()
    {
        return 'formidable';
    }

    public function label()
    {
        return 'Formidable Forms';
    }

    public function is_active()
    {
        return class_exists('FrmHooksController') || class_exists('FrmAppHelper');
    }

    public function register_hooks()
    {
        add_action('frm_after_create_entry', array($this, 'on_submit'), 30, 2);
    }

    public function on_submit()
    {
        $this->capture($this->flatten_submission(func_get_args()));
    }

    public function flatten_submission($args)
    {
        // Formidable's frm_after_create_entry hook passes only ($entry_id, $form_id) — not the
        // submitted values — so we read $_POST['item_meta'] directly (the accepted Formidable
        // pattern). Formidable verifies its own form nonce before this hook fires.
        // NOTE: sanitize_text_field() here is required even though capture() sanitizes again —
        // do not remove it; flatten_submission() is the boundary where raw $_POST first enters.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($_POST['item_meta']) || ! is_array($_POST['item_meta'])) {
            return array();
        }
        $meta = wp_unslash($_POST['item_meta']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $out  = array();
        foreach ($meta as $field_id => $value) {
            if (is_scalar($value)) {
                $out[(string) $field_id] = sanitize_text_field($value);
            }
        }
        return $out;
    }
}
