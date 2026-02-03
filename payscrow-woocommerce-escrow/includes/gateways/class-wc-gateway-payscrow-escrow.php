<?php

/**
 * WooCommerce PayScrow Escrow Payment Gateway
 *
 * @since 1.0.0
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes/gateways
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Make sure WooCommerce is active
if (!class_exists('WC_Payment_Gateway')) {
    return;
}

/**
 * WooCommerce PayScrow Escrow Payment Gateway
 *
 * @since 1.0.0
 */
class WC_Gateway_PayScrow_Escrow extends WC_Payment_Gateway
{

    /**
     * Constructor for the gateway
     */
    public function __construct()
    {
        $this->id = 'payscrow_wc_escrow';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('PayScrow Escrow', 'payscrow-woocommerce-escrow');
        $this->method_description = __('Process payments through PayScrow escrow service.', 'payscrow-woocommerce-escrow');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings from admin page
        $settings = get_option('payscrow_wc_escrow_settings', array());

        $this->enabled = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
        $this->title = isset($settings['title']) ? $settings['title'] : __('Escrow Payment', 'payscrow-woocommerce-escrow');
        $this->description = isset($settings['description']) ? $settings['description'] : __('Pay securely using escrow.', 'payscrow-woocommerce-escrow');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Ensure the processor uses the updated class
        $this->processor_class = 'PayScrow_WC_Escrow_Payment';
    }

    /**
     * Initialize gateway form fields (none needed, using admin page)
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'payscrow-woocommerce-escrow'),
                'type'        => 'checkbox',
                'label'       => __('Enable PayScrow Escrow Payment', 'payscrow-woocommerce-escrow'),
                'description' => __('This payment method is configured in the PayScrow Escrow settings page.', 'payscrow-woocommerce-escrow'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $processor = new PayScrow_WC_Escrow_Payment('payscrow-woocommerce-escrow', '1.0.0');
        $payment_link = $processor->process_escrow_payment($order_id, $_POST, $order);

        if (is_wp_error($payment_link) || empty($payment_link)) {
            wc_add_notice(__('Escrow payment could not be initialized. Please try again.', 'payscrow-woocommerce-escrow'), 'error');
            return array('result' => 'failure');
        }

        return array(
            'result'   => 'success',
            'redirect' => $payment_link,
        );
    }
}
