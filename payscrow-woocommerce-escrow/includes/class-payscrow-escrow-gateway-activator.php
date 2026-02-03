<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 * @author     Your Name <email@example.com>
 */
class PayScrow_WC_Escrow_Activator {

    /**
     * Activate the plugin.
     *
     * Checks for WooCommerce and initializes default settings.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('PayScrow WooCommerce Escrow requires WooCommerce to be installed and active.', 'payscrow-woocommerce-escrow'), 'Plugin dependency check', array('back_link' => true));
        }

        // Initialize default plugin settings
        $default_settings = array(
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

        // Only set defaults if settings don't already exist
        if (!get_option('payscrow_wc_escrow_settings')) {
            update_option('payscrow_wc_escrow_settings', $default_settings);
        }

        // Create a log file if debug is enabled
        if (get_option('payscrow_wc_escrow_settings')['debug_mode'] === 'yes') {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/payscrow-escrow-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                
                // Create an .htaccess file to protect logs
                $htaccess_content = "Order Deny,Allow\nDeny from all";
                file_put_contents($log_dir . '/.htaccess', $htaccess_content);
                
                // Create index.php to prevent directory listing
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
        }

        // Flush rewrite rules for custom endpoints
        flush_rewrite_rules();
    }
}
