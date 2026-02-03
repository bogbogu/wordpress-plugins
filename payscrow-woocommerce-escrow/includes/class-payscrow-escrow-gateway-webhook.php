<?php

/**
 * Handle webhook operations
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 */

/**
 * Handle webhook operations.
 *
 * This class defines the webhook endpoint and processes PayScrow notifications.
 *
 * @since      1.0.0
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 * @author     Your Name <email@example.com>
 */
class PayScrow_WC_Escrow_Webhook {

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
     * Debug mode
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug    Whether debug mode is enabled
     */
    private $debug;
    
    /**
     * API instance
     *
     * @since    1.0.0
     * @access   private
     * @var      PayScrow_WC_Escrow_API    $api    API instance
     */
    private $api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $settings = get_option('payscrow_wc_escrow_settings', array());
        $this->debug = isset($settings['debug_mode']) && $settings['debug_mode'] === 'yes';
        
        $this->api = new PayScrow_WC_Escrow_API();
    }

    /**
     * Register the webhook endpoint
     *
     * @since    1.0.0
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'payscrow/escrow/v1', '/webhook', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'process_webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Process incoming webhook data
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full details about the request.
     * @return   WP_REST_Response               Response object
     */
    public function process_webhook($request) {
        // Log the webhook if debug is enabled
        if ($this->debug) {
            $this->log('Webhook received: ' . print_r($request->get_params(), true));
        }
        
        // Get the request body
        $payload = $request->get_json_params();
        
        if (empty($payload) || !isset($payload['transactionId']) || !isset($payload['status'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid payload'
            ), 400);
        }
        
        // Get transaction details
        $transaction_id = sanitize_text_field($payload['transactionId']);
        $status = sanitize_text_field($payload['status']);
        $escrow_code = isset($payload['escrowCode']) ? sanitize_text_field($payload['escrowCode']) : '';
        $external_reference = isset($payload['externalReference']) ? sanitize_text_field($payload['externalReference']) : '';
        
        // Verify transaction status with API
        $transaction_status = $this->api->get_transaction_status($transaction_id);
        
        if (is_wp_error($transaction_status)) {
            if ($this->debug) {
                $this->log('Transaction status verification failed: ' . $transaction_status->get_error_message());
            }
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Failed to verify transaction status'
            ), 500);
        }
        
        // Make sure status matches what we got in webhook
        if (isset($transaction_status['status']) && $transaction_status['status'] !== $status) {
            if ($this->debug) {
                $this->log('Status mismatch. Webhook: ' . $status . ', API: ' . $transaction_status['status']);
            }
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Status mismatch'
            ), 400);
        }
        
        // Look for transaction reference in multiple metadata fields
        $orders = wc_get_orders(array(
            'meta_query' => array(
                array(
                    'key'     => '_payscrow_transaction_id',
                    'value'   => $transaction_id,
                    'compare' => '='
                )
            ),
            'limit'      => 1
        ));
        
        // If not found, try to extract order ID from transaction reference
        if (empty($orders)) {
            // Try to extract the order ID from our reference format KM{orderID}T{timestamp}
            if (strpos($transaction_id, 'KM') === 0 && strpos($transaction_id, 'T') !== false) {
                $parts = explode('T', substr($transaction_id, 2));
                if (!empty($parts[0]) && is_numeric($parts[0])) {
                    $order_id = (int)$parts[0];
                    $order = wc_get_order($order_id);
                    
                    if ($order) {
                        // Save the transaction ID for future reference
                        $order->update_meta_data('_payscrow_transaction_id', $transaction_id);
                        $order->save();
                        
                        $orders = array($order);
                        $this->log("Found order #$order_id by extracting from reference: $transaction_id");
                    }
                }
            }
            
            // If still not found, check for external reference
            if (empty($orders) && !empty($external_reference) && is_numeric($external_reference)) {
                $order_id = (int)$external_reference;
                $order = wc_get_order($order_id);
                
                if ($order) {
                    // Save the transaction ID for future reference
                    $order->update_meta_data('_payscrow_transaction_id', $transaction_id);
                    $order->save();
                    
                    $orders = array($order);
                    $this->log("Found order #$order_id using external reference");
                }
            }
        }
        
        if (empty($orders)) {
            if ($this->debug) {
                $this->log('Order not found for transaction: ' . $transaction_id);
            }
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Order not found. Transaction ID: ' . $transaction_id
            ), 404);
        }
        
        $order = $orders[0];
        
        // Store escrow code if provided (HPOS-compatible)
        if (!empty($escrow_code)) {
            $order->update_meta_data('_payscrow_escrow_code', $escrow_code);
            $order->save();
            $order->add_order_note(sprintf(__('PayScrow escrow code: %s', 'payscrow-woocommerce-escrow'), $escrow_code));
        }
        
        // Process order based on transaction status
        switch ($status) {
            case 'Paid':
            case 'InProgress':
                // Payment has been received and in escrow
                $order->update_status('processing', __('Payment confirmed and in escrow via PayScrow.', 'payscrow-woocommerce-escrow'));
                break;
                
            case 'Completed':
                // Customer has been satisfied and released escrow code
                $order->add_order_note(__('Customer has released the escrow code via PayScrow.', 'payscrow-woocommerce-escrow'));
                $order->update_status('completed', __('Transaction completed in PayScrow.', 'payscrow-woocommerce-escrow'));
                break;
                
            case 'Finalized':
                // All parties settled and transaction closed
                $order->update_status('completed', __('Transaction finalized in PayScrow.', 'payscrow-woocommerce-escrow'));
                break;
                
            case 'Pending':
                // Transaction created but no payment yet
                $order->update_status('pending', __('Awaiting payment via PayScrow.', 'payscrow-woocommerce-escrow'));
                break;
                
            case 'Cancelled':
                // Transaction cancelled
                $order->update_status('cancelled', __('Transaction cancelled in PayScrow.', 'payscrow-woocommerce-escrow'));
                break;
                
            default:
                // Unknown status
                $order->add_order_note(sprintf(__('PayScrow status updated to: %s', 'payscrow-woocommerce-escrow'), $status));
                break;
        }
        
        // Handle dispute status separately (it's a boolean flag)
        if (isset($transaction_status['inDispute']) && $transaction_status['inDispute']) {
            $order->update_status('on-hold', __('Order has been disputed in PayScrow.', 'payscrow-woocommerce-escrow'));
            $order->add_order_note(__('Order dispute opened in PayScrow.', 'payscrow-woocommerce-escrow'));
        }
        
        if ($this->debug) {
            $this->log('Order #' . $order->get_id() . ' updated to ' . $status);
        }
        
        // Return success response
        return new WP_REST_Response(array(
            'status' => 'success',
            'message' => 'Webhook processed successfully'
        ), 200);
    }

    /**
     * Log debug information
     *
     * @since    1.0.0
     * @param    string    $message    Message to log
     */
    private function log($message) {
        if (!$this->debug) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/payscrow-escrow-logs';
        $log_file = $log_dir . '/webhook-' . date('Y-m-d') . '.log';
        
        // Create directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $time = date('Y-m-d H:i:s');
        error_log("[{$time}] {$message}\n", 3, $log_file);
    }
}
