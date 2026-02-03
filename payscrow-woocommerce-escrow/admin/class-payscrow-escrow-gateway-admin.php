<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/admin
 * @author     Your Name <email@example.com>
 */
class PayScrow_WC_Escrow_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/payscrow-escrow-gateway-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/payscrow-escrow-gateway-admin.js', array( 'jquery' ), $this->version, false );
    }

    /**
     * Add options page for plugin settings
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_options_page(
            __('PayScrow Escrow Gateway', 'payscrow-woocommerce-escrow'),
            __('PayScrow Escrow', 'payscrow-woocommerce-escrow'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links
     * @return   array              Plugin action links
     */
    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name) . '">' . __('Settings', 'payscrow-woocommerce-escrow') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once('partials/payscrow-escrow-gateway-admin-display.php');
    }

    /**
     * Register settings
     *
     * @since    1.0.0
     */
    public function register_setting() {
        register_setting(
            'payscrow_wc_escrow_settings_group',
            'payscrow_wc_escrow_settings',
            array($this, 'validate_settings')
        );
    }

    /**
     * Validate settings
     *
     * @since    1.0.0
     * @param    array    $input    Input values
     * @return   array              Sanitized values
     */
    public function validate_settings($input) {
        $validated = array();
        
        // Checkbox options
        $validated['enabled'] = isset($input['enabled']) ? 'yes' : 'no';
        $validated['sandbox_mode'] = isset($input['sandbox_mode']) ? 'yes' : 'no';
        $validated['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';
        
        // Text fields
        $validated['title'] = sanitize_text_field($input['title']);
        $validated['description'] = wp_kses_post($input['description']);
        $validated['admin_account_name'] = sanitize_text_field($input['admin_account_name']);
        $validated['admin_account_number'] = sanitize_text_field($input['admin_account_number']);
        $validated['admin_bank_code'] = sanitize_text_field($input['admin_bank_code']);
        $validated['broker_api_key'] = sanitize_text_field($input['broker_api_key']);
        // Webhook secret (optional but required for secure processing)
        $validated['webhook_secret'] = isset($input['webhook_secret']) ? sanitize_text_field($input['webhook_secret']) : '';
        
        // Number fields
        $admin_percentage = floatval($input['admin_percentage']);
        $validated['admin_percentage'] = $admin_percentage >= 0 && $admin_percentage <= 100 ? $admin_percentage : 10;
        
        return $validated;
    }

    /**
     * Add vendor meta fields to user profile
     *
     * @since    1.0.0
     * @param    WP_User    $user    User object
     */
    public function add_vendor_meta_fields($user) {
        // Only show these fields to users who can be vendors
        $is_vendor = false;

        // Check for WooCommerce Marketplace plugin roles
        if (function_exists('wc_get_user_property') && wc_get_user_property($user->ID, 'is_vendor')) {
            $is_vendor = true;
        }
        
        // Check for other vendor roles (modify as needed for your specific setup)
        $vendor_roles = array('vendor', 'seller', 'wcfm_vendor', 'dc_vendor', 'administrator');
        foreach ($vendor_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                $is_vendor = true;
                break;
            }
        }
        
        // Exit if not a vendor
        if (!$is_vendor && !current_user_can('manage_options')) {
            return;
        }
        
        // Get current values
        $account_name = get_user_meta($user->ID, 'payscrow_account_name', true);
        $account_number = get_user_meta($user->ID, 'payscrow_account_number', true);
        $bank_code = get_user_meta($user->ID, 'payscrow_bank_code', true);
        
        ?>
        <h3><?php _e('Escrow Payment Details', 'payscrow-woocommerce-escrow'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="payscrow_account_name"><?php _e('Account Name', 'payscrow-woocommerce-escrow'); ?></label></th>
                <td>
                    <input type="text" name="payscrow_account_name" id="payscrow_account_name" 
                        value="<?php echo esc_attr($account_name); ?>" class="regular-text" />
                    <p class="description"><?php _e('Bank account name for escrow settlement', 'payscrow-woocommerce-escrow'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="payscrow_account_number"><?php _e('Account Number', 'payscrow-woocommerce-escrow'); ?></label></th>
                <td>
                    <input type="text" name="payscrow_account_number" id="payscrow_account_number" 
                        value="<?php echo esc_attr($account_number); ?>" class="regular-text" />
                    <p class="description"><?php _e('Bank account number for escrow settlement', 'payscrow-woocommerce-escrow'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="payscrow_bank_code"><?php _e('Bank Code', 'payscrow-woocommerce-escrow'); ?></label></th>
                <td>
                    <input type="text" name="payscrow_bank_code" id="payscrow_bank_code" 
                        value="<?php echo esc_attr($bank_code); ?>" class="regular-text" />
                    <p class="description"><?php _e('Bank code for escrow settlement (e.g., 044 for Access Bank)', 'payscrow-woocommerce-escrow'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save vendor meta fields when profile is updated
     *
     * @since    1.0.0
     * @param    int    $user_id    User ID
     */
    public function save_vendor_meta_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Save account name
        if (isset($_POST['payscrow_account_name'])) {
            update_user_meta($user_id, 'payscrow_account_name', sanitize_text_field($_POST['payscrow_account_name']));
        }
        
        // Save account number
        if (isset($_POST['payscrow_account_number'])) {
            update_user_meta($user_id, 'payscrow_account_number', sanitize_text_field($_POST['payscrow_account_number']));
        }
        
        // Save bank code
        if (isset($_POST['payscrow_bank_code'])) {
            update_user_meta($user_id, 'payscrow_bank_code', sanitize_text_field($_POST['payscrow_bank_code']));
        }
    }
}
