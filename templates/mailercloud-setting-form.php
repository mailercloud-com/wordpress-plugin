<?php
// Prevent direct file access
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mailercloud-wrap">
    <div class="mailcloud-ver">
        <h1>Mailercloud Settings</h1>
        <div id="mailercloud_settings_page">
            <div id="mailercloud_apikey_section">
                <h2>Mailercloud Settings </h2>
                <form id="mc_api_key_form" class="mc-api-key-form" action="" method="post">
                    <div class="lable_mailer"><label for="mc_api_key">Enter Mailercloud API Key</label></div>
					<div class="mailer_input">
						<input type="text" id="mc_api_key" name="mc_api_key" value="<?php echo esc_attr($api_key); ?>"
							placeholder="Mailercloud Api Key">
						<input type="hidden" name="mc_api_key_nonce" value="<?php echo esc_attr($mc_api_key_nonce); ?>" />
					</div>
					<div class="mailer_verify">
						<input name="apikey_verify" class="button" type="submit" value="verify">
					</div>
                    <div class="mailercloud_links">
                        <p> <a href="https://app.mailercloud.com/account/api-integrations" target="_blank">Get your API key from your
                                account </a></p>
                        <p> You don't have a Mailercloud account yet? <a
                                href="https://app.mailercloud.com/register?ref=wordpress" target="_blank">Create an account </a></p>
                    </div>
                    <?php if ($message): ?>

                    <p id="apikey_feedback" class="<?php echo $msg ? 'success' : 'error'; ?>">

                        <?php   echo esc_html($message); ?>

                    </p>
                    <?php endif; ?>
                </form>
            </div>
            <?php if (!empty($user_data)): ?>
            <div id="mailercloud_my_account">
				<div class="mailer_account">
					<h2 class="mailer_my_account">My Account</h2>
					 <form id="mc_api_key_form_logout" class="mc-api-key-form_logout mailer_logout" action="" method="post">
						<input type="hidden" name="mc_api_logout_nonce" value="<?php echo esc_attr($mc_api_logout_nonce); ?>" />
						<input id="logout_account" class="button" name="mc_account_logout" type="submit" value="Log out">

					</form>
				</div>
                <p class="mailer_account_details"> <b>logged in as - </b><?php echo esc_html($user_data['name']); ?>
                    -<?php echo esc_html($user_data['email']); ?> </p>
                <p class="mailer_account_details"><span>Plan </span><br><strong><?php echo esc_html($user_data['plan']); ?> </strong></p>
                <p class="mailer_account_details"><span>Total contacts </span><br><strong> <?php echo esc_html($user_data['total_contacts']); ?> </strong></p>
                <p class="mailer_account_details"><span>Used contacts </span><br><strong> <?php echo esc_html($user_data['used_contacts']); ?> </strong></p>
                <p class="mailer_account_details"><span>Remaining contacts </span><br><strong> <?php echo esc_html($user_data['remaining_contacts']); ?> </strong></p>

               

            </div>
            <?php endif;?>
        </div>
    </div>
</div>