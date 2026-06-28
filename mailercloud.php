<?php
/**
 * Plugin Name:     Mailercloud - Integrate webforms and synchronize website contacts
 * Plugin URI:      https://app.mailercloud.com/
 * Description:     Mailercloud Wordpress plugin.
 * Author:          Mailercloud
 * Author URI:      https://mailercloud.com/
 * Text Domain:     mailercloud
 * Domain Path:     /languages
 * Version:         1.1.0
 *
 * @package         Mailercloud
 */
if (!defined('ABSPATH')) {
    exit;
}

class Mailercloud
{
    /**
     *
     *
     * @since 0.1.0
     * @var
     */
    public $version = '1.1.0';
    /* Member variables */
    public $mailercloud_api_key;
    public $default_mapping_array= [];
    public $plugin_path;

    /*
     * construct function
     *
     * @since 0.1.0
     */
    public function __construct()
    {
        if (defined('DOING_CRON') and DOING_CRON) {
            return;
        }
        $this->default_mapping_array=[
            [
            'wordpress_attribute' => 'Email',
            'mailercloud_attribute' => 'email',
            'is_custom_fields' => 0,
            ],
            [
                'wordpress_attribute' => 'FirstName',
                'mailercloud_attribute' => 'name',
                'is_custom_fields' => 0,
            ],
             [
                'wordpress_attribute' => 'LastName',
                'mailercloud_attribute' => 'last_name',
                'is_custom_fields' => 0,
            ]
        ];
       
        register_activation_hook(__FILE__, array( $this,'mailercloudPluginActivation'));
        register_deactivation_hook(__FILE__, array( $this,'mailercloudPluginDeactivation'));
        $this->mailercloud_includes();
        add_action(
            'admin_menu',
            array($this, 'add_mailercloud_admin_menu')
        );
        add_action(
            'plugins_loaded',
            array($this, 'mailercloud_plugin_init')
        );
        add_action(
            'activated_plugin',
            array($this, 'mailercloud_activation_redirect'),
            9,
            2
        );
        add_action('init', array($this,'mailercloud_add_custom_shortcode'));

        // -------------------------------------------------------------------------
        // Recurring background sync — INTENTIONALLY LEFT DORMANT (do not enable yet).
        //
        // Under standard WP-Cron this handler never fires: the constructor returns
        // early on DOING_CRON (above) before this registration runs, so the scheduled
        // 'mailercloud_cron_every_five_minutes' event has no callback during a real
        // cron request. We are deliberately KEEPING this as-is for now because:
        //   1. Real-time sync (user_register / profile_update, registered just below)
        //      already keeps contacts up to date for new and edited users, and
        //      "Sync My Users" handles full backfill on demand.
        //   2. Activating a 5-minute FULL re-sync across all installs would create a
        //      large, fleet-wide contact-upsert load and is not safe to switch on
        //      without a redesign (planned: a DAILY, changed-only safety re-sync).
        // The code is kept (not removed) for that future, planned enablement. Do NOT
        // move this registration above the DOING_CRON return without that plan.
        // (A harmless "invalid_schedule" notice may appear in debug.log while dormant;
        //  it is cosmetic and tracked for the future cron redesign.)
        // See: sync_contact_every_five_minutes_event() and mailercloud_cron_extra_schedules().
        // -------------------------------------------------------------------------
        add_filter('cron_schedules', array($this,'mailercloud_cron_extra_schedules'));
        add_action('mailercloud_cron_every_five_minutes', array($this,'sync_contact_every_five_minutes_event'));
        add_action('user_register', array($this,'mailercloud_registration_save'), 11, 1);
        add_action('profile_update', array($this,'mailercloud_updated_user_details'), 10, 2);
        if (get_option('mailercloud_api_key')) {
            $this->mailercloud_api_key = get_option('mailercloud_api_key') ? get_option('mailercloud_api_key') : '';
        }

        add_action(
            "wp_ajax_mailercloud_create_new_property",
            array($this, "mailercloud_create_new_property")
        );

        add_action(
            "wp_ajax_mailercloud_sync_contacts_now_ajax",
            array($this, "mailercloud_sync_contacts_now_ajax")
        );
        add_action("wp_ajax_mailercloud_save_connector_map", array($this, "mailercloud_save_connector_map"));
        add_action("wp_ajax_mailercloud_search_lists", array($this, "mailercloud_search_lists"));
        add_action("wp_ajax_mailercloud_search_tags", array($this, "mailercloud_search_tags"));
        add_action("wp_ajax_mailercloud_dismiss_review", array($this, "mailercloud_dismiss_review"));
        add_action("admin_notices", array($this, "mailercloud_maybe_show_review_notice"));
    }


    /**
         * mailercloud_activation_redirect
         *
         * @return void
         */
    public function mailercloud_activation_redirect()
    {
        if (is_admin() && get_option('mailercloud_Activated_Plugin') == 'mailercloud') {
            delete_option('mailercloud_Activated_Plugin');
            wp_safe_redirect(admin_url('admin.php?page=mailercloud-settings-page'));
            exit;
        }
    }

    /**
     * plugin file dir hooks
     *
     * @since 0.1.0
     *
     */
    public function mailercloud_plugin_dir_url()
    {
        return plugin_dir_url(__FILE__);
    }

    /**
     * Gets the absolute plugin path without a trailing slash, e.g.
     * /path/to/wp-content/plugins/plugin-directory.
     *
     * @since 0.1.0
     * @return  plugin path
     */
    public function mailercloud_get_plugin_path()
    {
        if (isset($this->plugin_path)) {
            return $this->plugin_path;
        }
        $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
        return $this->plugin_path;
    }

    /**
     * init hooks
     *
     * @since 0.1.0
     *
     */
    public function mailercloud_plugin_init()
    {
        //callback on activate plugin
        //load javascript in admin
        add_action(
            'admin_enqueue_scripts',
            array($this, 'mailercloud_admin_enqueue')
        );
    }
    

    /*
     * Add admin javascript
     *
     * @since 0.1.0
     */
    public function mailercloud_admin_enqueue($hook)
    {
        $page = isset($_GET["page"]) ? sanitize_text_field(wp_unslash($_GET["page"])) : "";

        // Add condition for css & js include for admin page
        $mc_pages = array(
            'mailercloud-settings-page',
            'mailercloud-subscriber-synchronisation',
            'mailercloud-signup-form-listing',
            'mailercloud-integrations',
            'mailercloud-analytics',
        );
        if (! in_array($page, $mc_pages, true)) {
            return;
        }
        wp_register_style(
            'mailercloud-admin',
            plugins_url('assets/css/mailercloud-style.css', __FILE__),
            array(),
            @filemtime($this->mailercloud_get_plugin_path() . '/assets/css/mailercloud-style.css') ?: $this->version
        );
        wp_register_style(
            'mailercloud-sweetalert',
            plugins_url('assets/sweetalert/sweetalert2.min.css', __FILE__),
            array(),
            $this->version
        );
        wp_enqueue_style('mailercloud-admin');
        wp_enqueue_style('mailercloud-sweetalert');
        wp_register_script(
            'mailercloud-admin-script',
            plugins_url('assets/js/mailercloud-scripts.js', __FILE__),
            array('jquery'),
            $this->version
        );
        wp_register_script(
            'mailercloud-sweetalert',
            plugins_url('assets/sweetalert/sweetalert2.min.js', __FILE__),
            array('jquery'),
            $this->version
        );
        wp_enqueue_script('mailercloud-sweetalert');
        wp_enqueue_script('mailercloud-admin-script');

        wp_register_script(
            'mailercloud-integrations',
            plugins_url('assets/js/mailercloud-integrations.js', __FILE__),
            array('jquery', 'mailercloud-admin-script'),
            @filemtime($this->mailercloud_get_plugin_path() . '/assets/js/mailercloud-integrations.js') ?: $this->version,
            true
        );
        // Only load the connector-mapping script on the Integrations page
        // (keeps the authenticated save-connector AJAX surface off unrelated pages).
        if ($page === 'mailercloud-integrations') {
            wp_enqueue_script('mailercloud-integrations');
            wp_localize_script('mailercloud-integrations', 'mcInt', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mailercloud_admin_ajax'),
            ));
        }

        wp_localize_script(
            'mailercloud-admin-script',
            'admAjax',
            array('ajaxurl' => admin_url('admin-ajax.php')
            )
        );
    }

    /*
     * Add frontend css
     *
     * @since 0.1.0
     */
    public function mailercloud_frontend_enqueue()
    {
        wp_enqueue_script(
            'mailercloud-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/mailercloud-frontend.js',
            array('jquery'),
            $this->version
        );
        wp_localize_script(
            'mailercloud-frontend',
            'ajax_object',
            array('front_ajax_url' => admin_url('admin-ajax.php')
            )
        );
        wp_enqueue_style(
            'mailercloud-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/mailercloud-frontend.css',
            array(),
            $this->version
        );
    }



    /**
     * Include plugin file.
     *
     * @since 0.1.0
     *
     */
    public function mailercloud_includes()
    {
        require_once $this->mailercloud_get_plugin_path() . '/config/constants.php';
        require_once $this->mailercloud_get_plugin_path() . '/includes/mailercloud_functions.php';
        require_once $this->mailercloud_get_plugin_path() . '/includes/class-mc-contact-sync.php';
        require_once $this->mailercloud_get_plugin_path() . '/includes/class-mc-analytics.php';
        require_once $this->mailercloud_get_plugin_path() . '/includes/class-mc-connectors-loader.php';
        require_once $this->mailercloud_get_plugin_path() . '/widgets/mailercloud_widget.php';
        require_once $this->mailercloud_get_plugin_path() . '/blocks/mailercloud-forms.php';

        // Register form-plugin connectors on init (after host form plugins have loaded).
        $mc_connectors = new Mc_Connectors_Loader();
        add_action('init', array($mc_connectors, 'register'));
    }
    
    /**
     * add_mailercloud_admin_menu
     *
     * @return void
     */
    public function add_mailercloud_admin_menu()
    {
        add_menu_page(
            __('Mailercloud', 'mailercloud'),
            __('Mailercloud', 'mailercloud'),
            'manage_options',
            'mailercloud-settings-page',
            array($this, 'create_mailercloud_settings_page'),
            plugins_url('assets/images/icon.png', __FILE__)
        );
        add_submenu_page(
            'mailercloud-settings-page',
            __('Settings', 'mailercloud'),
            __('Settings', 'mailercloud'),
            'manage_options',
            'mailercloud-settings-page',
            array($this, 'create_mailercloud_settings_page')
        );
        add_submenu_page(
            'mailercloud-settings-page',
            __('Contact Sync', 'mailercloud'),
            __('Contact Sync', 'mailercloud'),
            'manage_options',
            'mailercloud-subscriber-synchronisation',
            array($this, 'create_mailercloud_synchronisation_settings_page')
        );
        add_submenu_page(
            'mailercloud-settings-page',
            __('Forms', 'mailercloud'),
            __('Forms', 'mailercloud'),
            'manage_options',
            'mailercloud-signup-form-listing',
            array($this, 'create_mailercloudp_form_listing_settings_page')
        );
        add_submenu_page(
            'mailercloud-settings-page',
            __('Integrations', 'mailercloud'),
            __('Integrations', 'mailercloud'),
            'manage_options',
            'mailercloud-integrations',
            array($this, 'create_mailercloud_integrations_page')
        );
        add_submenu_page(
            'mailercloud-settings-page',
            __('Analytics', 'mailercloud'),
            __('Analytics', 'mailercloud'),
            'manage_options',
            'mailercloud-analytics',
            array($this, 'create_mailercloud_analytics_page')
        );
    }

    /**
     * Fetch MailerCloud lists, custom fields and tags for the connector mapping UI.
     *
     * @return array [ $lists (id=>name), $custom_fields (id=>name), $tags (id=>name) ]
     */
    private function mailercloud_fetch_mc_meta()
    {
        $lists = array();
        $custom_fields = array();
        $tags = array();
        $api_key = get_option('mailercloud_api_key');
        if (! $api_key) {
            return array($lists, $custom_fields, $tags);
        }
        $list_resp = callWpRemoteRestApi('POST', MAILERCLOUD_SUBSCRIBER_SYNC_API_URL, $api_key, json_encode(array(
            'limit' => 100, 'list_type' => 1, 'page' => 1, 'search_name' => '', 'sort_field' => 'name', 'sort_order' => 'asc',
        )));
        if (! empty($list_resp['data'])) {
            foreach ($list_resp['data'] as $l) {
                if (isset($l['id'])) {
                    $lists[$l['id']] = isset($l['name']) ? $l['name'] : $l['id'];
                }
            }
        }
        $prop_resp = callWpRemoteRestApi('POST', MAILERCLOUD_SUBSCRIBER_SYNC_CONTACT_PROPERTY_API_URL, $api_key, json_encode(array(
            'limit' => 100, 'page' => 1, 'search' => '',
        )));
        if (! empty($prop_resp['data'])) {
            foreach ($prop_resp['data'] as $f) {
                if (empty($f['is_default']) && isset($f['id'])) {
                    $custom_fields[$f['id']] = isset($f['field_name']) ? $f['field_name'] : $f['id'];
                }
            }
        }
        $tag_resp = callWpRemoteRestApi('POST', MAILERCLOUD_TAG_LISTING_API_URL, $api_key, json_encode(array(
            'limit' => 100, 'page' => 1, 'search' => '',
        )));
        if (! empty($tag_resp['data'])) {
            foreach ($tag_resp['data'] as $t) {
                if (isset($t['id'])) {
                    $tags[$t['id']] = isset($t['tag_name']) ? $t['tag_name'] : $t['id'];
                }
            }
        }
        return array($lists, $custom_fields, $tags);
    }

    /**
     * Integrations admin page — connect third-party form plugins.
     */
    public function create_mailercloud_integrations_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $api_key = get_option('mailercloud_api_key');
        $loader = new Mc_Connectors_Loader();
        $connectors = $loader->all();
        list($lists, $custom_fields, $tags) = $this->mailercloud_fetch_mc_meta();
        $mc_connector_nonce = wp_create_nonce('mailercloud_admin_ajax');
        require_once $this->mailercloud_get_plugin_path() . '/templates/mailercloud-integrations.php';
    }

    /**
     * Analytics admin page — lead-capture counts from this plugin.
     */
    public function create_mailercloud_analytics_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $stats = class_exists('Mc_Analytics') ? Mc_Analytics::all() : array();
        $total_captured = class_exists('Mc_Analytics') ? Mc_Analytics::total_captured() : 0;
        $loader = new Mc_Connectors_Loader();
        $labels = array();
        foreach ($loader->all() as $c) {
            $labels[$c->slug()] = $c->label();
        }
        require_once $this->mailercloud_get_plugin_path() . '/templates/mailercloud-analytics.php';
    }

    /**
     * AJAX: save a connector's mapping config. Same nonce + capability guard as
     * the other admin AJAX handlers (LIVE-741 model).
     */
    public function mailercloud_save_connector_map()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $allowed_slugs = array('cf7', 'wpforms', 'elementor', 'gravity', 'ninja', 'formidable');
        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        if (! in_array($slug, $allowed_slugs, true)) {
            wp_send_json_error(array('message' => 'invalid connector'), 400);
        }

        $enabled = ! empty($_POST['enabled']);
        // List/tag ids are short alphanumeric tokens (e.g. "wKKwyf"); keep as string, just bound length.
        $list_id = isset($_POST['list_id']) ? substr(sanitize_text_field(wp_unslash($_POST['list_id'])), 0, 64) : '';

        $mapping = array();
        if (! empty($_POST['mapping']) && is_array($_POST['mapping'])) {
            foreach (wp_unslash($_POST['mapping']) as $pair) {
                if (! isset($pair['field_key'], $pair['mc_attr'])) {
                    continue;
                }
                $field_key = substr(sanitize_text_field($pair['field_key']), 0, 200);
                $mc_attr   = sanitize_text_field($pair['mc_attr']);
                if ($field_key === '' || $mc_attr === '') {
                    continue;
                }
                // Whitelist destinations: standard fields or custom_fields_<id>.
                if (! in_array($mc_attr, array('email', 'name', 'last_name'), true)
                    && ! preg_match('/^custom_fields_[A-Za-z0-9_-]+$/', $mc_attr)) {
                    continue;
                }
                $mapping[] = array('wordpress_attribute' => $field_key, 'mailercloud_attribute' => $mc_attr);
            }
        }

        if (! empty($_POST['tags']) && is_array($_POST['tags'])) {
            $tag_ids = array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['tags']))));
            if (! empty($tag_ids)) {
                $mapping[] = array('wordpress_attribute' => 'tags', 'mailercloud_attribute' => wp_json_encode($tag_ids));
            }
        }

        // Email is mandatory in MailerCloud — an enabled connector must map a field to Email
        // and choose a list. (A disabled connector can be saved incomplete.)
        if ($enabled) {
            $has_email = false;
            foreach ($mapping as $m) {
                if ($m['mailercloud_attribute'] === 'email' && $m['wordpress_attribute'] !== '') {
                    $has_email = true;
                    break;
                }
            }
            if (! $has_email) {
                wp_send_json_error(array('message' => __('Map a form field to Email — it is required.', 'mailercloud')), 400);
            }
            if (empty($list_id)) {
                wp_send_json_error(array('message' => __('Choose a list to add contacts to.', 'mailercloud')), 400);
            }
        }

        update_option('mailercloud_connector_map_' . $slug, array(
            'enabled' => $enabled,
            'list_id' => $list_id,
            'mapping' => $mapping,
        ), false);

        wp_send_json_success(array('message' => 'saved'));
    }

    /**
     * AJAX: search MailerCloud lists for the searchable list dropdown (server-side search).
     * Returns { results: [ { id, text }, ... ] }. Loads up to 100 per query.
     */
    public function mailercloud_search_lists()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('results' => array()), 403);
        }
        $api_key = get_option('mailercloud_api_key');
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $results = array();
        if ($api_key) {
            $resp = callWpRemoteRestApi('POST', MAILERCLOUD_SUBSCRIBER_SYNC_API_URL, $api_key, wp_json_encode(array(
                'limit' => 100, 'list_type' => 1, 'page' => 1, 'search_name' => $q, 'sort_field' => 'name', 'sort_order' => 'asc',
            )));
            if (! empty($resp['data'])) {
                foreach ($resp['data'] as $l) {
                    if (isset($l['id'])) {
                        $results[] = array('id' => $l['id'], 'text' => isset($l['name']) ? $l['name'] : $l['id']);
                    }
                }
            }
        }
        wp_send_json(array('results' => $results));
    }

    /**
     * AJAX: search MailerCloud tags for the searchable tag dropdown (server-side search).
     */
    public function mailercloud_search_tags()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('results' => array()), 403);
        }
        $api_key = get_option('mailercloud_api_key');
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $results = array();
        if ($api_key) {
            $resp = callWpRemoteRestApi('POST', MAILERCLOUD_TAG_LISTING_API_URL, $api_key, wp_json_encode(array(
                'limit' => 100, 'page' => 1, 'search' => $q,
            )));
            if (! empty($resp['data'])) {
                foreach ($resp['data'] as $t) {
                    if (isset($t['id'])) {
                        $results[] = array('id' => $t['id'], 'text' => isset($t['tag_name']) ? $t['tag_name'] : $t['id']);
                    }
                }
            }
        }
        wp_send_json(array('results' => $results));
    }

    /**
     * admin_notices: a one-time review-request nudge once the plugin has captured
     * at least one lead, dismissible/snoozable. Gap #5 (review engine).
     */
    public function mailercloud_maybe_show_review_notice()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        if (! class_exists('Mc_Analytics') || Mc_Analytics::total_captured() < 1) {
            return;
        }
        $state = get_option('mailercloud_review_notice', array());
        if (! is_array($state)) {
            $state = array();
        }
        if (! empty($state['dismissed'])) {
            return;
        }
        if (! empty($state['snooze_until']) && time() < intval($state['snooze_until'])) {
            return;
        }
        $review_url = 'https://wordpress.org/support/plugin/mailercloud-integrate-webforms-synchronize-contacts/reviews/#new-post';
        $nonce = wp_create_nonce('mailercloud_admin_ajax');
        echo '<div class="notice notice-info is-dismissible" id="mailercloud-review-notice" data-nonce="' . esc_attr($nonce) . '">';
        echo '<p>' . esc_html__('Mailercloud has started capturing leads from your forms. If it is helping, a quick 5-star review would mean a lot.', 'mailercloud') . ' ';
        echo '<a href="' . esc_url($review_url) . '" target="_blank" rel="noopener" class="button button-primary" id="mailercloud-review-now">' . esc_html__('Leave a review', 'mailercloud') . '</a> ';
        echo '<a href="#" id="mailercloud-review-later">' . esc_html__('Maybe later', 'mailercloud') . '</a></p>';
        echo '</div>';

        $ajax_url = admin_url('admin-ajax.php');
        $inline = "(function(){var n=document.getElementById('mailercloud-review-notice');if(!n)return;"
            . "var nonce=n.getAttribute('data-nonce');"
            . "function send(mode){var b=new URLSearchParams();b.append('action','mailercloud_dismiss_review');b.append('mode',mode);b.append('_ajax_nonce',nonce);"
            . "fetch(" . wp_json_encode($ajax_url) . ",{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()});}"
            . "var later=document.getElementById('mailercloud-review-later');if(later){later.addEventListener('click',function(e){e.preventDefault();send('later');n.style.display='none';});}"
            . "n.addEventListener('click',function(e){if(e.target&&e.target.classList&&e.target.classList.contains('notice-dismiss')){send('dismiss');}});"
            . "var now=document.getElementById('mailercloud-review-now');if(now){now.addEventListener('click',function(){send('dismiss');});}})();";
        echo '<script>' . $inline . '</script>';
    }

    /**
     * AJAX: record review-notice dismissal/snooze.
     */
    public function mailercloud_dismiss_review()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'dismiss';
        if ($mode === 'later') {
            update_option('mailercloud_review_notice', array('snooze_until' => time() + (14 * DAY_IN_SECONDS)), false);
        } else {
            update_option('mailercloud_review_notice', array('dismissed' => true), false);
        }
        wp_send_json_success();
    }
    



    public function mailercloud_create_new_property()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        $response = array();
        $api_key  = get_option('mailercloud_api_key');
        if (isset($_POST['name']) && $api_key) {
            $this->mailercloud_api_key = $api_key;
            // Field type is constrained to the options the UI offers.
            $allowed_types = array('text', 'number', 'textarea', 'date');
            $type = sanitize_text_field(wp_unslash(isset($_POST['type']) ? $_POST['type'] : ''));
            if (! in_array($type, $allowed_types, true)) {
                $type = 'text';
            }
            $name = sanitize_text_field(wp_unslash($_POST['name']));
            $data = array(
                'description' => sanitize_text_field(wp_unslash(isset($_POST['description']) ? $_POST['description'] : '')),
                'name'        => $name,
                'type'        => $type,
            );
            $response = callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_CREATE_NEW_PROPERTY_API_URL,
                $api_key,
                wp_json_encode($data)
            );
            if (isset($response['id'])) {
                $response['name']    = $name;
                $response['message'] = __('New property created successfully.', 'mailercloud');
            }
        }
        wp_send_json($response);
    }

    public function mailercloud_sync_contacts_now_ajax()
    {
        check_ajax_referer('mailercloud_admin_ajax', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }
        $users =[];
        $response =[];
        $list_id ='';
        $api_key='';
        $user_data =[];
        $status =1;
        $attribute_mapping_arr=[];
        if (isset($_POST['action'])) {
            if (!ini_get('safe_mode')) {
                $timeout = 1800;
                @set_time_limit($timeout);
                @ini_set('max_execution_time', $timeout);
            }
        
            // increase php memory limit
            $mem_limit = '1024M';
            @ini_set('memory_limit', $mem_limit);

            if (get_option('mailercloud_selected_sync_list_id')) {
                $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
                $list_id = $mailercloud_selected_sync_list_id;
            }
            if (get_option('mailercloud_api_key')) {
                $api_key = get_option('mailercloud_api_key') ;
            }
            // Count subscribers via the WP core aggregate — does not load user objects into memory.
            $role_counts = count_users();
            $user_count = isset($role_counts['avail_roles']['subscriber'])
                ? (int) $role_counts['avail_roles']['subscriber']
                : 0;
            if ($user_count > 0) {
                if ($list_id) {
                    if ($api_key) {
                        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                        $attribute_mapping_arr = json_decode(file_get_contents($file), true);
                        if (!empty($attribute_mapping_arr)) {
                            $limit = 49;
                            $pages = (int) ceil($user_count / $limit);
                            $total_inserted = 0;
                            $total_skipped = 0;
                            $total_submitted = 0;
                            $total_updated = 0;
                            $user_data = [];

                            // Fetch and send one batch of users at a time — keeps peak memory bounded
                            // regardless of how many subscribers the site has.
                            for ($page = 1; $page <= $pages; $page++) {
                                $offset = ($page - 1) * $limit;
                                $user_batch = get_users(array(
                                    'role'    => 'subscriber',
                                    'orderby' => 'user_nicename',
                                    'order'   => 'ASC',
                                    'number'  => $limit,
                                    'offset'  => $offset,
                                    'fields'  => 'ID',
                                ));
                                if (empty($user_batch)) {
                                    continue;
                                }
                                $temp_final = [];
                                foreach ($user_batch as $user_id) {
                                    $user = getwordpressUserAttributes($user_id);
                                    $temp = [];
                                    $temp_custom = [];
                                    foreach ($attribute_mapping_arr as $row) {
                                        $find = "custom_fields_";
                                        if ($row['wordpress_attribute'] == 'tags') {
                                            $temp['tags'] = json_decode($row['mailercloud_attribute'], true);
                                        } else if (strpos($row['mailercloud_attribute'], $find) !== false) {
                                            $mailercloud_attribute_key = str_replace($find, '', $row['mailercloud_attribute']);
                                            $temp_custom[$mailercloud_attribute_key] = isset($user[$row['wordpress_attribute']]) ? $user[$row['wordpress_attribute']] : '';
                                        } else {
                                            $temp[$row['mailercloud_attribute']] = isset($user[$row['wordpress_attribute']]) ? $user[$row['wordpress_attribute']] : '';
                                        }
                                    }
                                    if (!empty($temp_custom)) {
                                        $temp['custom_fields'] = $temp_custom;
                                    }
                                    if (!empty($temp)) {
                                        $temp_final[] = $temp;
                                    }
                                }
                                if (!empty($temp_final)) {
                                    $contacts_data = array(
                                        'contacts' => $temp_final,
                                        'list_id'  => $list_id,
                                    );
                                    $response = callWpRemoteRestApi(
                                        'POST',
                                        MAILERCLOUD_SUBSCRIBER_SYNC_CONTACTS_BATCH_API_URL,
                                        $api_key,
                                        json_encode($contacts_data)
                                    );
                                    if (isset($response['data'])) {
                                        $total_inserted  += isset($response['data']['inserted']) ? (int) $response['data']['inserted'] : 0;
                                        $total_skipped   += isset($response['data']['skipped']) ? (int) $response['data']['skipped'] : 0;
                                        $total_submitted += isset($response['data']['submitted']) ? (int) $response['data']['submitted'] : 0;
                                        $total_updated   += isset($response['data']['updated']) ? (int) $response['data']['updated'] : 0;
                                        $msg = true;
                                    } else {
                                        $message = isset($response['message']) ? $response['message'] : '';
                                        if (isset($response['errors'])) {
                                            $errors = $response['errors'];
                                        }
                                        $msg = false;
                                    }
                                }
                                // Free per-batch memory before fetching the next batch.
                                unset($user_batch, $temp_final);
                                usleep(400000);
                            }
                            $message = 'Contact synchronization has been completed';
                            $user_data['inserted']  = $total_inserted;
                            $user_data['skipped']   = $total_skipped;
                            $user_data['submitted'] = $total_submitted;
                            $user_data['updated']   = $total_updated;
                            $response['message'] = $message;
                            $response['status']  = $status;
                            $response['data']    = $user_data;
                        }
                    }
                } else {
                    $message = 'Please choose the Mailcloud list to sync with.';
                    $response['status']  = 0;
                    $response['message'] = $message;
                }
            }
        }

        echo json_encode($response);
        die();
    }

  
    public function mailercloud_cron_extra_schedules($schedules)
    {
        $schedules['every_five_minutes'] = array(
            'interval'  =>  5*60,
            'display'   => __('Every 5 Minutes', 'mailercloud')
    );
        return $schedules;
    }


 
    public function mailercloud_add_custom_shortcode()
    {
        add_shortcode('sibwp_form', array($this,'create_dynamic_shortcode_signup_form'));
    }

    public function mailercloudPluginActivation()
    {
        add_option('mailercloud_Activated_Plugin', 'mailercloud');
        // Schedule an action if it's not already scheduled
        if (! wp_next_scheduled('mailercloud_cron_every_five_minutes')) {
            wp_schedule_event(time(), 'every_five_minutes', 'mailercloud_cron_every_five_minutes');
        }
        // NOTE: do NOT clear the saved API key / list on activation. Plugin updates don't
        // run activation, but a manual deactivate->reactivate previously wiped the stored
        // credentials, silently breaking sync until re-entered. Disconnect is handled
        // explicitly by the logout action instead.
        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
        if ($file) {
            if (file_put_contents($file, json_encode($this->default_mapping_array))) {
            }
        }
    }
   
    /**
     * mailercloudPluginDeactivation
     *
     * @return void
     */
    public function mailercloudPluginDeactivation()
    {
        $timestamp = wp_next_scheduled('mailercloud_cron_every_five_minutes');
        wp_unschedule_event($timestamp, 'mailercloud_cron_every_five_minutes');
        wp_clear_scheduled_hook('mailercloud_cron_every_five_minutes');
    }

   
 
    /**
     * mailercloud_registration_save
     *
     * @param  mixed $user_id
     * @return void
     */
    public function mailercloud_registration_save($user_id)
    {
        $user_info = get_userdata($user_id);
        $user_email = $user_info->user_email;
        $user = getwordpressUserAttributes($user_id);
        $api_key='';
        $list_id = '';
        $attribute_mapping_arr=[];
        if (get_option('mailercloud_selected_sync_list_id')) {
            $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
            $list_id = $mailercloud_selected_sync_list_id;
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key') ;
        }
        if ($list_id) {
            if ($api_key) {
                $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                $attribute_mapping_arr = json_decode(file_get_contents($file), true);
                $contacts = [];
                if (!empty($attribute_mapping_arr)) {
                    $temp = [];
                    $temp_final = [];
                    if ($user) {
                        $temp_custom =[];
                        foreach ($attribute_mapping_arr as $row) {
                            $find="custom_fields_";
                            if ($row['wordpress_attribute'] == 'tags') {
                                $temp['tags'] = json_decode($row['mailercloud_attribute'],true);
                            } elseif (strpos($row['mailercloud_attribute'], $find) !== false) {
                                $mailercloud_attribute_key = str_replace($find, '', $row['mailercloud_attribute']);
                                $temp_custom[$mailercloud_attribute_key] = $user[$row['wordpress_attribute']];
                            } else {
                                $temp[$row['mailercloud_attribute']] =$user[$row['wordpress_attribute']] ;
                            }
                        }
                        if (!empty($temp_custom)) {
                            $temp['custom_fields'] = $temp_custom;
                        }
                        if (!empty($temp)) {
                            $temp_final= $temp;
                        }
                    }
                   
                    $contacts = $temp_final;
               
                    $contact_data = $contacts;
                    $contact_data['list_id'] = $list_id;
                    $response= callWpRemoteRestApi(
                        'POST',
                        MAILERCLOUD_SUBSCRIBER_SYNC_SINGLE_CONTACT_API_URL,
                        $api_key,
                        json_encode($contact_data)
                    );
                    if (isset($response['id'])) {
                        // $updated = update_user_meta($user_id, 'mailercloud_is_synched',true);
                    } else {
                        $message = isset($response['message'])?$response['message']:'';
                        $msg = false;
                    }
                }
            }
        }
    }


    /**
     * mailercloud_updated_user_details
     *
     * @param  mixed $user_id
     * @param  mixed $old_user_data
     * @return void
     */
    public function mailercloud_updated_user_details($user_id, $old_user_data)
    {
        $users =[];
        $list_id ='';
        $api_key='';
        $attribute_mapping_arr=[];
        $user_info = get_userdata($user_id);
        $user_email = $user_info->user_email;
        $user = getwordpressUserAttributes($user_id);
        if (get_option('mailercloud_selected_sync_list_id')) {
            $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
            $list_id = $mailercloud_selected_sync_list_id;
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key') ;
        }

        if ($user_id) {
            if ($api_key) {
                $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                $attribute_mapping_arr = json_decode(file_get_contents($file), true);
                $contacts = [];
                if (!empty($attribute_mapping_arr)) {
                    $temp = [];
                    $temp_final = [];
                    if ($user) {
                        $temp_custom =[];
                        foreach ($attribute_mapping_arr as $row) {
                            $find="custom_fields_";
                            if ($row['wordpress_attribute'] == 'tags') {
                                $temp['tags'] =  json_decode($row['mailercloud_attribute'],true);
                            } elseif (strpos($row['mailercloud_attribute'], $find) !== false) {
                                $mailercloud_attribute_key = str_replace($find, '', $row['mailercloud_attribute']);
                                $temp_custom[$mailercloud_attribute_key] = $user[$row['wordpress_attribute']];
                            } else {
                                $temp[$row['mailercloud_attribute']] =$user[$row['wordpress_attribute']] ;
                            }
                        }
                        if (!empty($temp_custom)) {
                            $temp['custom_fields'] = $temp_custom;
                        }
                        if (!empty($temp)) {
                            $temp_final = $temp;
                        }
                    }
                   
                    $contacts = $temp_final;
                    $contact_data = $contacts;
                    if (isset($contact_data['email'])) {
                        unset($contact_data['email']);
                    }
                    $response= callWpRemoteRestApi(
                        'PUT',
                        MAILERCLOUD_SUBSCRIBER_SYNC_SINGLE_CONTACT_UPDATE_API_URL . rawurlencode($user_email),
                        $api_key,
                        json_encode($contact_data)
                    );
                    if (isset($response['message'])) {
                        // $updated = update_user_meta($user_id, 'mailercloud_is_synched',true);
                    } else {
                        $message = isset($response['message'])?$response['message']:'error';
                        $msg = false;
                    }
                }
            }
        }
    }



    /**
     * sync_contact_every_five_minutes_event
     *Hook into that action that'll fire every five minutes
     * @return void
     */
    /**
     * Recurring background re-sync of subscribers.
     *
     * DORMANT: this is not active under standard WP-Cron (the constructor returns
     * early on DOING_CRON before the hook is registered — see the note next to the
     * add_action in __construct). Kept intact for a future, planned redesign into a
     * DAILY, changed-only safety re-sync. Real-time sync (user_register /
     * profile_update) and the manual "Sync My Users" button cover current needs.
     */
    public function sync_contact_every_five_minutes_event()
    {
        $list_id = '';
        $api_key = '';
        if (get_option('mailercloud_selected_sync_list_id')) {
            $list_id = get_option('mailercloud_selected_sync_list_id');
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key');
        }
        // Bail early if the site has not finished setup — avoids loading any user data.
        if (empty($list_id) || empty($api_key)) {
            return;
        }
        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
        $attribute_mapping_arr = json_decode(file_get_contents($file), true);
        if (empty($attribute_mapping_arr)) {
            return;
        }
        $role_counts = count_users();
        $user_count = isset($role_counts['avail_roles']['subscriber'])
            ? (int) $role_counts['avail_roles']['subscriber']
            : 0;
        if ($user_count === 0) {
            return;
        }
        $limit = 49;
        $pages = (int) ceil($user_count / $limit);
        // Cap how long a single cron run takes, so big sites don't pile up overlapping jobs.
        $start_time = time();
        $max_runtime = 60; // seconds; remaining batches will be picked up by the next cron tick
        for ($page = 1; $page <= $pages; $page++) {
            if ((time() - $start_time) > $max_runtime) {
                break;
            }
            $offset = ($page - 1) * $limit;
            $user_batch = get_users(array(
                'role'    => 'subscriber',
                'orderby' => 'user_nicename',
                'order'   => 'ASC',
                'number'  => $limit,
                'offset'  => $offset,
                'fields'  => 'ID',
            ));
            if (empty($user_batch)) {
                continue;
            }
            $temp_final = [];
            foreach ($user_batch as $user_id) {
                $user = getwordpressUserAttributes($user_id);
                $temp = [];
                $temp_custom = [];
                foreach ($attribute_mapping_arr as $row) {
                    $find = "custom_fields_";
                    if ($row['wordpress_attribute'] == 'tags') {
                        $temp['tags'] = json_decode($row['mailercloud_attribute'], true);
                    } elseif (strpos($row['mailercloud_attribute'], $find) !== false) {
                        $mailercloud_attribute_key = str_replace($find, '', $row['mailercloud_attribute']);
                        $temp_custom[$mailercloud_attribute_key] = isset($user[$row['wordpress_attribute']]) ? $user[$row['wordpress_attribute']] : '';
                    } else {
                        $temp[$row['mailercloud_attribute']] = isset($user[$row['wordpress_attribute']]) ? $user[$row['wordpress_attribute']] : '';
                    }
                }
                if (!empty($temp_custom)) {
                    $temp['custom_fields'] = $temp_custom;
                }
                if (!empty($temp)) {
                    $temp_final[] = $temp;
                }
            }
            if (empty($temp_final)) {
                unset($user_batch, $temp_final);
                continue;
            }
            $contact_data = array(
                'contacts' => $temp_final,
                'list_id'  => $list_id,
            );
            $response = callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_SUBSCRIBER_SYNC_CONTACTS_BATCH_API_URL,
                $api_key,
                json_encode($contact_data)
            );
            if (isset($response['data'])) {
                $user_data = $response['data'];
            } else {
                $message = isset($response['message']) ? $response['message'] : '';
            }
            // Free per-batch memory before fetching the next batch.
            unset($user_batch, $temp_final);
        }
    }

   
    /**
     * create_mailercloud_settings_page
     *
     * @return void
     */
    public function create_mailercloud_settings_page()
    {
        $mc_api_key_nonce = wp_create_nonce('mc_api_key');
        $mc_api_logout_nonce = wp_create_nonce('mc_api_logout');
        $msg = false;
        $message = '';
        $api_key = '';
        $user_data = [];
        if (isset($_POST['mc_account_logout'])) {
            $mc_api_logout_nonce = (isset($_POST['mc_api_logout_nonce'])) ? sanitize_text_field($_POST['mc_api_logout_nonce']) : null;
            if (wp_verify_nonce($mc_api_logout_nonce, 'mc_api_logout') && current_user_can('manage_options')) {
                if (isset($_POST['mc_account_logout']) && !empty($_POST['mc_account_logout'])) {
                    update_option('mailercloud_api_key', '');
                    update_option('mailercloud_selected_sync_list_id', '');
                    $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                    if (file_put_contents($file, json_encode($this->default_mapping_array))) {
                    }
                }
            }
        }

        if (isset($_POST['apikey_verify'])) {
            $nonce = (isset($_POST['mc_api_key_nonce'])) ? sanitize_text_field($_POST['mc_api_key_nonce']) : null;
            if (wp_verify_nonce($nonce, 'mc_api_key') && current_user_can('manage_options')) {
                if (isset($_POST['mc_api_key']) && !empty($_POST['mc_api_key'])) {
                    $api_key = sanitize_text_field($_POST['mc_api_key']);
                    $this->mailercloud_api_key = $api_key;
                    
                    $response= callWpRemoteRestApi(
                        'GET',
                        MAILERCLOUD_USER_DETAILS_API_URL,
                        $api_key
                    );
                    if (isset($response['data'])) {
                        update_option('mailercloud_api_key', $api_key);
                        $user_data = $response['data'];
                        $message = 'Api Key is verified successfully.';
                        $msg = true;
                    } else {
                        update_option('mailercloud_api_key', '');
                        update_option('mailercloud_selected_sync_list_id', '');
                        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                        if (file_put_contents($file, json_encode($this->default_mapping_array))) {
                        }
                        $message = 'Entered Api Key is not valid.';
                        $msg = false;
                    }
                }
            }
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key');
            $this->mailercloud_api_key = $api_key;
           
            $response= callWpRemoteRestApi(
                'GET',
                MAILERCLOUD_USER_DETAILS_API_URL,
                $api_key
            );

            if (isset($response['data'])) {
                $user_data = $response['data'];
            } else {
            }
        }
        require_once $this->mailercloud_get_plugin_path() . '/templates/mailercloud-setting-form.php';
    }
    
    /**
     * create_mailercloud_synchronisation_settings_page
     *
     * @return void
     */
    public function create_mailercloud_synchronisation_settings_page()
    {
        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
        $jsonData = json_decode(file_get_contents($file), true);
        $mc_sync_list_key = wp_create_nonce('mc_sync_list_key');
        $msg = false;
        $message = '';
        $message2 = '';
        $msg2 = true;
        $api_key = '';
        $users=[];
        $user_data = [];
        $contact_data = [];
        $tagsData = [];
        $lists = [];
        $list_id ='';
        $errors= [];
        $user_count = 0;
        // Use count_users() instead of loading every user object — works for sites with large subscriber counts.
        $role_counts = count_users();
        if (isset($role_counts['avail_roles']['subscriber'])) {
            $user_count = (int) $role_counts['avail_roles']['subscriber'];
        }
        $wordpress_attributes = [];
        $selected_wordpress_attributes = [];
        $selected_mailercloud_attributes = [];
        $selected_list_name = '';
        $mailercloud_attributes_properties =[];
        $mailercloud_tags =[];
        $attribute_mapping_arr=[];
        $mailercloud_attributes = array(
            'email' => 'Email',
            'name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'country' => 'Country',
            'city' => 'City',
            'state' => 'State',
            'phone' => 'Phone',
            'zip' => 'Zip',
            'industry' => 'Industry',
            'organization' => 'Organization',
            'department' => 'Department',
            'job_title' => 'Job title',
            'salary' => 'Salary',
            'lead_source' => 'Lead source'
        );
        
        //$wordpress_attributes = getwordpressUserAttributes(get_current_user_id());
        $wordpress_attributes = [];
        $wordpress_attributes['Email'] = 'Email';
        $wordpress_attributes['FirstName'] ='First name';
        $wordpress_attributes['LastName'] = 'Last name';
        $wordpress_attributes['UserLogin'] = 'User login';
        $wordpress_attributes['UserNicename'] = 'User nicename';
        $wordpress_attributes['DisplayName'] ='Display name';
        $wordpress_attributes['UserRegistered'] = 'User registered date';
        $wordpress_attributes['BillingFirstName'] = 'Billing first name';
        $wordpress_attributes['BillingLastName'] = 'Billing last name';
        $wordpress_attributes['BillingCompany'] = 'Billing company';
        $wordpress_attributes['BillingAddress1'] = 'Billing address';
        $wordpress_attributes['BillingCity'] = 'Billing city';
        $wordpress_attributes['BillingState'] = 'Billing state';
        $wordpress_attributes['BillingPostcode'] = 'Billing postcode';
        $wordpress_attributes['BillingCountry'] = 'Billing country';
        $wordpress_attributes['BillingPhone'] ='Billing phone';
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key');
            $this->mailercloud_api_key = $api_key;
        }
        if ($api_key) {
            $data = array(
                'limit' => 100,
                'list_type' => 1,
                'page' => 1,
                'search_name' => '',
                'sort_field' => 'name',
                'sort_order' => 'asc'
            );
          
            $response= callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_SUBSCRIBER_SYNC_API_URL,
                $api_key,
                json_encode($data)
            );
            if (isset($response['data'])) {
                $listing = $response['data'];
                foreach ($listing as $list) {
                    $lists[$list['id']] = $list['name'];
                }
            } else {
            }

            /** get custom fields contact properties  **/
            $property_data = array(
                'limit' => 100,
                'page' => 1,
                'search' => '',
            );
            
            $p_response= callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_SUBSCRIBER_SYNC_CONTACT_PROPERTY_API_URL,
                $api_key,
                json_encode($property_data)
            );
            if (isset($p_response['data'])) {
                $listing = $p_response['data'];
                foreach ($listing as $list) {
                    if ($list['is_default']) {
                        continue;
                    }
                    $key ="custom_fields_".$list['id'];
                    $mailercloud_attributes[$key] = $list['field_name'];
                }
            } else {
            }
            $tag_data = array(
                'limit' => 100,
                'page' => 1,
                'search' => '',
            );
            $tag_response= callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_TAG_LISTING_API_URL,
                $api_key,
                json_encode($tag_data)
            );
            if (isset($tag_response['data'])) {
                $listing = $tag_response['data'];
                foreach ($listing as $list) {
                    
                    $key ="tags_".$list['id'];
                    $mailercloud_tags[$key] = $list['tag_name'];
                }
                
            } else {
            }
        }
        
        if (get_option('mailercloud_selected_sync_list')) {
            $mailercloud_selected_sync_list = get_option('mailercloud_selected_sync_list');
            $selected_list_name = $mailercloud_selected_sync_list;
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key') ;
        }
        if (get_option('mailercloud_selected_sync_list_id')) {
            $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
            $list_id = $mailercloud_selected_sync_list_id;
        }
       
        
        
        if (isset($_POST['apikey_sync_list'])) {
            $nonce = (isset($_POST['mc_sync_list_key'])) ? sanitize_text_field($_POST['mc_sync_list_key']) : null;
            if (wp_verify_nonce($nonce, 'mc_sync_list_key') && current_user_can('manage_options')) {
                if (isset($_POST['list_id']) && !empty($_POST['list_id'])) {
                    $selected_wordpress_attributes = sanitize_text_field($_POST['mailercloud_attributes']);
                    $selected_mailercloud_attributes = sanitize_text_field($_POST['mailercloud_attributes']);
                    $tagsData = isset($_POST['mailercloud_tags']) ? sanitize_text_field($_POST['mailercloud_tags']) : [];
                    $selected_list_name = sanitize_text_field($_POST['selected_list_name']);
                    update_option('mailercloud_selected_sync_list_id', sanitize_text_field($_POST['list_id']));
                    update_option('mailercloud_selected_sync_list', trim($selected_list_name));
                    $contacts = [];
                    {
                        foreach ($_POST['mailercloud_attributes'] as $key => $attr) {
                            $mailercloud_attribute = sanitize_text_field($_POST['mailercloud_attributes'][$key]);
                            $wordpress_attribute = sanitize_text_field($_POST['wordpress_attributes'][$key]);
                            $attribute_mapping_arr[] = array(
                                'wordpress_attribute' => $wordpress_attribute,
                                'mailercloud_attribute' => $mailercloud_attribute,
                                'is_custom_fields' => 0,
                            );
                        }

                        if (isset($_POST['mailercloud_tags']) && is_array($_POST['mailercloud_tags'])) {
                            $tag_attribute = [];
                            foreach ($_POST['mailercloud_tags'] as $key => $name) {
                                $tag_attribute[] = stripslashes($name);
                            }
                            $attribute_mapping_arr[] = array(
                                'wordpress_attribute' => 'tags',
                                'mailercloud_attribute' => json_encode($tag_attribute),
                            );
                        }

                        if (!empty($attribute_mapping_arr)) {
                            $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                            if (is_writable($file)) {
                                if (file_put_contents($file, json_encode($attribute_mapping_arr))) {
                                    $jsonData = json_decode(file_get_contents($file), true);
                                    $message2 = 'Tag saved successfully.';
                                    $msg2 = true;
                                } else {
                                    $message2 = 'Tag is not saved';
                                    $msg2= false;
                                }
                            } else {
                                $dir ='wp-content/plugins/mailercloud/assets/json_files';
                                $message2 = 'Unable to create directory '. $dir.'. Is its parent directory writable by the server?';
                                $msg2= false;
                            }
                        }
                    }
                }
            }
        }

        require_once $this->mailercloud_get_plugin_path() . '/templates/mailercloud-subscriber-synchronisation.php';
    }
    
    /**
     * create_mailercloudp_form_listing_settings_page
     *
     * @return void
     */
    public function create_mailercloudp_form_listing_settings_page()
    {
        $mc_api_key_nonce = wp_create_nonce('mc_api_key');
        $msg = false;
        $message = '';
        $api_key = '';
        $webforms = [];
        $webforms_data = array(
            'limit' => 100,
            'page' => 1,
            'search' => '',
            'sort_field' => '',
            'sort_order' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => 'Active'
        );

        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key');
            
            $response= callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_SIGNUP_FORM_LISTING_API_URL,
                $api_key,
                json_encode($webforms_data)
            );
            if (isset($response['data'])) {
                $webforms = $response['data'];
                $message = isset($response['message'])?$response['message']:'';
                $msg = true;
            } else {
                $message = $response['message'];
                $msg = false;
            }
        }
        require_once $this->mailercloud_get_plugin_path() . '/templates/mailercloud-signup-form-listing.php';
    }
    
    
       
    /**
     * create_dynamic_shortcode_signup_form
     *
     * @param  mixed $atts
     * @param  mixed $content
     * @return void
     */
    public function create_dynamic_shortcode_signup_form($atts, $content = '')
    {
        ob_start();
        extract(
            shortcode_atts(
                array(
                'id' => '',
                'name' => ''
            ),
                $atts
            )
        );
        $webforms = [];
        $webforms_data = array(
            'limit' => 100,
            'page' => 1,
            'search' => $name,
            'sort_field' => '',
            'sort_order' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => 'Active'
        );

        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key');
            
            $response= callWpRemoteRestApi(
                'POST',
                MAILERCLOUD_SIGNUP_FORM_LISTING_API_URL,
                $api_key,
                json_encode($webforms_data)
            );
            if (isset($response['data'])) {
                $webforms = $response['data'];
                if (isset($webforms[0]['embed_code'])) {
                    $content = str_replace('<\\/script>', '</script>', $webforms[0]['embed_code']);
                }
            }
        }
        // strips all html (empty array)
        $allowed_html_strip = wp_kses_allowed_html('strip');

        // allows all most inline elements and strips all block level elements except blockquote
        $allowed_html_data = wp_kses_allowed_html('data');
             
        // very permissive: allows pretty much all HTML to pass - same as what's normally applied to the_content by default
        $allowed_html_post = wp_kses_allowed_html('post');
             
        // allows a list of HTML Entities such as
        $allowed_html_entities = wp_kses_allowed_html('entities');
        $allowed_html_scripts = array(
                'script'      => array(
                    'charset'  => array(),
                    'type' => array(),
                    'src' => array(),
                )
            );
        echo wp_kses($content, $allowed_html_scripts);
        return ob_get_clean();
    }
}
$mailercloud = new Mailercloud();
