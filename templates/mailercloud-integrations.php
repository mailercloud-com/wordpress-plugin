<?php
/**
 * Integrations admin page — connect third-party form plugins to Mailercloud.
 *
 * Reuses the Contact Sync visual design: white cards, the .costs_main / .word_divs
 * / .action_btns mapping rows with green "+" / red "-" buttons, and .mc-dropdown
 * dropdowns (with a search box) for the List and Tags. Each connector is a card
 * whose header (name + status) toggles its mapping panel. No "Sync" box.
 *
 * In-scope variables (set by create_mailercloud_integrations_page):
 *   $connectors  Mc_Connector_Base[]
 *   $lists       array id => name
 *   $custom_fields array id => field_name
 *   $tags        array id => tag_name
 *   $api_key     string
 *   $mc_connector_nonce string
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

$mc_descriptions = array(
    'cf7'        => __('Subscribes people from your Contact Form 7 forms.', 'mailercloud'),
    'wpforms'    => __('Subscribes visitors from your WPForms forms.', 'mailercloud'),
    'elementor'  => __('Subscribes visitors from your Elementor forms.', 'mailercloud'),
    'gravity'    => __('Subscribes visitors from your Gravity Forms forms.', 'mailercloud'),
    'ninja'      => __('Subscribes people from your Ninja Forms forms.', 'mailercloud'),
    'formidable' => __('Subscribes people from your Formidable forms.', 'mailercloud'),
);

if (! function_exists('mc_render_attr_options')) {
    function mc_render_attr_options($custom_fields, $selected, $email_only = false)
    {
        if ($email_only) {
            echo '<option value="email" selected>' . esc_html__('Email (required)', 'mailercloud') . '</option>';
            return;
        }
        $std = array('email' => __('Email (required)', 'mailercloud'), 'name' => __('First name', 'mailercloud'), 'last_name' => __('Last name', 'mailercloud'));
        foreach ($std as $val => $label) {
            echo '<option value="' . esc_attr($val) . '" ' . selected($selected, $val, false) . '>' . esc_html($label) . '</option>';
        }
        foreach ($custom_fields as $cfid => $cfname) {
            echo '<option value="custom_fields_' . esc_attr($cfid) . '" ' . selected($selected, 'custom_fields_' . $cfid, false) . '>' . esc_html($cfname) . '</option>';
        }
    }
}
?>
<div class="wrap mailercloud-wrap subs-p mc-int-wrap">
    <h1 class="header_sync"><?php esc_html_e('Integrations', 'mailercloud'); ?></h1>
    <p class="mc-int-intro"><?php esc_html_e('Send submissions from the form plugins you already use straight into a Mailercloud list — no third-party connector needed. Click a connector to set it up.', 'mailercloud'); ?></p>

    <?php if (empty($api_key)) : ?>
        <div class="notice notice-warning inline"><p>
            <?php
            echo wp_kses_post(sprintf(
                /* translators: %s: settings page URL */
                __('Connect your Mailercloud API key on the <a href="%s">Settings</a> page first.', 'mailercloud'),
                esc_url(admin_url('admin.php?page=mailercloud-settings-page'))
            ));
            ?>
        </p></div>
        <?php return; ?>
    <?php endif; ?>

    <?php foreach ($connectors as $connector) :
        $slug    = $connector->slug();
        $active  = $connector->is_active();
        $config  = $connector->get_config();
        $enabled = ! empty($config['enabled']);
        $desc    = isset($mc_descriptions[$slug]) ? $mc_descriptions[$slug] : '';

        if (! $active) {
            $badge_label = __('Not installed', 'mailercloud');
            $badge_css   = 'color:#646970;background:#f0f0f1;';
        } elseif ($enabled) {
            $badge_label = __('Active', 'mailercloud');
            $badge_css   = 'color:#fff;background:#00a32a;';
        } else {
            $badge_label = __('Inactive', 'mailercloud');
            $badge_css   = 'color:#fff;background:#646970;';
        }

        $email_key = '';
        $other_rows = array();
        $selected_tags = array();
        if (! empty($config['mapping'])) {
            foreach ($config['mapping'] as $row) {
                if (! isset($row['wordpress_attribute'], $row['mailercloud_attribute'])) {
                    continue;
                }
                if ($row['wordpress_attribute'] === 'tags') {
                    $decoded = json_decode($row['mailercloud_attribute'], true);
                    if (is_array($decoded)) {
                        $selected_tags = array_map('strval', $decoded);
                    }
                } elseif ($row['mailercloud_attribute'] === 'email') {
                    $email_key = $row['wordpress_attribute'];
                } else {
                    $other_rows[] = $row;
                }
            }
        }
        $sel_list_label = (! empty($config['list_id']) && isset($lists[$config['list_id']])) ? $lists[$config['list_id']] : '';
        ?>
        <div class="mc-int-card <?php echo $active ? 'mc-active' : 'mc-not-installed'; ?>" data-slug="<?php echo esc_attr($slug); ?>">
            <div class="mc-int-head"<?php echo $active ? ' role="button" tabindex="0"' : ''; ?>>
                <h2>
                    <?php echo esc_html($connector->label()); ?>
                    <span class="mc-badge" style="<?php echo esc_attr($badge_css); ?>"><?php echo esc_html($badge_label); ?></span>
                </h2>
                <?php if ($active) : ?><span class="mc-int-caret dashicons dashicons-arrow-down-alt2"></span><?php endif; ?>
            </div>
            <p class="mc-desc" style="margin:6px 0 0;"><?php echo esc_html($desc); ?></p>

            <?php if ($active) : ?>
            <div class="mc-int-body" style="display:none;">
                <form class="mc-connector-form" data-slug="<?php echo esc_attr($slug); ?>">
                    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($mc_connector_nonce); ?>" />

                    <div class="mc-field-block">
                        <label><?php esc_html_e('Status', 'mailercloud'); ?></label>
                        <label style="font-weight:400;"><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Send submissions from this form plugin to Mailercloud', 'mailercloud'); ?></label>
                    </div>

                    <div class="mc-field-block">
                        <label><?php esc_html_e('Add contacts to list', 'mailercloud'); ?></label>
                        <div class="mc-dropdown mc-listdrop">
                            <a href="#" class="mc-dropdown-btn mc-listdrop-btn"><?php echo $sel_list_label ? esc_html($sel_list_label) : esc_html__('Select a list', 'mailercloud'); ?></a>
                            <div class="dropdown-content mc-listdrop-content">
                                <input type="text" class="mc-drop-search mc-list-search" placeholder="<?php esc_attr_e('Search lists…', 'mailercloud'); ?>" autocomplete="off" />
                                <div class="mc-drop-options mc-list-options">
                                    <?php foreach ($lists as $lid => $lname) : ?>
                                        <label class="mc-opt mc-list-opt<?php echo ((string) $config['list_id'] === (string) $lid) ? ' active' : ''; ?>" data-id="<?php echo esc_attr($lid); ?>"><?php echo esc_html($lname); ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" name="list_id" class="mc-list-id" value="<?php echo esc_attr($config['list_id']); ?>" />
                        </div>
                    </div>

                    <div class="mc-field-block">
                        <h3><?php esc_html_e('Field mapping', 'mailercloud'); ?></h3>
                        <p class="mc-desc"><?php esc_html_e('Map your form field keys to Mailercloud fields. Email is required.', 'mailercloud'); ?></p>
                        <div class="attribute_header">
                            <label class="wordpress_attributes"><?php esc_html_e('Form field key', 'mailercloud'); ?></label>
                            <label class="mailercloud_attributes"><?php esc_html_e('Mailercloud field', 'mailercloud'); ?></label>
                        </div>
                        <div class="mc-map-list">
                            <div class="costs_main mc-map-row mc-email-row">
                                <div class="input-group repeat_div">
                                    <div class="word_divs"><input type="text" class="mc-field-key" value="<?php echo esc_attr($email_key); ?>" placeholder="<?php esc_attr_e('e.g. your-email', 'mailercloud'); ?>" required /></div>
                                    <div class="word_divs"><select class="mc-field-attr"><?php mc_render_attr_options($custom_fields, 'email', true); ?></select></div>
                                    <div class="action_btns"></div>
                                </div>
                            </div>
                            <?php foreach ($other_rows as $row) : ?>
                            <div class="costs_main mc-map-row">
                                <div class="input-group repeat_div">
                                    <div class="word_divs"><input type="text" class="mc-field-key" value="<?php echo esc_attr($row['wordpress_attribute']); ?>" placeholder="<?php esc_attr_e('e.g. your-name', 'mailercloud'); ?>" /></div>
                                    <div class="word_divs"><select class="mc-field-attr"><?php mc_render_attr_options($custom_fields, $row['mailercloud_attribute']); ?></select></div>
                                    <div class="action_btns"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="costs_main mc-map-row mc-map-template" style="display:none;">
                            <div class="input-group repeat_div">
                                <div class="word_divs"><input type="text" class="mc-field-key" placeholder="<?php esc_attr_e('e.g. your-name', 'mailercloud'); ?>" /></div>
                                <div class="word_divs"><select class="mc-field-attr"><?php mc_render_attr_options($custom_fields, 'name'); ?></select></div>
                                <div class="action_btns"></div>
                            </div>
                        </div>
                        <p class="mc-new-prop-wrap"><a href="#" class="mc-new-property"><?php esc_html_e('Create New Property', 'mailercloud'); ?></a></p>
                    </div>

                    <div class="mc-field-block">
                        <label><?php esc_html_e('Apply tags', 'mailercloud'); ?></label>
                        <div class="mc-dropdown mc-tagdrop">
                            <a href="#" class="mc-dropdown-btn mc-tagdrop-btn"><?php esc_html_e('Choose Tags', 'mailercloud'); ?></a>
                            <div class="dropdown-content mc-tagdrop-content">
                                <input type="text" class="mc-drop-search mc-tag-search" placeholder="<?php esc_attr_e('Search tags…', 'mailercloud'); ?>" autocomplete="off" />
                                <div class="mc-drop-options mc-tag-options">
                                    <?php foreach ($tags as $tid => $tname) : ?>
                                        <label><input type="checkbox" class="mc-tag-cb" value="<?php echo esc_attr($tid); ?>" data-name="<?php echo esc_attr($tname); ?>" <?php checked(in_array((string) $tid, $selected_tags, true)); ?> /> <?php echo esc_html($tname); ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mc-save-feedback" style="display:none;"></div>
                    <input type="submit" class="button mc-save-connector" value="<?php esc_attr_e('Save changes', 'mailercloud'); ?>" />
                </form>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Create New Property modal (reuses the existing create-property AJAX flow) -->
    <div id="mc-int-prop-modal" class="modal">
        <div class="modal-content mailer_popup">
            <div class="modal-header"><span class="close mc-prop-close">&times;</span></div>
            <h2><?php esc_html_e('Create new property', 'mailercloud'); ?></h2>
            <form class="mc-prop-form">
                <?php wp_nonce_field('mailercloud_admin_ajax', '_ajax_nonce'); ?>
                <label><?php esc_html_e('Attribute name', 'mailercloud'); ?>*</label><br />
                <input type="text" class="mc-prop-name" name="name" required /><br />
                <label><?php esc_html_e('Field type', 'mailercloud'); ?>*</label><br />
                <select class="mc-prop-type" name="type" required>
                    <option value="text"><?php esc_html_e('Text (Text field can store upto 100 characters)', 'mailercloud'); ?></option>
                    <option value="number"><?php esc_html_e('Number (Number field can store upto 30 characters)', 'mailercloud'); ?></option>
                    <option value="textarea"><?php esc_html_e('Text Area (Text area field can store upto 500 characters)', 'mailercloud'); ?></option>
                    <option value="date"><?php esc_html_e('Date', 'mailercloud'); ?></option>
                </select><br />
                <label><?php esc_html_e('Description', 'mailercloud'); ?></label><br />
                <input type="text" class="mc-prop-desc" name="description" /><br />
                <div class="mc-prop-feedback" style="display:none;"></div>
                <input type="submit" class="button mc-prop-create" value="<?php esc_attr_e('Create', 'mailercloud'); ?>" />
            </form>
        </div>
    </div>

    <!-- Full-screen loader (same as the Contact Sync page) -->
    <div class="loader_mailercloud">
        <div class="overlay-img">
            <img src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/images/loader.gif'); ?>" alt="" />
        </div>
    </div>
</div>
