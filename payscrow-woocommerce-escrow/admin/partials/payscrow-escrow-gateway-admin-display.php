<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/admin/partials
 */

// Get current settings
$options = get_option('payscrow_wc_escrow_settings', array());

// Set defaults if not set
if (empty($options)) {
    $options = array(
        'enabled' => 'yes',
        'title' => 'Escrow Payment Gateway',
        'description' => 'Pay securely using PayScrow Escrow service',
        'admin_account_name' => '',
        'admin_account_number' => '',
        'admin_bank_code' => '',
        'broker_api_key' => '',
        'sandbox_mode' => 'yes',
        'admin_percentage' => '10',
        'debug_mode' => 'no',
    );
}
?>

<div class="wrap">
    <div class="payscrow-escrow-admin-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php _e('Configure the PayScrow escrow payment gateway integration for your marketplace.', 'payscrow-woocommerce-escrow'); ?></p>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('payscrow_wc_escrow_settings_group');
        ?>
        
        <div class="payscrow-escrow-admin-section">
            <h2><?php _e('General Settings', 'payscrow-woocommerce-escrow'); ?></h2>
            <table class="form-table payscrow-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_enabled">
                            <?php _e('Enable/Disable', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="payscrow_wc_escrow_settings_enabled" 
                               name="payscrow_wc_escrow_settings[enabled]" 
                               value="yes" <?php checked('yes', $options['enabled']); ?> />
                        <span class="description"><?php _e('Enable PayScrow Escrow Payment Gateway', 'payscrow-woocommerce-escrow'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_title">
                            <?php _e('Title', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="payscrow_wc_escrow_settings_title" 
                               name="payscrow_wc_escrow_settings[title]" 
                               value="<?php echo esc_attr($options['title']); ?>" />
                        <p class="description"><?php _e('This controls the title which the user sees during checkout.', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_description">
                            <?php _e('Description', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="payscrow_wc_escrow_settings_description" 
                                 name="payscrow_wc_escrow_settings[description]" rows="3"><?php echo esc_textarea($options['description']); ?></textarea>
                        <p class="description"><?php _e('This controls the description which the user sees during checkout.', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="payscrow-escrow-admin-section">
            <h2><?php _e('API Configuration', 'payscrow-woocommerce-escrow'); ?></h2>
            <table class="form-table payscrow-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_sandbox_mode">
                            <?php _e('Sandbox Mode', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="payscrow_wc_escrow_settings_sandbox_mode" 
                               name="payscrow_wc_escrow_settings[sandbox_mode]" 
                               value="yes" <?php checked('yes', $options['sandbox_mode']); ?> />
                        <span class="description"><?php _e('Enable sandbox mode for testing', 'payscrow-woocommerce-escrow'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_broker_api_key">
                            <?php _e('Broker API Key', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="payscrow_wc_escrow_settings_broker_api_key" 
                               name="payscrow_wc_escrow_settings[broker_api_key]" 
                               value="<?php echo esc_attr($options['broker_api_key']); ?>" />
                        <p class="description api-key-description"><?php _e('Your PayScrow API key', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_webhook_secret">
                            <?php _e('Webhook Secret', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password" id="payscrow_wc_escrow_settings_webhook_secret" 
                               name="payscrow_wc_escrow_settings[webhook_secret]" 
                               value="<?php echo esc_attr($options['webhook_secret']); ?>" />
                        <p class="description"><?php _e('Secret used to verify webhook signatures (HMAC SHA256). Keep this private.', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="payscrow-escrow-admin-section">
            <h2><?php _e('Admin Account Settings', 'payscrow-woocommerce-escrow'); ?></h2>
            <p><?php _e('These details will be used for the marketplace admin settlement account.', 'payscrow-woocommerce-escrow'); ?></p>
            <table class="form-table payscrow-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_admin_account_name">
                            <?php _e('Admin Account Name', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="payscrow_wc_escrow_settings_admin_account_name" 
                               name="payscrow_wc_escrow_settings[admin_account_name]" 
                               value="<?php echo esc_attr($options['admin_account_name']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_admin_account_number">
                            <?php _e('Admin Account Number', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="payscrow_wc_escrow_settings_admin_account_number" 
                               name="payscrow_wc_escrow_settings[admin_account_number]" 
                               value="<?php echo esc_attr($options['admin_account_number']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_admin_bank_code">
                            <?php _e('Admin Bank Code', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="payscrow_wc_escrow_settings_admin_bank_code" 
                               name="payscrow_wc_escrow_settings[admin_bank_code]" 
                               value="<?php echo esc_attr($options['admin_bank_code']); ?>" />
                        <p class="description"><?php _e('Bank code (e.g., 044 for Access Bank)', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_admin_percentage">
                            <?php _e('Admin Percentage', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="payscrow_wc_escrow_settings_admin_percentage" 
                               name="payscrow_wc_escrow_settings[admin_percentage]" 
                               value="<?php echo esc_attr($options['admin_percentage']); ?>" 
                               min="0" max="100" step="0.01" />
                        <p class="description"><?php _e('Percentage of each transaction that goes to the admin account (0-100)', 'payscrow-woocommerce-escrow'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="payscrow-escrow-admin-section">
            <h2><?php _e('Advanced Settings', 'payscrow-woocommerce-escrow'); ?></h2>
            <table class="form-table payscrow-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="payscrow_wc_escrow_settings_debug_mode">
                            <?php _e('Debug Mode', 'payscrow-woocommerce-escrow'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="payscrow_wc_escrow_settings_debug_mode" 
                               name="payscrow_wc_escrow_settings[debug_mode]" 
                               value="yes" <?php checked('yes', $options['debug_mode']); ?> />
                        <span class="description"><?php _e('Enable logging for debugging purposes', 'payscrow-woocommerce-escrow'); ?></span>
                        
                        <?php if ($options['debug_mode'] === 'yes'): ?>
                        <div class="payscrow-escrow-debug-section">
                            <h3><?php _e('Debug Information', 'payscrow-woocommerce-escrow'); ?></h3>
                            <p><?php _e('Logs are stored in:', 'payscrow-woocommerce-escrow'); ?> 
                            <code><?php echo esc_html(wp_upload_dir()['basedir'] . '/payscrow-escrow-logs/'); ?></code></p>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="payscrow-escrow-admin-buttons">
            <?php submit_button(__('Save Settings', 'payscrow-woocommerce-escrow'), 'primary', 'submit', false); ?>
        </div>
    </form>
</div>
