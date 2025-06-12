<?php

/**
 * WooCommerce Klassified Escrow Payment Gateway
 *
 * @since 1.0.0
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/includes/gateways
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
 * WooCommerce Klassified Escrow Payment Gateway
 *
 * @since 1.0.0
 */
class WC_Gateway_Klassified_Escrow extends WC_Payment_Gateway
{

    /**
     * Constructor for the gateway
     */
    public function __construct()
    {
        $this->id = 'klassified_escrow';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('Klassified Escrow', 'klassified-escrow-gateway');
        $this->method_description = __('Process payments through PayScrow escrow service.', 'klassified-escrow-gateway');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings from admin page
        $settings = get_option('klassified_escrow_gateway_settings', array());

        $this->enabled = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
        $this->title = isset($settings['title']) ? $settings['title'] : __('Escrow Payment', 'klassified-escrow-gateway');
        $this->description = isset($settings['description']) ? $settings['description'] : __('Pay securely using escrow.', 'klassified-escrow-gateway');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize gateway form fields (none needed, using admin page)
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'klassified-escrow-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable Klassified Escrow Payment', 'klassified-escrow-gateway'),
                'description' => __('This payment method is configured in the Klassified Escrow Gateway settings page.', 'klassified-escrow-gateway'),
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

        $processor = new Klassified_Escrow_Gateway_Payment('klassified-escrow-gateway', '1.0.0');
        $payment_link = $processor->process_escrow_payment($order_id, $_POST, $order);

        if (is_wp_error($payment_link) || empty($payment_link)) {
            wc_add_notice(__('Escrow payment could not be initialized. Please try again.', 'klassified-escrow-gateway'), 'error');
            return array('result' => 'failure');
        }

        return array(
            'result'   => 'success',
            'redirect' => $payment_link,
        );
    }
}
