<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Gutenberg block.
 *
 * @package mailercloud
 */

/**
 * Registers all block assets so that they can be enqueued through Gutenberg in
 * the corresponding context.
 *
 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */


/**
 * mc_forms_block_init
 *
 * @return void
 */
function mailercloud_forms_block_init()
{
    // Skip block registration if Gutenberg is not enabled/merged.
    if (!function_exists('register_block_type')) {
        return;
    }
    $dir = dirname(__FILE__);

    $index_js = 'mailercloud-forms/index.js';
    wp_register_script(
        'mc-forms-block-editor',
        plugins_url($index_js, __FILE__),
        array(
            'wp-blocks',
            'wp-i18n',
            'wp-element',
            'wp-block-editor',
            'wp-components'
        ),
        filemtime("$dir/$index_js")
    );

    $editor_css = 'mailercloud-forms/editor.css';
    wp_register_style(
        'mc-forms-block-editor',
        plugins_url($editor_css, __FILE__),
        array(),
        filemtime("$dir/$editor_css")
    );

    $style_css = 'mailercloud-forms/style.css';
    wp_register_style(
        'mc-forms-block',
        plugins_url($style_css, __FILE__),
        array(),
        filemtime("$dir/$style_css")
    );

    register_block_type('mailercloud/mc-forms', array(
        'editor_script' => 'mc-forms-block-editor',
        'editor_style' => 'mc-forms-block-editor',
        'style' => 'mc-forms-block',
        'attributes'      => [
            'selectedForm' => [
                'type' => 'string',
            ],
            'displayTitle' => [
                'type' => 'boolean',
            ],
        ],
        'render_callback' => 'mailercloud_render_dynamic_block'
    ));
}

/**
 * mailercloud_render_dynamic_block
 *
 * @param  mixed $attributes
 * @return void
 */
function mailercloud_render_dynamic_block($attributes)
{
    if (empty($attributes['selectedForm'])) {
        return '';
    }
    ob_start(); // Turn on output buffering
    $content ='';
    /* BEGIN HTML OUTPUT */
  
    if (!empty($attributes['selectedForm'])) {
        $webforms = [];
        $content='';
        $name = $attributes['selectedForm'];
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
    $output = ob_get_contents(); // collect output
    ob_end_clean(); // Turn off ouput buffer

  return $output; // Print output
}



  add_action('rest_api_init', 'create_rest_api_login_user');
    /**
     * create_rest_api_login_user
     *
     * @return void
     */
    function create_rest_api_login_user()
    {
        register_rest_route(
            'mailcloud/v1',
            '/get-signup-forms',
            array(
        'methods' => 'POST',
        'callback' => 'mailcloud_rest_api_get_signup_forms',
        'permission_callback' => '__return_true',
    )
        );
    }


/**
 * mailcloud_rest_api_get_signup_forms
 *
 * @param  mixed $request
 * @return void
 */
function mailcloud_rest_api_get_signup_forms($request)
{
    $response = [];
    $parameters = $request->get_json_params();
    $response['parameters'] = $parameters;
    $webforms = [];
    
    if (get_option('mailercloud_api_key')) {
        $api_key = get_option('mailercloud_api_key');
        $webforms = get_mailercloud_webforms(
            "POST",
            MAILERCLOUD_SIGNUP_FORM_LISTING_API_URL,
            $api_key
        );
    
        if ($webforms) {
            $response['webforms'] =$webforms;
            $response['code'] = 200;
            $response['message'] = __("Webforms is found", "mailercloud");
            return new WP_REST_Response($response, 123);
        } else {
            $response['webforms'] =$webforms;
            $response['code'] = 403;
            $response['message'] = __("Webforms is not found", "mailercloud");
        }
    }
    return $response;
}



add_action('init', 'mailercloud_forms_block_init');
