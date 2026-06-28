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
        $this->capture($this->flatten_submission(func_get_args()));
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
}
