<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/admin/partials
 */

// Get current settings
$options = get_option('klassified_escrow_gateway_settings', array());

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
    <div class="klassified-escrow-admin-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php _e('Configure the PayScrow escrow payment gateway integration for your marketplace.', 'klassified-escrow-gateway'); ?></p>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('klassified_escrow_gateway_settings_group');
        ?>
        
        <div class="klassified-escrow-admin-section">
            <h2><?php _e('General Settings', 'klassified-escrow-gateway'); ?></h2>
            <table class="form-table klassified-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_enabled">
                            <?php _e('Enable/Disable', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="klassified_escrow_gateway_settings_enabled" 
                               name="klassified_escrow_gateway_settings[enabled]" 
                               value="yes" <?php checked('yes', $options['enabled']); ?> />
                        <span class="description"><?php _e('Enable PayScrow Escrow Payment Gateway', 'klassified-escrow-gateway'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_title">
                            <?php _e('Title', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="klassified_escrow_gateway_settings_title" 
                               name="klassified_escrow_gateway_settings[title]" 
                               value="<?php echo esc_attr($options['title']); ?>" />
                        <p class="description"><?php _e('This controls the title which the user sees during checkout.', 'klassified-escrow-gateway'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_description">
                            <?php _e('Description', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="klassified_escrow_gateway_settings_description" 
                                 name="klassified_escrow_gateway_settings[description]" rows="3"><?php echo esc_textarea($options['description']); ?></textarea>
                        <p class="description"><?php _e('This controls the description which the user sees during checkout.', 'klassified-escrow-gateway'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="klassified-escrow-admin-section">
            <h2><?php _e('API Configuration', 'klassified-escrow-gateway'); ?></h2>
            <table class="form-table klassified-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_sandbox_mode">
                            <?php _e('Sandbox Mode', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="klassified_escrow_gateway_settings_sandbox_mode" 
                               name="klassified_escrow_gateway_settings[sandbox_mode]" 
                               value="yes" <?php checked('yes', $options['sandbox_mode']); ?> />
                        <span class="description"><?php _e('Enable sandbox mode for testing', 'klassified-escrow-gateway'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_broker_api_key">
                            <?php _e('Broker API Key', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="klassified_escrow_gateway_settings_broker_api_key" 
                               name="klassified_escrow_gateway_settings[broker_api_key]" 
                               value="<?php echo esc_attr($options['broker_api_key']); ?>" />
                        <p class="description api-key-description"><?php _e('Your PayScrow API key', 'klassified-escrow-gateway'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="klassified-escrow-admin-section">
            <h2><?php _e('Admin Account Settings', 'klassified-escrow-gateway'); ?></h2>
            <p><?php _e('These details will be used for the marketplace admin settlement account.', 'klassified-escrow-gateway'); ?></p>
            <table class="form-table klassified-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_admin_account_name">
                            <?php _e('Admin Account Name', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="klassified_escrow_gateway_settings_admin_account_name" 
                               name="klassified_escrow_gateway_settings[admin_account_name]" 
                               value="<?php echo esc_attr($options['admin_account_name']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_admin_account_number">
                            <?php _e('Admin Account Number', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="klassified_escrow_gateway_settings_admin_account_number" 
                               name="klassified_escrow_gateway_settings[admin_account_number]" 
                               value="<?php echo esc_attr($options['admin_account_number']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_admin_bank_code">
                            <?php _e('Admin Bank Code', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="klassified_escrow_gateway_settings_admin_bank_code" 
                               name="klassified_escrow_gateway_settings[admin_bank_code]" 
                               value="<?php echo esc_attr($options['admin_bank_code']); ?>" />
                        <p class="description"><?php _e('Bank code (e.g., 044 for Access Bank)', 'klassified-escrow-gateway'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_admin_percentage">
                            <?php _e('Admin Percentage', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="klassified_escrow_gateway_settings_admin_percentage" 
                               name="klassified_escrow_gateway_settings[admin_percentage]" 
                               value="<?php echo esc_attr($options['admin_percentage']); ?>" 
                               min="0" max="100" step="0.01" />
                        <p class="description"><?php _e('Percentage of each transaction that goes to the admin account (0-100)', 'klassified-escrow-gateway'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="klassified-escrow-admin-section">
            <h2><?php _e('Advanced Settings', 'klassified-escrow-gateway'); ?></h2>
            <table class="form-table klassified-escrow-form-table">
                <tr>
                    <th scope="row">
                        <label for="klassified_escrow_gateway_settings_debug_mode">
                            <?php _e('Debug Mode', 'klassified-escrow-gateway'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="klassified_escrow_gateway_settings_debug_mode" 
                               name="klassified_escrow_gateway_settings[debug_mode]" 
                               value="yes" <?php checked('yes', $options['debug_mode']); ?> />
                        <span class="description"><?php _e('Enable logging for debugging purposes', 'klassified-escrow-gateway'); ?></span>
                        
                        <?php if ($options['debug_mode'] === 'yes'): ?>
                        <div class="klassified-escrow-debug-section">
                            <h3><?php _e('Debug Information', 'klassified-escrow-gateway'); ?></h3>
                            <p><?php _e('Logs are stored in:', 'klassified-escrow-gateway'); ?> 
                            <code><?php echo esc_html(wp_upload_dir()['basedir'] . '/klassified-escrow-logs/'); ?></code></p>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="klassified-escrow-admin-buttons">
            <?php submit_button(__('Save Settings', 'klassified-escrow-gateway'), 'primary', 'submit', false); ?>
        </div>
    </form>
</div>
