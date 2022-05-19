<?php
/**
 *
 * Class to add Mailercloud widget
 *
 *
*/
if (!defined('ABSPATH')) {
    exit;
}



/**
 * mailer_register_widget
 *
 * @return void
 */
function mailer_register_widget()
{
    register_widget('mailer_widget');
}

add_action('widgets_init', 'mailer_register_widget');
/**
 * mailer_widget
 */
class mailer_widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
                // widget ID
                'mailer_widget',
                // widget name
                __('Mailercloud Widget', ' mailercloud'),
                // widget description
                array('description' => __('Mailercloud Widget', 'mailercloud'))
        );
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title']);
        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        if (!empty($instance['mc_form'])) {
            $webforms = [];
            $content='';
            $name = $instance['mc_form'];
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
                if (!empty($response['data'])) {
                    $webforms = $response['data'];
                    $content = str_replace('<\\/script>', '</script>', $webforms[0]['embed_code']);
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
        }
        echo $args['after_widget'];
    }
        
    /**
     * form
     *
     * @param  mixed $instance
     * @return void
     */
    public function form($instance)
    {
        $mc_form ='';
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = __('Mailercloud Form', 'mailercloud');
        }
        if (isset($instance['mc_form'])) {
            $mc_form = $instance['mc_form'];
        }
           
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
                $msg = true;
            } else {
                $message = $response['message'];
                $msg = false;
            }
        } ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id('mc_form'); ?>"><?php echo __('Choose a Form from list', 'mailercloud'); ?></label>

            <select  class="widefat" name="<?php echo $this->get_field_name('mc_form'); ?>" value="<?php echo esc_attr($mc_form); ?>"  required>
            <option value="">
                Select a Form
            </option>
                <?php foreach ($webforms as $id => $webform) : ?>
                <option value="<?php echo esc_attr($webform['name']); ?>"  <?php echo (esc_attr($mc_form) == $webform['name'])?'selected=selected':''; ?> >
                <?php echo esc_html($webform['name']); ?>
            </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    
    /**
     * update
     *
     * @param  mixed $new_instance
     * @param  mixed $old_instance
     * @return void
     */
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['mc_form'] = (!empty($new_instance['mc_form'])) ? strip_tags($new_instance['mc_form']) : '';
        
        return $instance;
    }
}
