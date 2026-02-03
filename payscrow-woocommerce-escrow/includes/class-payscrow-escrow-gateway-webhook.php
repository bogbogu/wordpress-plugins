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
    /**
     * Process incoming webhook data
     *
     * Idempotency mechanism: to avoid re-processing duplicate webhooks we store a transient
     * keyed by `transactionIdentifier|status` (prefer transactionNumber when available). If
     * the same event is received again within the TTL it will be ignored. We also use a short
     * "inflight" transient during processing to avoid race conditions.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full details about the request.
     * @return   WP_REST_Response               Response object
     */
    public function process_webhook($request) {
        // Early: Verify webhook signature using configured webhook secret (HMAC SHA256)
        $settings = get_option('payscrow_wc_escrow_settings', array());
        $webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';

        // Get raw body for signature verification
        $raw_body = $request->get_body();

        // Look for common signature headers (case-insensitive)
        $signature = $request->get_header('x-payscrow-signature');
        if (empty($signature)) {
            $signature = $request->get_header('x-signature');
        }

        // Require a configured webhook secret to verify signatures
        if (empty($webhook_secret) || empty($signature)) {
            $this->log('Webhook verification failed: missing secret or signature header', true);
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Webhook verification failed'), 401);
        }

        // Some providers prefix signatures like "sha256=..."; accept that form
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $raw_body, $webhook_secret);

        if (!hash_equals($expected, $signature)) {
            // Don't log secrets; log a short prefix for diagnostics
            $this->log('Webhook verification failed: invalid signature (prefix: ' . substr($signature, 0, 8) . ')', true);
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Invalid signature'), 401);
        }

        // Log the webhook if debug is enabled
        if ($this->debug) {
            $this->log('Webhook received and signature verified: ' . print_r($request->get_params(), true));
        }
        
        // Get the request body
        $payload = $request->get_json_params();
        
        // Accept payloads that contain either transactionNumber (preferred) or transactionId (fallback)
        if (empty($payload) || (!isset($payload['transactionId']) && !isset($payload['transactionNumber'])) || !isset($payload['status'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid payload'
            ), 400);
        }
        
        // Get transaction details
        $transaction_number = isset($payload['transactionNumber']) ? sanitize_text_field($payload['transactionNumber']) : '';
        $transaction_id = isset($payload['transactionId']) ? sanitize_text_field($payload['transactionId']) : '';
        $status = sanitize_text_field($payload['status']);
        $escrow_code = isset($payload['escrowCode']) ? sanitize_text_field($payload['escrowCode']) : '';
        $external_reference = isset($payload['externalReference']) ? sanitize_text_field($payload['externalReference']) : '';

        // Normalize status and escrowStatus for idempotency keys. Providers may vary formatting (e.g., "In Progress", "inprogress").
        // Preserve raw $status / $escrow_status_raw for order notes and business logic; normalized versions are for key generation only.
        $escrow_status_raw = isset($payload['escrowStatus']) ? sanitize_text_field($payload['escrowStatus']) : '';
        $normalized_status = preg_replace('/\s+/', '', strtolower(trim($status)));
        $normalized_escrow_status = !empty($escrow_status_raw) ? preg_replace('/\s+/', '', strtolower(trim($escrow_status_raw))) : '';        
        // Decide which identifier to use when verifying with the API (prefer transactionNumber)
        $identifier_for_status = !empty($transaction_number) ? $transaction_number : $transaction_id;

        // Verify transaction status with API
        $transaction_status = $this->api->get_transaction_status($identifier_for_status);
        
        if (is_wp_error($transaction_status)) {
            if ($this->debug) {
                $this->log('Transaction status verification failed: ' . $transaction_status->get_error_message());
            }
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Failed to verify transaction status'
            ), 500);
        }

        // If we only had a transactionId and API returned a transactionNumber, lazy-migrate it
        if (empty($transaction_number)) {
            if (!empty($transaction_status['transactionNumber'])) {
                $transaction_number = sanitize_text_field($transaction_status['transactionNumber']);
                $this->log("Lazy-migrated transactionNumber from status API: $transaction_number", true);
            } elseif (!empty($transaction_status['data']['transactionNumber'])) {
                $transaction_number = sanitize_text_field($transaction_status['data']['transactionNumber']);
                $this->log("Lazy-migrated transactionNumber from status API (nested data): $transaction_number", true);
            }
        }

        // === IDEMPOTENCY PROTECTION ===
        // Use transactionNumber when available, otherwise fallback to transactionId for keying.
        $identifier_for_key = !empty($transaction_number) ? $transaction_number : $transaction_id;
        if (!empty($identifier_for_key)) {
            // Build idempotency key using normalized status (and normalized escrow status if present)
            // Normalization ensures identical events are deduplicated even if the provider varies formatting.
            $id_key_raw = $identifier_for_key . '|' . $normalized_status;
            if (!empty($normalized_escrow_status)) {
                $id_key_raw .= '|' . $normalized_escrow_status;
            }
            $id_key = 'payscrow_webhook_processed_' . md5($id_key_raw);
            $inflight_key = $id_key . '_inflight';

            // If already processed, return success (idempotent)
            if (get_transient($id_key)) {
                $this->log("Duplicate webhook ignored for identifier: " . $identifier_for_key . ' status: ' . $normalized_status);
                return new WP_REST_Response(array(
                    'status' => 'success',
                    'message' => 'Duplicate webhook ignored'
                ), 200);
            }

            // If another process is handling it, short-circuit
            if (get_transient($inflight_key)) {
                $this->log("Webhook already being processed (inflight) for identifier: " . $identifier_for_key);
                return new WP_REST_Response(array(
                    'status' => 'success',
                    'message' => 'Webhook accepted (processing)'
                ), 202);
            }

            // Set inflight marker for short time to prevent race conditions
            set_transient($inflight_key, time(), 30);
        }

        // Make sure status matches what we got in webhook
        if (isset($transaction_status['status']) && $transaction_status['status'] !== $status) {
            if ($this->debug) {
                $this->log('Status mismatch. Webhook: ' . $status . ', API: ' . $transaction_status['status']);
            }
            
            // Clear inflight marker on early exit
            if (!empty($inflight_key)) {
                delete_transient($inflight_key);
            }

            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Status mismatch'
            ), 400);
        }
        
        // Lookup order by _payscrow_transaction_number first (canonical)
        $orders = array();
        if (!empty($transaction_number)) {
            $orders = wc_get_orders(array(
                'meta_query' => array(
                    array(
                        'key'     => '_payscrow_transaction_number',
                        'value'   => $transaction_number,
                        'compare' => '='
                    )
                ),
                'limit'      => 1
            ));
        }

        // If not found by number, fall back to transaction_id meta
        if (empty($orders) && !empty($transaction_id)) {
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
        }
        
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
            
            // Clear inflight marker before returning
            if (!empty($inflight_key)) {
                delete_transient($inflight_key);
            }

            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Order not found. Transaction ID: ' . $transaction_id
            ), 404);
        }
        
// Perform processing in a try/catch to ensure inflight transient is cleared on unexpected errors
        try {
            $order = $orders[0];
            
            // If we obtained a transactionNumber during this webhook (or via status lookup) and the order
            // does not yet have it, persist it for faster future lookups (lazy migration).
            if (!empty($transaction_number)) {
                $existing = $order->get_meta('_payscrow_transaction_number', true);
                if (empty($existing)) {
                    $order->update_meta_data('_payscrow_transaction_number', $transaction_number);
                    $order->save();
                    $order->add_order_note(sprintf(__('PayScrow Transaction Number (migrated): %s', 'payscrow-woocommerce-escrow'), $transaction_number));
                    $this->log("Persisted transactionNumber $transaction_number for order #" . $order->get_id());
                }
            }
        
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

        // Mark webhook as processed (idempotency) and clear inflight marker
        if (!empty($identifier_for_key) && !empty($id_key)) {
            // Persist that we've processed this event for 7 days
            set_transient($id_key, time(), DAY_IN_SECONDS * 7);

            // Remove inflight marker
            if (!empty($inflight_key)) {
                delete_transient($inflight_key);
            }
        }

        // Return success response
        return new WP_REST_Response(array(
            'status' => 'success',
            'message' => 'Webhook processed successfully'
        ), 200);
        
        } catch (Exception $e) {
            // Ensure inflight marker is cleared if something unexpected happens
            if (!empty($inflight_key)) {
                delete_transient($inflight_key);
            }

            $this->log('Webhook processing exception: ' . $e->getMessage(), true);
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Webhook processing error'
            ), 500);
        }
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
