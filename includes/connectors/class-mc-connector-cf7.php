<?php
/**
 * Contact Form 7 connector. Hook: wpcf7_mail_sent (fires after CF7 validation
 * + mail send). Submitted data comes from the active WPCF7_Submission.
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connector_CF7 extends Mc_Connector_Base
{
    public function slug()
    {
        return 'cf7';
    }

    public function label()
    {
        return 'Contact Form 7';
    }

    public function is_active()
    {
        return class_exists('WPCF7');
    }

    public function register_hooks()
    {
        add_action('wpcf7_mail_sent', array($this, 'on_submit'));
    }

    public function on_submit()
    {
        $this->handle(func_get_args());
    }

    public function flatten_submission($args)
    {
        if (! class_exists('WPCF7_Submission')) {
            return array();
        }
        $submission = WPCF7_Submission::get_instance();
        if (! $submission) {
            return array();
        }
        $posted = $submission->get_posted_data();
        return is_array($posted) ? $posted : array();
    }

    public function get_forms()
    {
        $out = array();
        $posts = get_posts(array('post_type' => 'wpcf7_contact_form', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        foreach ($posts as $p) {
            $out[] = array('id' => (string) $p->ID, 'title' => $p->post_title);
        }
        return $out;
    }

    public function get_form_fields($form_id)
    {
        $out = array();
        if (! class_exists('WPCF7_ContactForm')) {
            return $out;
        }
        $form = WPCF7_ContactForm::get_instance((int) $form_id);
        if (! $form) {
            return $out;
        }
        foreach ($form->scan_form_tags() as $tag) {
            $name = isset($tag->name) ? $tag->name : '';
            $type = isset($tag->basetype) ? $tag->basetype : '';
            if ($name === '' || in_array($type, array('submit', 'recaptcha', 'acceptance'), true)) {
                continue;
            }
            // CF7 tags have no human label — derive a readable one from the field name
            // (e.g. "your-email" -> "Email", "your-first-name" -> "First Name"). The real
            // field name is still stored as the key.
            $label = ucwords(str_replace(array('-', '_'), ' ', preg_replace('/^your[\s\-_]+/i', '', $name)));
            if ($label === '') {
                $label = $name;
            }
            $out[$name] = array('key' => $name, 'label' => $label);
        }
        return array_values($out);
    }

    public function get_form_id($args)
    {
        $form = isset($args[0]) ? $args[0] : null;
        return (is_object($form) && method_exists($form, 'id')) ? (string) $form->id() : '';
    }
}
