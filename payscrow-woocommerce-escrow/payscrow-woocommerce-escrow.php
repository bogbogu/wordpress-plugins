<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://payscrow.net
 * @since             1.0.1.1
 * @package           PayScrow_WC_Escrow
 *
 * @wordpress-plugin
 * Plugin Name:       PayScrow WooCommerce Escrow
 * Plugin URI:        https://payscrow.net/plugins/payscrow-woocommerce-escrow
 * Description:       Integrates WooCommerce with PayScrow's escrow payment gateway for secure marketplace transactions. Supports multi-vendor sites by collecting vendor bank details and distributing payments.
 * Version:           1.1.4
 * Author:            PayScrow
 * Author URI:        https://payscrow.net
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       payscrow-woocommerce-escrow
 * Domain Path:       /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.4.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 */
define( 'PAYSCROW_WC_ESCROW_VERSION', '1.0.0' );
define( 'PAYSCROW_WC_ESCROW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYSCROW_WC_ESCROW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-payscrow-escrow-gateway-activator.php
 */
function activate_payscrow_wc_escrow() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-payscrow-escrow-gateway-activator.php';
    PayScrow_WC_Escrow_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-payscrow-escrow-gateway-deactivator.php
 */
function deactivate_payscrow_wc_escrow() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-payscrow-escrow-gateway-deactivator.php';
    PayScrow_WC_Escrow_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_payscrow_wc_escrow' );
register_deactivation_hook( __FILE__, 'deactivate_payscrow_wc_escrow' );

/**
 * Declare HPOS compatibility
 */
add_action(
    'before_woocommerce_init',
    function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-payscrow-escrow-gateway.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_payscrow_wc_escrow() {

    // Check if WooCommerce is active
    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_action( 'admin_notices', 'payscrow_wc_escrow_woocommerce_missing_notice' );
        return;
    }

    $plugin = new PayScrow_WC_Escrow();
    $plugin->run();

}

/**
 * Admin notice for missing WooCommerce
 */
function payscrow_wc_escrow_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e( 'PayScrow Escrow Gateway requires WooCommerce to be installed and active.', 'payscrow-woocommerce-escrow' ); ?></p>
    </div>
    <?php
}

add_action( 'plugins_loaded', 'run_payscrow_wc_escrow' );
