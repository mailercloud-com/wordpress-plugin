<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mailercloud-wrap sign-up_list">
    <h1>Signup form Listing </h1>
    <div class="container sign-up_list-inner">
        <div class="mailer_sign_form">
            <div class="mailer_fl">
                <h2>Forms </h2>
            </div>
            <div class="mailer_fl_right">
                <a class="button" href="https://app.mailercloud.com/webform/create" target="_blank">Add New Form</a>
            </div>
        </div>
        <?php if( !empty($webforms)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Form Name</th>
                    <th scope="col">Shortcode</th>
                    <th scope="col">Type</th>
                    <th scope="col">Linked List</th>
                    <th scope="col">Submissions</th>
                    <th scope="col">Views</th>
                    <th scope="col">Last updated</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $i=1; 
                    foreach($webforms as $webform):
                    ?>
                <tr>
                    <td><?php echo esc_html($webform['name']); ?></td>
                    <td>[sibwp_form name="<?php echo esc_attr($webform['name']); ?>"]</td>
                    <td><?php echo esc_html($webform['type']); ?></td>
                    <td><?php echo esc_html($webform['linked_list']); ?></td>
                    <td><?php echo esc_html($webform['submission_count']); ?></td>
                    <td><?php echo esc_html($webform['view_count']); ?></td>
                    <td><?php echo esc_html($webform['last_updated']); ?></td>
                </tr>
                <?php 
                 endforeach;
                
                 ?>

            </tbody>

        </table>
        <?php 
               
                 endif; 
                 ?>

    </div>
</div>