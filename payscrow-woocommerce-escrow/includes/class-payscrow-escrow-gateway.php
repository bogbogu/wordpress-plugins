<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 * @author     Your Name <email@example.com>
 */
class PayScrow_WC_Escrow {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      PayScrow_WC_Escrow_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'PAYSCROW_WC_ESCROW_VERSION' ) ) {
            $this->version = PAYSCROW_WC_ESCROW_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'payscrow-woocommerce-escrow';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_payment_hooks();
        $this->define_webhook_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - PayScrow_WC_Escrow_Loader. Orchestrates the hooks of the plugin.
     * - PayScrow_WC_Escrow_i18n. Defines internationalization functionality.
     * - PayScrow_WC_Escrow_Admin. Defines all hooks for the admin area.
     * - PayScrow_WC_Escrow_Public. Defines all hooks for the public side of the site.
     * - PayScrow_WC_Escrow_Payment. Defines all payment gateway functionality.
     * - PayScrow_WC_Escrow_API. Handles API interactions with PayScrow.
     * - PayScrow_WC_Escrow_Webhook. Handles webhook operations.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-payscrow-escrow-gateway-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-payscrow-escrow-gateway-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-payscrow-escrow-gateway-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-payscrow-escrow-gateway-public.php';

        /**
         * The class responsible for defining payment gateway functionality.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-payscrow-escrow-gateway-payment.php';

        /**
         * The class responsible for API interactions.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-payscrow-escrow-gateway-api.php';

        /**
         * The class responsible for webhook handling.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-payscrow-escrow-gateway-webhook.php';

        $this->loader = new PayScrow_WC_Escrow_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the PayScrow_WC_Escrow_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new PayScrow_WC_Escrow_i18n();

        // Load text domain on init instead of plugins_loaded to avoid the warning
        $this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new PayScrow_WC_Escrow_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        
        // Add admin settings page
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        
        // Add settings link on plugin page
        $this->loader->add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' ), $plugin_admin, 'add_action_links' );
        
        // Save/update settings
        $this->loader->add_action( 'admin_init', $plugin_admin, 'register_setting' );
        
        // Add vendor meta fields
        $this->loader->add_action( 'show_user_profile', $plugin_admin, 'add_vendor_meta_fields' );
        $this->loader->add_action( 'edit_user_profile', $plugin_admin, 'add_vendor_meta_fields' );
        $this->loader->add_action( 'personal_options_update', $plugin_admin, 'save_vendor_meta_fields' );
        $this->loader->add_action( 'edit_user_profile_update', $plugin_admin, 'save_vendor_meta_fields' );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new PayScrow_WC_Escrow_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

    }

    /**
     * Register all of the hooks related to the payment gateway functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_payment_hooks() {

        $plugin_payment = new PayScrow_WC_Escrow_Payment( $this->get_plugin_name(), $this->get_version() );

        // Add payment gateway to WooCommerce
        $this->loader->add_filter( 'woocommerce_payment_gateways', $plugin_payment, 'add_gateway_class' );
        
        // Payment processing hooks
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $plugin_payment, 'process_escrow_payment', 10, 3 );
        
        // Escrow code apply functionality (optional)
        $this->loader->add_action( 'woocommerce_cart_calculate_fees', $plugin_payment, 'apply_escrow_code_discount' );
        $this->loader->add_action( 'woocommerce_after_checkout_form', $plugin_payment, 'add_escrow_code_field' );
        $this->loader->add_action( 'wp_ajax_apply_escrow_code', $plugin_payment, 'apply_escrow_code_ajax' );
        $this->loader->add_action( 'wp_ajax_nopriv_apply_escrow_code', $plugin_payment, 'apply_escrow_code_ajax' );
    }

    /**
     * Register all of the hooks related to the webhook functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_webhook_hooks() {

        $plugin_webhook = new PayScrow_WC_Escrow_Webhook( $this->get_plugin_name(), $this->get_version() );

        // Register webhook REST endpoint
        $this->loader->add_action( 'rest_api_init', $plugin_webhook, 'register_webhook_endpoint' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    PayScrow_WC_Escrow_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

}
