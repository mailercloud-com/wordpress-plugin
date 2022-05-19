<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="mailercloud-wrap subs-p">
    <h1 class="header_sync">Contact Sync</h1>
    <form id="mc_api_key_form" class="mc-api-key-form" action="" method="post">

        <input type="hidden" name="mc_sync_list_key" value="<?php echo esc_attr($mc_sync_list_key); ?>" />
        <input type="hidden" id="selected_list_name" name="selected_list_name"
            value="<?php echo esc_attr($selected_list_name); ?>" />
        <div id="synchronisation_list">
            <h2>Attributes Mapping</h2>
            <div class="lable_mailer">
                <label for="list_id">Choose the mailcloud list to sync with .
                    do you want to add to mailercloud?</label>
            </div>
            <div class="mailer_select">
                <select name="list_id" id="list_id" required>
                    <?php
						foreach ($lists as $id => $name) :
						?>
                    <option value="<?php echo esc_attr($id); ?>"
                        <?php echo ( esc_attr($selected_list_name) == $name)?'selected=selected':''; ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                    <?php
						endforeach;
					?>
                </select>
            </div>
        </div>
        <div id="costs_main_new">
            <h2>Match And Add Custom Attributes</h2>
            <div class="attribute_header">
                <label class="wordpress_attributes">Wordpress Users Attributes</label> <label
                    class="mailercloud_attributes2">Mailercloud Contact Attributes</label>
            </div>
            <?php if(!empty( $jsonData)) :?>
            <?php
            $display_title_first = true;
            $display_title_second = true;
            $check_custom_field_count = 0;
            $normal_field_count = array_count_values(array_column($jsonData, 'is_custom_fields'))[0]; 
            $j = 0;
            $i = 0;
           ?>

            <?php  foreach ($jsonData as $id => $row) : ?>

            <?php if(!($row['is_custom_fields'])) : $j++;  ?>


            <div class="costs_main">

                <div class="input-group repeat_div">
                    <div class="word_divs">

                        <select name="wordpress_attributes[]" class="wordpress_attributes" required>
                            <option value=""></option>
                            <?php
                        foreach ($wordpress_attributes as $id => $name) :
                        ?>
                            <option value="<?php echo esc_attr($id); ?>"
                                <?php echo ( esc_attr($row['wordpress_attribute']) == $id)?'selected=selected':''; ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                            <?php
                        endforeach;
                    ?>
                        </select>
                    </div>
                    <div class="word_divs">

                        <select name="mailercloud_attributes[]" class="mailercloud_attributes" required>
                            <option value=""></option>
                            <?php
                        foreach ($mailercloud_attributes as $id => $name) :
                        ?>
                            <option value="<?php echo esc_attr($id); ?>"
                                <?php echo ( esc_attr($row['mailercloud_attribute']) == $id)?'selected=selected':''; ?>>
                                <?php echo esc_html($name); ?></option>
                            <?php
                        endforeach;
                    ?>
                        </select>
                    </div>
                    <div class="action_btns">
                        <?php if( $j == $normal_field_count): ?>
                        <button type="button" class="add_line">+</button>
                        <?php else: ?>
                        <button type="button" class="del_line">-</button>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

            <?php endif; ?>

            <?php  endforeach; ?>
            <?php else : ?>

            <div class="costs_main">

                <div class="input-group repeat_div">
                    <div class="word_divs">

                        <select name="wordpress_attributes[]" class="wordpress_attributes" required>
                            <?php
                        foreach ($wordpress_attributes as $id => $name) :
                        ?>
                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                            <?php
                        endforeach;
                    ?>
                        </select>
                    </div>
                    <div class="word_divs">

                        <select name="mailercloud_attributes[]" class="mailercloud_attributes" required>
                            <?php
                        foreach ($mailercloud_attributes as $id => $name) :
                        ?>
                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                            <?php
                        endforeach;
                    ?>
                        </select>
                    </div>
                    <div class="action_btns">
                        <button type="button" class="add_line">+</button>

                    </div>
                </div>
            </div>




            <?php endif; ?>
            <button type="button" id="new_property">Create new property</button>
        </div>
        <?php  if ($message2) : ?>
        <p id="sync_feedback_mapping" class="<?php echo $msg2 ? 'success' : 'error'; ?>">
            <?php  
                        echo esc_html($message2);
                    ?>
        </p>
        <?php endif; ?>
        <div id="subscriber_synchronisation_settings_page">

            <input name="apikey_sync_list" class="button" type="submit" value="Save Mapping">
        </div>

    </form>
    <div class="loader_mailercloud">
        <div class="overlay-img">
            <img src="<?php echo plugin_dir_url(__DIR__) . '/assets/images/loader.gif' ?>">
        </div>
    </div>
    <form id="contact_sync_now_form" class="mc_contact_sync_now_form" action="" method="post">
        <div id="subscriber_synchronisation_settings_page">
            <h2>Contact Sync</h2>
            <label>You have <b><?php echo  esc_html($user_count);?></b> existing users.
                do you want to add to mailercloud? </label>
            <input name="contact_sync_now" class="button" type="submit" value="sync my users">
        </div>
        <div id="sync_feedback" class="">
            <?php  if ($message) : ?>

            <div id="msg_feedback" class="">
                <?php  
                        echo esc_html($message);
                    ?>
            </div>
            <div class="total_contacts">
                <?php
         
                if(!empty( $user_data)){
                    echo '<p><span> Total Contacts Inserted  </span><br>'.esc_html($user_data['inserted']).'</p>';
                    echo '<p><span>Total Contacts Skipped  </span><br>'.esc_html($user_data['skipped']).'</p>';
                    echo '<p><span>Total Contacts Submitted  </span><br>'.esc_html($user_data['submitted']).'</p>';
                    echo '<p><span>Total Updated Contacts  </span><br>'.esc_html($user_data['updated']).'</p>';

                }
            endif;
            ?>
                <div class="">
                    <?php
                    if(!empty( $errors)){
                        foreach($errors as $error){
                            $field = $error['field']?$error['field']:'';
                            $msgs = $error['message']?$error['message']:'';
                            echo '<p><b> '. esc_html($field).' </b>'. esc_html($msgs) .'</p>';
                        }

                    }
                

                    ?>
                </div>
            </div>
        </div>

    </form>

    <!-- The Modal -->
    <div id="myModal" class="modal">


        <!-- Modal content -->
        <div class="modal-content mailer_popup">
            <div class="modal-header">
                <span class="close">&times;</span>
            </div>

            <h2>Create new property</h2>
            <form id="newPropertyForm" action="" method="post">
                <label for="fname">Attribute name*</label><br>
                <input type="text" id="attributename" name="name" required><br>
                <label for="lname">Field type*</label><br>

                <?php $type_arr = [
                    "text" =>"Text (Text field can store upto 100 characters)",
                    "number" =>"Number (Number field can store upto 30 characters)", 
                    "textarea" =>" Text Area (Text area field can store upto 500 characters)"
                    ]; 
                    ?>
                <select name="type" class="type" required>
                    <?php
                        foreach ($type_arr as  $id=>$name) :
                        ?>
                    <option value="<?php echo esc_attr($id); ?>" title="<?php echo esc_attr($name); ?>">
                        <?php echo esc_html($name); ?></option>
                    <?php
                        endforeach;
                    ?>
                </select></br>
                <label for="fname">Description</label><br>
                <input type="text" id="description" name="description" required><br>
                <div id="response_feedback"> </div>
                <br>
                <input name="property_create" id="property_create" class="button" type="submit" value="Create">

            </form>
        </div>

    </div>



</div>