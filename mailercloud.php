<?php
/**
 * Plugin Name:     Mailercloud - Integrate webforms and synchronize website contacts
 * Plugin URI:      https://app.mailercloud.com/
 * Description:     Mailercloud Wordpress plugin.
 * Author:          Mailercloud
 * Author URI:      https://mailercloud.com/
 * Text Domain:     mailercloud
 * Domain Path:     /languages
 * Version:         1.0
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
    public $version = '1.0';
    /* Member variables */
    public $mailercloud_api_key;
    public $default_mapping_array= [];

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
            "wp_ajax_nopriv_mailercloud_create_new_property",
            array($this, "mailercloud_create_new_property")
        );
       

        add_action(
            "wp_ajax_mailercloud_sync_contacts_now_ajax",
            array($this, "mailercloud_sync_contacts_now_ajax")
        );
        add_action(
            "wp_ajax_nopriv_mailercloud_sync_contacts_now_ajax",
            array($this, "mailercloud_sync_contacts_now_ajax")
        );
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
        $page = isset($_GET["page"]) ? sanitize_text_field($_GET["page"]) : "";

        // Add condition for css & js include for admin page
        if ($page != ('mailercloud-settings-page' || 'mailercloud-subscriber-synchronisation' || 'mailercloud-signup-form-listing')) {
            return;
        }
        wp_register_style(
            'mailercloud-admin',
            plugins_url('assets/css/mailercloud-style.css', __FILE__),
            array(),
            $this->version
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
        require_once $this->mailercloud_get_plugin_path() . '/widgets/mailercloud_widget.php';
        require_once $this->mailercloud_get_plugin_path() . '/blocks/mailercloud-forms.php';
    }
    
    /**
     * add_mailercloud_admin_menu
     *
     * @return void
     */
    public function add_mailercloud_admin_menu()
    {
        add_menu_page(
            __('MailerCloud', 'mailercloud'),
            __('MailerCloud', 'mailercloud'),
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
    }
    



    public function mailercloud_create_new_property()
    {
        $response =[];
        if (isset($_POST['name'])) {
            if (get_option('mailercloud_api_key')) {
                $api_key = get_option('mailercloud_api_key');
                $this->mailercloud_api_key = $api_key;
            }
            if ($api_key) {
                $data = array(
                    'description' =>sanitize_text_field($_POST['description']),
                    'name' => sanitize_text_field($_POST['name']),
                    'type' => sanitize_text_field($_POST['type']),
                );
                $response= callWpRemoteRestApi(
                    'POST',
                    MAILERCLOUD_CREATE_NEW_PROPERTY_API_URL,
                    $api_key,
                    json_encode($data)
                );
                if (isset($response['id'])) {
                    $response['name'] =sanitize_text_field($_POST['name']);
                    $response['message'] ='new property is created successfully.';
                } else {
                }
            }
        }
        echo json_encode($response);
        die();
    }

    public function mailercloud_sync_contacts_now_ajax()
    {
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
            $users = get_users_by_role(
                'subscriber',
                'user_nicename',
                'ASC'
            );
            if (!empty($users)) {
                if ($list_id) {
                    if ($api_key) {
                        $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                        $attribute_mapping_arr = json_decode(file_get_contents($file), true);
                        $contacts = [];
                        if (!empty($attribute_mapping_arr)) {
                            $temp = [];
                            $temp_final = [];
                            $user_count = count($users);
                            $limit = 49;
                            $page = 1;
                            // How many pages will there be
                            $pages = ceil($user_count / $limit);
                            $offset = ($page - 1)  * $limit;
                              
                            // Some information to display to the user
                            $start = $offset + 1;
                            $end = min(($offset + $limit), $user_count);
                            $contactss =[];
                            $contacts_data=[];
                            $total_inserted =0;
                            $total_skipped =0;
                            $total_submitted =0;
                            $total_updated =0;
                            foreach ($users as $user) {
                                $temp_custom =[];
                                foreach ($attribute_mapping_arr as $row) {
                                    $find="custom_fields_";
                                    if (strpos($row['mailercloud_attribute'], $find) !== false) {
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
                                    $temp_final[] = $temp;
                                }

                                if ($start == $end) {
                                    $page = $page + 1;
                                    $offset = ($page - 1)  * $limit;
                                    $start = $offset + 1;
                                    $end = min(($offset + $limit), $user_count);
                                    $contactss = $temp_final;
                                    $contacts_data['contacts'] = $contactss;
                                    $contacts_data['list_id'] = $list_id;
                                    $response= callWpRemoteRestApi(
                                        'POST',
                                        MAILERCLOUD_SUBSCRIBER_SYNC_CONTACTS_BATCH_API_URL,
                                        $api_key,
                                        json_encode($contacts_data)
                                    );
                                      
                                    if (isset($response['data'])) {
                                        $user_data = $response['data'];
                                        $total_inserted += $response['data']['inserted'];
                                        $total_skipped += $response['data']['skipped'];
                                        $total_submitted += $response['data']['submitted'];
                                        $total_updated += $response['data']['updated'];
                                        $msg = true;
                                        foreach ($users as $user) {
                                            $userData = get_user_by('email', $user['Email']);
                                        }
                                    } else {
                                        $message = $response['message'];
                                        if (isset($response['errors'])) {
                                            $errors = $response['errors'];
                                        }
                                        $msg = false;
                                    }
                                    $contactss =[];
                                    $contacts_data=[];
                                    $temp_final =[];
                                    usleep(400000);
                                } else {
                                    $start++;
                                }
                            }
                            $message = 'Contact synchronization has been completed';
                            $user_data['inserted']= $total_inserted ;
                            $user_data['skipped']  =  $total_skipped;
                            $user_data['submitted'] = $total_submitted;
                            $user_data['updated'] =    $total_updated;
                            $response['message'] =  $message;
                            $response['status'] =  $status;
                            $response['data'] = $user_data;
                        }
                    }
                } else {
                    $message='Please choose the Mailcloud list to sync with.';
                    $response['status'] =  0;
                    $response['message'] =  $message;
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
        if (get_option('mailercloud_api_key')) {
            update_option('mailercloud_api_key', '');
            update_option('mailercloud_selected_sync_list_id', '');
        }
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
                            if (strpos($row['mailercloud_attribute'], $find) !== false) {
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
                            if (strpos($row['mailercloud_attribute'], $find) !== false) {
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
                        MAILERCLOUD_SUBSCRIBER_SYNC_SINGLE_CONTACT_UPDATE_API_URL . $user_email,
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
    public function sync_contact_every_five_minutes_event()
    {
        $list_id ='';
        $api_key='';
        $users=[];
        $attribute_mapping_arr=[];
        if (get_option('mailercloud_selected_sync_list_id')) {
            $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
            $list_id = $mailercloud_selected_sync_list_id;
        }
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key') ;
        }
        $users = get_users_by_role(
            'subscriber',
            'user_nicename',
            'ASC'
        );
        if (!empty($users)) {
            if ($list_id) {
                if ($api_key) {
                    $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                    $attribute_mapping_arr = json_decode(file_get_contents($file), true);
                    $contacts = [];
                    if (!empty($attribute_mapping_arr)) {
                        $temp = [];
                        $temp_final = [];
                        foreach ($users as $user) {
                            $temp_custom =[];
                            foreach ($attribute_mapping_arr as $row) {
                                $find="custom_fields_";
                                if (strpos($row['mailercloud_attribute'], $find) !== false) {
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
                                $temp_final[] = $temp;
                            }
                        }
                   
                        $contacts = $temp_final;
               
                        $contact_data['contacts'] = $contacts;
                        $contact_data['list_id'] = $list_id;
                        
                        $response= callWpRemoteRestApi(
                            'POST',
                            MAILERCLOUD_SUBSCRIBER_SYNC_CONTACTS_BATCH_API_URL,
                            $api_key,
                            json_encode($contact_data)
                        );
                        if (isset($response['data'])) {
                            $user_data = $response['data'];
                            foreach ($users as $user) {
                                $userData = get_user_by('email', $user['Email']);
                                // $updated = update_user_meta($userData->ID, 'mailercloud_is_synched',true);
                            }
                        } else {
                            $message = $response['message'];
                            $msg = false;
                        }
                    }
                }
            }
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
            if (wp_verify_nonce($mc_api_logout_nonce, 'mc_api_logout')) {
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
            if (wp_verify_nonce($nonce, 'mc_api_key')) {
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
        $lists = [];
        $list_id ='';
        $errors= [];
        $user_count = 0;
        $users = get_users_by_role(
            'subscriber',
            'user_nicename',
            'ASC'
        );
     
        $user_count = count($users);
        $wordpress_attributes = [];
        $selected_wordpress_attributes = [];
        $selected_mailercloud_attributes = [];
        $selected_list_name = '';
        $mailercloud_attributes_properties =[];
       
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
        }
        
        if (get_option('mailercloud_selected_sync_list')) {
            $mailercloud_selected_sync_list = get_option('mailercloud_selected_sync_list');
            $selected_list_name = $mailercloud_selected_sync_list;
        }
        $users = get_users_by_role(
            'subscriber',
            'user_nicename',
            'ASC'
        );
        if (get_option('mailercloud_api_key')) {
            $api_key = get_option('mailercloud_api_key') ;
        }
        if (get_option('mailercloud_selected_sync_list_id')) {
            $mailercloud_selected_sync_list_id = get_option('mailercloud_selected_sync_list_id');
            $list_id = $mailercloud_selected_sync_list_id;
        }
       
        
        
        if (isset($_POST['apikey_sync_list'])) {
            $nonce = (isset($_POST['mc_sync_list_key'])) ? sanitize_text_field($_POST['mc_sync_list_key']) : null;
            if (wp_verify_nonce($nonce, 'mc_sync_list_key')) {
                if (isset($_POST['list_id']) && !empty($_POST['list_id'])) {
                    $selected_wordpress_attributes = sanitize_text_field($_POST['mailercloud_attributes']);
                    $selected_mailercloud_attributes = sanitize_text_field($_POST['mailercloud_attributes']);
                    $selected_list_name = sanitize_text_field($_POST['selected_list_name']);
                    update_option('mailercloud_selected_sync_list_id', sanitize_text_field($_POST['list_id']));
                    update_option('mailercloud_selected_sync_list', trim($selected_list_name));
                    $contacts = [];
                    {
                        foreach ($_POST['mailercloud_attributes'] as $key => $attr) {
                            $mailercloud_attribute = sanitize_text_field($_POST['mailercloud_attributes'][$key]);
                            $wordpress_attribute =sanitize_text_field($_POST['wordpress_attributes'][$key]);
                            $attribute_mapping_arr[] = array(
                                'wordpress_attribute' => $wordpress_attribute,
                                'mailercloud_attribute' => $mailercloud_attribute,
                                'is_custom_fields' => 0,
                            );
                        }
                        if (!empty($attribute_mapping_arr)) {
                            $file = $this->mailercloud_get_plugin_path() . '/assets/json_files/attribute_mapping.json';
                            if (is_writable($file)) {
                                if (file_put_contents($file, json_encode($attribute_mapping_arr))) {
                                    $jsonData = json_decode(file_get_contents($file), true);
                                    $message2 = 'Attribute mapping is saved successfully.';
                                    $msg2 = true;
                                } else {
                                    $message2 = 'Attribute mapping is not saved';
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
