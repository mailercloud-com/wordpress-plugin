<?php
/**
 * Integrations admin page — two views:
 *   - list   : connector cards; clicking one opens its configure page (no inline toggle).
 *   - config : the selected connector's form-mappings ("feeds"), each a collapsible card.
 *
 * In-scope vars (set by create_mailercloud_integrations_page):
 *   $connectors, $lists, $custom_fields, $tags, $api_key, $mc_connector_nonce,
 *   $mc_view ('list'|'config'), $mc_connector (selected connector or null),
 *   $connector_forms[slug] => [ {id,title} ], $connector_fields[slug] => [ form_id => [ {key,label} ] ]
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

if (! function_exists('mc_render_field_key')) {
    function mc_render_field_key($form_fields, $selected, $use_dropdown, $required = false)
    {
        // NOTE: no HTML "required" attribute — a required control inside a hidden feed
        // template is not focusable and blocks the whole form's native submit. The Email
        // requirement is enforced in JS at save time instead.
        if (! $use_dropdown) {
            printf('<input type="text" class="mc-field-key" value="%s" placeholder="%s" />', esc_attr($selected), esc_attr__('e.g. your-email', 'mailercloud'));
            return;
        }
        echo '<select class="mc-field-key">';
        echo '<option value="">' . esc_html__('— Select form field —', 'mailercloud') . '</option>';
        $found = false;
        foreach ($form_fields as $f) {
            $key   = isset($f['key']) ? $f['key'] : '';
            $label = isset($f['label']) && $f['label'] !== '' ? $f['label'] : $key;
            $sel   = selected($selected, $key, false);
            if ($sel !== '') {
                $found = true;
            }
            echo '<option value="' . esc_attr($key) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        if ($selected !== '' && ! $found) {
            echo '<option value="' . esc_attr($selected) . '" selected>' . esc_html($selected) . '</option>';
        }
        echo '</select>';
    }
}

if (! function_exists('mc_render_feed')) {
    /** Render one collapsible feed (form-mapping) block. */
    function mc_render_feed($feed, $forms, $form_fields, $custom_fields, $tags, $lists, $use_dropdown, $is_template = false)
    {
        $enabled    = ! empty($feed['enabled']);
        $form_id    = isset($feed['form_id']) ? $feed['form_id'] : '';
        $list_id    = isset($feed['list_id']) ? $feed['list_id'] : '';
        $email_key  = '';
        $other_rows = array();
        $sel_tags   = array();
        if (! empty($feed['mapping']) && is_array($feed['mapping'])) {
            foreach ($feed['mapping'] as $row) {
                if (! isset($row['wordpress_attribute'], $row['mailercloud_attribute'])) {
                    continue;
                }
                if ($row['wordpress_attribute'] === 'tags') {
                    $decoded = json_decode($row['mailercloud_attribute'], true);
                    if (is_array($decoded)) {
                        $sel_tags = array_map('strval', $decoded);
                    }
                } elseif ($row['mailercloud_attribute'] === 'email') {
                    $email_key = $row['wordpress_attribute'];
                } else {
                    $other_rows[] = $row;
                }
            }
        }
        $form_label = '';
        foreach ($forms as $f) {
            if ((string) $f['id'] === (string) $form_id) {
                $form_label = $f['title'];
            }
        }
        $list_label = ($list_id !== '' && isset($lists[$list_id])) ? $lists[$list_id] : '';
        $title      = $form_label ? $form_label : __('New form mapping', 'mailercloud');
        ?>
        <div class="mc-feed<?php echo $is_template ? ' mc-feed-template' : ''; ?>"<?php echo $is_template ? ' style="display:none;"' : ''; ?>>
            <input type="hidden" class="mc-feed-id" value="<?php echo esc_attr(isset($feed['id']) ? $feed['id'] : ''); ?>" />
            <div class="mc-feed-head" role="button" tabindex="0">
                <span class="mc-feed-title"><?php echo esc_html($title); ?></span>
                <span class="mc-feed-head-right">
                    <label class="mc-feed-enable"><input type="checkbox" class="mc-feed-enabled" <?php checked($enabled); ?> /> <?php esc_html_e('Enabled', 'mailercloud'); ?></label>
                    <a href="#" class="mc-remove-feed" title="<?php esc_attr_e('Remove this form mapping', 'mailercloud'); ?>"><?php esc_html_e('Remove', 'mailercloud'); ?></a>
                    <span class="mc-feed-caret dashicons dashicons-arrow-down-alt2"></span>
                </span>
            </div>
            <div class="mc-feed-body">
                <?php if ($use_dropdown) : ?>
                <div class="mc-field-block">
                    <label><?php esc_html_e('Select form', 'mailercloud'); ?></label>
                    <div class="mc-dropdown mc-formdrop">
                        <a href="#" class="mc-dropdown-btn mc-formdrop-btn"><?php echo $form_label ? esc_html($form_label) : esc_html__('Select a form', 'mailercloud'); ?></a>
                        <div class="dropdown-content mc-formdrop-content">
                            <input type="text" class="mc-drop-search mc-form-search" placeholder="<?php esc_attr_e('Search forms…', 'mailercloud'); ?>" autocomplete="off" />
                            <div class="mc-drop-options mc-form-options">
                                <?php foreach ($forms as $f) : ?>
                                    <label class="mc-opt mc-form-opt<?php echo ((string) $form_id === (string) $f['id']) ? ' active' : ''; ?>" data-id="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['title']); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" class="mc-form-id" value="<?php echo esc_attr($form_id); ?>" />
                    </div>
                </div>
                <?php endif; ?>

                <div class="mc-field-block">
                    <label><?php esc_html_e('Add contacts to list', 'mailercloud'); ?></label>
                    <div class="mc-dropdown mc-listdrop">
                        <a href="#" class="mc-dropdown-btn mc-listdrop-btn"><?php echo $list_label ? esc_html($list_label) : esc_html__('Select a list', 'mailercloud'); ?></a>
                        <div class="dropdown-content mc-listdrop-content">
                            <input type="text" class="mc-drop-search mc-list-search" placeholder="<?php esc_attr_e('Search lists…', 'mailercloud'); ?>" autocomplete="off" />
                            <div class="mc-drop-options mc-list-options">
                                <?php foreach ($lists as $lid => $lname) : ?>
                                    <label class="mc-opt mc-list-opt<?php echo ((string) $list_id === (string) $lid) ? ' active' : ''; ?>" data-id="<?php echo esc_attr($lid); ?>"><?php echo esc_html($lname); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" class="mc-list-id" value="<?php echo esc_attr($list_id); ?>" />
                    </div>
                </div>

                <div class="mc-field-block">
                    <h3><?php esc_html_e('Field mapping', 'mailercloud'); ?></h3>
                    <p class="mc-desc"><?php esc_html_e('Map each form field to a Mailercloud field. Email is required.', 'mailercloud'); ?></p>
                    <div class="attribute_header">
                        <label class="wordpress_attributes"><?php esc_html_e('Form field', 'mailercloud'); ?></label>
                        <label class="mailercloud_attributes"><?php esc_html_e('Mailercloud field', 'mailercloud'); ?></label>
                    </div>
                    <div class="mc-map-list">
                        <div class="costs_main mc-map-row mc-email-row">
                            <div class="input-group repeat_div">
                                <div class="word_divs"><?php mc_render_field_key($form_fields, $email_key, $use_dropdown, true); ?></div>
                                <div class="word_divs"><select class="mc-field-attr"><?php mc_render_attr_options($custom_fields, 'email', true); ?></select></div>
                                <div class="action_btns"></div>
                            </div>
                        </div>
                        <?php foreach ($other_rows as $row) : ?>
                        <div class="costs_main mc-map-row">
                            <div class="input-group repeat_div">
                                <div class="word_divs"><?php mc_render_field_key($form_fields, $row['wordpress_attribute'], $use_dropdown); ?></div>
                                <div class="word_divs"><select class="mc-field-attr"><?php mc_render_attr_options($custom_fields, $row['mailercloud_attribute']); ?></select></div>
                                <div class="action_btns"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="costs_main mc-map-row mc-map-template" style="display:none;">
                        <div class="input-group repeat_div">
                            <div class="word_divs"><?php mc_render_field_key($form_fields, '', $use_dropdown); ?></div>
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
                                    <label><input type="checkbox" class="mc-tag-cb" value="<?php echo esc_attr($tname); ?>" <?php checked(in_array((string) $tname, $sel_tags, true)); ?> /> <?php echo esc_html($tname); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mc-feed-save">
                    <div class="mc-save-feedback" style="display:none;"></div>
                    <a href="#" class="button mc-save-connector mc-save-feed"><?php esc_html_e('Save changes', 'mailercloud'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }
}
?>
<div class="wrap mailercloud-wrap subs-p mc-int-wrap">

<?php if ('config' === $mc_view) :
    $slug         = $mc_connector->slug();
    $forms        = isset($connector_forms[$slug]) ? $connector_forms[$slug] : array();
    $fields_map   = isset($connector_fields[$slug]) ? $connector_fields[$slug] : array();
    $use_dropdown = ! empty($forms);
    $feeds        = $mc_connector->get_feeds();
    // Form-picker connectors: when there are no mappings, show an empty state + the
    // "Add form mapping" button (no auto-blank feed). Connectors without a form picker
    // (e.g. Elementor) keep a single free-text mapping.
    if (empty($feeds) && ! $use_dropdown) {
        $feeds = array(array('enabled' => true, 'form_id' => '', 'list_id' => '', 'mapping' => array()));
    }
    ?>
    <p class="mc-int-back"><a href="<?php echo esc_url(admin_url('admin.php?page=mailercloud-integrations')); ?>">&larr; <?php esc_html_e('Back to Integrations', 'mailercloud'); ?></a></p>
    <h1 class="header_sync"><?php echo esc_html($mc_connector->label()); ?></h1>
    <p class="mc-int-intro"><?php esc_html_e('Map this form plugin to Mailercloud. Each block below connects one form to a list. Add more forms with “Add form mapping”.', 'mailercloud'); ?></p>

    <?php if (empty($api_key)) : ?>
        <div class="notice notice-warning inline"><p>
            <?php echo wp_kses_post(sprintf(/* translators: %s: settings URL */ __('Connect your Mailercloud API key on the <a href="%s">Settings</a> page first.', 'mailercloud'), esc_url(admin_url('admin.php?page=mailercloud-settings-page')))); ?>
        </p></div>
    <?php else : ?>
    <div class="mc-int-card mc-config-card">
        <form class="mc-connector-form" data-slug="<?php echo esc_attr($slug); ?>">
            <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr($mc_connector_nonce); ?>" />
            <div class="mc-feeds">
                <?php foreach ($feeds as $feed) :
                    $ffid    = isset($feed['form_id']) ? (string) $feed['form_id'] : '';
                    $ffields = ($ffid !== '' && isset($fields_map[$ffid])) ? $fields_map[$ffid] : array();
                    mc_render_feed($feed, $forms, $ffields, $custom_fields, $tags, $lists, $use_dropdown, false);
                endforeach; ?>
            </div>
            <?php if ($use_dropdown) : ?>
            <p class="mc-feeds-empty"<?php echo ! empty($feeds) ? ' style="display:none;"' : ''; ?>><?php esc_html_e('No form mappings yet. Add one to connect a form to Mailercloud.', 'mailercloud'); ?></p>
            <?php mc_render_feed(array('enabled' => true, 'form_id' => '', 'list_id' => '', 'mapping' => array()), $forms, array(), $custom_fields, $tags, $lists, $use_dropdown, true); ?>
            <p><a href="#" class="button mc-add-feed">+ <?php esc_html_e('Add form mapping', 'mailercloud'); ?></a></p>
            <?php endif; ?>
        </form>
        <div class="loader_mailercloud mc-card-loader">
            <div class="overlay-img"><img src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/images/loader.gif'); ?>" alt="" /></div>
        </div>
    </div>
    <?php endif; ?>

<?php else : // list view ?>
    <h1 class="header_sync"><?php esc_html_e('Integrations', 'mailercloud'); ?></h1>
    <p class="mc-int-intro"><?php esc_html_e('Send submissions from the form plugins you already use straight into a Mailercloud list — no third-party connector needed. Click a connector to set it up.', 'mailercloud'); ?></p>

    <?php if (empty($api_key)) : ?>
        <div class="notice notice-warning inline"><p>
            <?php echo wp_kses_post(sprintf(/* translators: %s: settings URL */ __('Connect your Mailercloud API key on the <a href="%s">Settings</a> page first.', 'mailercloud'), esc_url(admin_url('admin.php?page=mailercloud-settings-page')))); ?>
        </p></div>
    <?php endif; ?>

    <?php foreach ($connectors as $connector) :
        $slug   = $connector->slug();
        $active = $connector->is_active();
        $desc   = isset($mc_descriptions[$slug]) ? $mc_descriptions[$slug] : '';
        $enabled_any = false;
        foreach ($connector->get_feeds() as $fd) {
            if (! empty($fd['enabled'])) {
                $enabled_any = true;
            }
        }
        if (! $active) {
            $badge_label = __('Not installed', 'mailercloud');
            $badge_css   = 'color:#646970;background:#f0f0f1;';
        } elseif ($enabled_any) {
            $badge_label = __('Active', 'mailercloud');
            $badge_css   = 'color:#fff;background:#00a32a;';
        } else {
            $badge_label = __('Inactive', 'mailercloud');
            $badge_css   = 'color:#fff;background:#646970;';
        }
        $card_inner = '<div class="mc-int-head"><h2>' . esc_html($connector->label())
            . ' <span class="mc-badge" style="' . esc_attr($badge_css) . '">' . esc_html($badge_label) . '</span></h2>'
            . ($active ? '<span class="mc-int-caret dashicons dashicons-arrow-right-alt2"></span>' : '')
            . '</div><p class="mc-desc" style="margin:6px 0 0;">' . esc_html($desc) . '</p>';
        if ($active) :
            $url = admin_url('admin.php?page=mailercloud-integrations&connector=' . $slug);
            ?>
            <a class="mc-int-card mc-active mc-int-card-link" href="<?php echo esc_url($url); ?>"><?php echo wp_kses_post($card_inner); ?></a>
        <?php else : ?>
            <div class="mc-int-card mc-not-installed"><?php echo wp_kses_post($card_inner); ?></div>
        <?php endif;
    endforeach; ?>
<?php endif; ?>

    <!-- Create New Property modal -->
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
</div>
