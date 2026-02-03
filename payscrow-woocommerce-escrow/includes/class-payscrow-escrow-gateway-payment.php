<?php

/**
 * Define the payment gateway functionality
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 */

/**
 * The payment gateway functionality.
 *
 * Defines the payment gateway, settings, and processing functions
 *
 * @package    PayScrow_WC_Escrow
 * @subpackage PayScrow_WC_Escrow/includes
 * @author     Your Name <email@example.com>
 */
class PayScrow_WC_Escrow_Payment
{

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
     * The settings for this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    The settings for this plugin.
     */
    private $settings;

    /**
     * API instance
     *
     * @since    1.0.0
     * @access   private
     * @var      PayScrow_WC_Escrow_API    $api    API instance
     */
    private $api;

    /**
     * Debug mode
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug    Whether debug mode is enabled
     */
    private $debug;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings = get_option('payscrow_wc_escrow_settings', array());
        $this->debug = isset($this->settings['debug_mode']) && $this->settings['debug_mode'] === 'yes';
        $this->api = new PayScrow_WC_Escrow_API();

        // Initialize the WooCommerce payment gateway class
        $this->init_gateway_class();

        // Register our actions through WooCommerce hooks instead of directly in constructor
        add_action('init', array($this, 'register_gateway_hooks'));
    }

    /**
     * Register gateway hooks at the proper time
     */
    public function register_gateway_hooks()
    {
        // Register the payment gateway with WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
    }

    /**
     * Add gateway class to WooCommerce
     *
     * @since    1.0.0
     * @param    array    $gateways    Payment gateway classes
     * @return   array                 Payment gateway classes
     */
    public function add_gateway_class($gateways)
    {
        $gateways[] = 'WC_Gateway_PayScrow_Escrow';
        return $gateways;
    }

    /**
     * Initialize the gateway class
     *
     * @since    1.0.0
     */
    private function init_gateway_class()
    {
        // Include the gateway class file
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/gateways/class-wc-gateway-payscrow-escrow.php';
    }

    /**
     * Process escrow payment after order is created
     *
     * @since    1.0.0
     * @param    int       $order_id    Order ID
     * @param    array     $posted_data Posted data
     * @param    WC_Order  $order       Order object
     */
    public function process_escrow_payment($order_id, $posted_data, $order)
    {

        $this->log("process_escrow_payment triggered for order #$order_id", true);

        // Only process if our payment method is selected
        if ($order->get_payment_method() !== 'payscrow_wc_escrow') {
            return;
        }

        if ($this->debug) {
            $this->log("Processing payment for order #$order_id");
        }

        // Build API request data
        $api_data = $this->build_api_request_data($order);

        $this->log("Payment method for order: " . $order->get_payment_method(), true);
        $this->log("Raw API payload:", true);
        $this->log(print_r($api_data, true), true);


        if (is_wp_error($api_data)) {
            $error_message = $api_data->get_error_message();
            $order->add_order_note(__('Escrow payment failed: ', 'payscrow-woocommerce-escrow') . $error_message);
            wc_add_notice($error_message, 'error');
            return;
        }

        // Always log the request data when having payment issues
        $this->log("PayScrow API request data for order #$order_id: " . json_encode($api_data), true);

        // Make API request to start transaction
        $response = $this->api->start_transaction($api_data);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_data = $response->get_error_data();

            // Build detailed message for admin (private)
            $admin_message = __('Escrow payment API error: ', 'payscrow-woocommerce-escrow') . $error_message;
            if (!empty($error_data) && is_array($error_data)) {
                $admin_message .= ' - Details: ' . json_encode($error_data);
            }

            // Add debug info to order notes (only visible to admin)
            $order->add_order_note($admin_message);

            // User-friendly error message (public)
            $user_message = __('Payment error: ', 'payscrow-woocommerce-escrow');

            // Add specific troubleshooting suggestions based on error type
            if (strpos($error_message, 'HTTP 400') !== false) {
                $user_message .= __('The payment could not be processed due to an issue with the request. This might be due to missing information or an API configuration issue. Please try again or contact support.', 'payscrow-woocommerce-escrow');
            } elseif (strpos($error_message, 'HTTP 401') !== false || strpos($error_message, 'HTTP 403') !== false) {
                $user_message .= __('Authentication failed with the payment processor. Please contact the store administrator to verify the API settings.', 'payscrow-woocommerce-escrow');
            } elseif (strpos($error_message, 'connect') !== false) {
                $user_message .= __('Could not connect to the payment processor. Please try again later or use a different payment method.', 'payscrow-woocommerce-escrow');
            } else {
                // Generic fallback error
                $user_message .= $error_message;
            }

            // Show error to user
            wc_add_notice($user_message, 'error');

            // Always log payment errors with full details
            $this->log("API Error for order #$order_id: " . $error_message, true);
            if (!empty($error_data)) {
                $this->log("API Error data for order #$order_id: " . json_encode($error_data), true);
            }

            return;
        }

        // Log the successful response
        $this->log("PayScrow API response for order #$order_id: " . json_encode($response), true);

        // Check for different response formats - PayScrow might return different formats
        $payment_link = null;
        $transaction_id = null;

        // Try to extract payment link and transaction ID from response
        if (!empty($response['paymentLink'])) {
            $payment_link = $response['paymentLink'];
            $transaction_id = !empty($response['transactionNo']) ? $response['transactionNo'] : null;
        } elseif (!empty($response['redirectUrl'])) {
            $payment_link = $response['redirectUrl'];
            $transaction_id = !empty($response['transactionId']) ? $response['transactionId'] : null;
        } elseif (!empty($response['data']) && is_array($response['data'])) {
            // Some APIs nest the data
            if (!empty($response['data']['paymentLink'])) {
                $payment_link = $response['data']['paymentLink'];
                $transaction_id = !empty($response['data']['transactionNo']) ? $response['data']['transactionNo'] : null;
            } elseif (!empty($response['data']['redirectUrl'])) {
                $payment_link = $response['data']['redirectUrl'];
                $transaction_id = !empty($response['data']['transactionId']) ? $response['data']['transactionId'] : null;
            }
        }

        // Check if we got a payment link
        if (empty($payment_link)) {
            $error_message = __('No payment link returned from escrow service. Please check API configuration.', 'payscrow-woocommerce-escrow');
            $order->add_order_note(__('Escrow payment error: ', 'payscrow-woocommerce-escrow') . $error_message);
            wc_add_notice(__('Payment error: ', 'payscrow-woocommerce-escrow') . $error_message, 'error');

            // Always log this error
            $this->log("Missing payment link for order #$order_id - Response: " . json_encode($response), true);

            return;
        }

        // Store transaction ID in order meta (compatible with HPOS)
        if (!empty($transaction_id)) {
            $order->update_meta_data('_payscrow_transaction_id', $transaction_id);
            $order->save();
            $order->add_order_note(sprintf(__('PayScrow Transaction ID: %s', 'payscrow-woocommerce-escrow'), $transaction_id));
            $this->log("Stored transaction ID $transaction_id for order #$order_id");
        } else {
            // Still store the raw response if we couldn't find a transaction ID
            $order->update_meta_data('_payscrow_response', json_encode($response));
            $order->save();
            $this->log("No transaction ID found in response for order #$order_id", true);
        }

        // Update order status to pending payment
        $order->update_status('pending', __('Awaiting escrow payment', 'payscrow-woocommerce-escrow'));

        // Log the redirect
        $this->log("Redirecting to PayScrow payment page: $payment_link");

        // Return the payment link to process_payment() for redirection
        return $payment_link;  // Return the link to the caller
    }

    /**
     * Format a phone number to valid Nigerian format
     *
     * @since    1.0.0
     * @param    string  $phone    Phone number to format
     * @return   string            Properly formatted Nigerian phone number
     */
    private function format_nigerian_phone($phone)
    {
        // Remove all non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Start with formatting
        if (substr($phone, 0, 3) === '234') {
            // International format without +
            $phone = '0' . substr($phone, 3);
        } elseif (substr($phone, 0, 1) === '+' && substr($phone, 1, 3) === '234') {
            // International format with +
            $phone = '0' . substr($phone, 4);
        } elseif (substr($phone, 0, 1) === '1' || substr($phone, 0, 1) === '2') {
            // If starts with 1 or 2 (non-Nigerian format), replace with standard
            $phone = '0801234' . substr($phone, 0, 4);
        } elseif (substr($phone, 0, 1) !== '0') {
            // If doesn't start with 0, add it
            $phone = '0' . $phone;
        }

        // Ensure it's 11 digits (standard Nigerian mobile number)
        $phone = substr(str_pad($phone, 11, '0', STR_PAD_RIGHT), 0, 11);

        // Check if valid Nigerian mobile prefix
        $valid_prefixes = ['070', '071', '080', '081', '090', '091'];
        $prefix = substr($phone, 0, 3);

        if (!in_array($prefix, $valid_prefixes)) {
            // If invalid prefix, use a standard one
            $phone = '080' . substr($phone, 3);
        }

        return $phone;
    }

    /**
     * Build API request data for PayScrow
     *
     * @since    1.0.0
     * @param    WC_Order  $order    Order object
     * @return   array|WP_Error     Request data or error
     */
    private function build_api_request_data($order)
    {
        if ($this->debug) {
            $this->log("Building API data for order #" . $order->get_id());
        }

        // Get admin account settings
        $admin_account_name = isset($this->settings['admin_account_name']) ? $this->settings['admin_account_name'] : '';
        $admin_account_number = isset($this->settings['admin_account_number']) ? $this->settings['admin_account_number'] : '';
        $admin_bank_code = isset($this->settings['admin_bank_code']) ? $this->settings['admin_bank_code'] : '';
        $admin_percentage = isset($this->settings['admin_percentage']) ? floatval($this->settings['admin_percentage']) : 10;

        // Validate admin account
        if (empty($admin_account_name) || empty($admin_account_number) || empty($admin_bank_code)) {
            return new WP_Error('invalid_admin_account', __('Admin account details are not properly configured.', 'payscrow-woocommerce-escrow'));
        }

        // Process items and collect vendor accounts
        $items = array();
        $vendor_percentages = array();
        $order_total = 0;

        // Get order total from the beginning
        $order_total = round($order->get_total(), 2);

        // Calculate admin amount (percentage of total) with proper decimal formatting
        $admin_amount = floatval(number_format($order_total * ($admin_percentage / 100), 2, '.', ''));

        // Initialize settlementAccounts array (we'll add all accounts later after calculations)

        // Get items from the order
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $product->get_id();

            // Get vendor ID (post author)
            $vendor_id = get_post_field('post_author', $product_id);

            // Skip if no vendor ID
            if (!$vendor_id) {
                continue;
            }

            // Get vendor bank details
            $vendor_account_name = get_user_meta($vendor_id, 'payscrow_account_name', true);
            $vendor_account_number = get_user_meta($vendor_id, 'payscrow_account_number', true);
            $vendor_bank_code = get_user_meta($vendor_id, 'payscrow_bank_code', true);

            // Validate vendor account
            if (empty($vendor_account_name) || empty($vendor_account_number) || empty($vendor_bank_code)) {
                $vendor_user = get_userdata($vendor_id);
                $vendor_name = $vendor_user ? $vendor_user->display_name : "Vendor #$vendor_id";
                return new WP_Error('invalid_vendor_account', sprintf(__('Vendor "%s" has incomplete bank details.', 'payscrow-woocommerce-escrow'), $vendor_name));
            }

            // Calculate item total
            $item_total = $item->get_total() + $item->get_total_tax();
            $order_total += $item_total;

            // Add to vendor percentages
            if (!isset($vendor_percentages[$vendor_id])) {
                $vendor_percentages[$vendor_id] = array(
                    'accountName' => $vendor_account_name,
                    'accountNumber' => $vendor_account_number,
                    'bankCode' => $vendor_bank_code,
                    'total' => 0,
                    'isAdminAccount' => false
                );
            }

            $vendor_percentages[$vendor_id]['total'] += $item_total;

            // Get description and clean it thoroughly for API submission
            $description = $product->get_short_description() ? $product->get_short_description() : $item->get_name();

            // First strip HTML tags
            $description = strip_tags($description);

            // Remove non-breaking spaces and other problematic Unicode characters
            $description = preg_replace('/\xA0|\x{00A0}|\s+/u', ' ', $description);

            // Normalize whitespace (convert multiple spaces to single space)
            $description = preg_replace('/\s+/', ' ', $description);

            // Trim whitespace from beginning and end
            $description = trim($description);

            // Remove any remaining non-printable characters
            $description = preg_replace('/[^\P{C}\n]+/u', '', $description);

            if ($this->debug) {
                // Log the original and cleaned description for debugging
                $original = $product->get_short_description() ? $product->get_short_description() : $item->get_name();
                $this->log("Original description: " . bin2hex($original));
                $this->log("Cleaned description: " . bin2hex($description));
            }

            // Clean the item name as well
            $cleaned_name = $item->get_name();
            $cleaned_name = preg_replace('/\xA0|\x{00A0}|\s+/u', ' ', $cleaned_name);
            $cleaned_name = preg_replace('/\s+/', ' ', $cleaned_name);
            $cleaned_name = trim($cleaned_name);
            $cleaned_name = preg_replace('/[^\P{C}\n]+/u', '', $cleaned_name);

            // Add item to items array with properly cleaned text and decimal formats
            $items[] = array(
                'name' => $cleaned_name,
                'description' => $description,
                'quantity' => $item->get_quantity(),
                'price' => floatval(number_format($item->get_total() / $item->get_quantity(), 2, '.', ''))
            );
        }

        // === Add shipping as an item (only if shipping cost > 0 or method exists) ===
        $shipping_total = $order->get_shipping_total();
        $shipping_methods = $order->get_shipping_methods();

        if (!empty($shipping_methods) && $shipping_total >= 0) {
            foreach ($shipping_methods as $shipping_item) {
                $shipping_name = $shipping_item->get_name(); // e.g., "Local Pickup"

                // Always include shipping as item — even if 0 — for transparency
                $items[] = array(
                    'name'        => $shipping_name,
                    'description' => 'Shipping via ' . $shipping_name,
                    'quantity'    => 1,
                    'price'       => floatval(number_format($shipping_total, 2, '.', ''))
                );
            }
        }



        // Get admin account info from settings (will be used later for settlement accounts)
        $admin_account_name = isset($this->settings['admin_account_name']) ? $this->settings['admin_account_name'] : get_bloginfo('name');
        $admin_account_number = isset($this->settings['admin_account_number']) ? $this->settings['admin_account_number'] : '';
        $admin_bank_code = isset($this->settings['admin_bank_code']) ? $this->settings['admin_bank_code'] : '';

        // Validate admin account details
        if (empty($admin_account_name) || empty($admin_account_number) || empty($admin_bank_code)) {
            return new WP_Error('invalid_admin_account', __('Admin bank account details are missing. Please configure them in the plugin settings.', 'payscrow-woocommerce-escrow'));
        }

        if (empty($items)) {
            return new WP_Error('no_items', __('No valid items found in order.', 'payscrow-woocommerce-escrow'));
        }

        // Calculate total amount
        $total_amount = round($order->get_total(), 2);

        // Return URLs
        $return_url = wc_get_endpoint_url('order-received', $order->get_id(), wc_get_checkout_url());
        $return_url = add_query_arg('key', $order->get_order_key(), $return_url);

        // Build webhook URL
        $webhook_url = get_rest_url(null, 'payscrow/escrow/v1/webhook');

        // Merchant info (site info) - ensure we have values
        $merchant_name = get_bloginfo('name');
        if (empty($merchant_name)) {
            $merchant_name = 'PayScrow Marketplace'; // Fallback to a default value if empty
        }

        $merchant_email = get_option('admin_email');

        // Get merchant phone from settings if available
        $merchant_phone = isset($this->settings['merchant_phone']) ? $this->settings['merchant_phone'] : '';
        if (empty($merchant_phone)) {
            // Use a realistic Nigerian phone number format (080xxxxxxxx)
            $merchant_phone = '08012345678'; // Fallback to a valid Nigerian phone number format
        }

        // Ensure the merchant phone has the correct Nigerian format
        $merchant_phone = $this->format_nigerian_phone($merchant_phone);

        // Log the merchant phone processing
        if ($this->debug) {
            $this->log("Formatted merchant phone: " . $merchant_phone);
        }

        $this->log("Using merchant info: Name=$merchant_name, Email=$merchant_email, Phone=$merchant_phone", true);

        // Get admin percentage for merchant charge
        $admin_percentage = isset($this->settings['admin_percentage']) ? floatval($this->settings['admin_percentage']) : 10;

        // Format request data according to PayScrow API v3 specifications
        // Create a unique reference with prefix to avoid collisions
        $unique_ref = 'KM' . $order->get_id() . 'T' . time();

        // Calculate total item value for proper settlement distribution
        $total_item_value = 0;
        foreach ($items as $item) {
            $total_item_value += $item['price'] * $item['quantity'];
        }

        // Ensure total_item_value has exactly 2 decimals
        $total_item_value = floatval(number_format($total_item_value, 2, '.', ''));

        if ($this->debug) {
            $this->log("Total item value: " . $total_item_value);
        }

        // Calculate exact merchant (admin) amount
        $admin_amount = floatval(number_format($total_item_value * ($admin_percentage / 100), 2, '.', ''));

        // Calculate remaining amount for vendors (should be exactly total minus admin amount)
        $vendor_amount = floatval(number_format($total_item_value - $admin_amount, 2, '.', ''));

        if ($this->debug) {
            $this->log("Admin amount: " . $admin_amount . " (" . $admin_percentage . "%)");
            $this->log("Vendor amount: " . $vendor_amount);
        }

        // Create settlement accounts with correct values
        $formatted_settlement_accounts = array();

        // Add admin account
        $formatted_settlement_accounts[] = array(
            'accountName' => $admin_account_name,
            'accountNumber' => $admin_account_number,
            'bankCode' => $admin_bank_code,
            'amount' => $admin_amount
        );

        // If we have only one vendor, add them with the entire vendor amount
        if (count($vendor_percentages) === 1) {
            $vendor_data = reset($vendor_percentages);
            $formatted_settlement_accounts[] = array(
                'accountName' => $vendor_data['accountName'],
                'accountNumber' => $vendor_data['accountNumber'],
                'bankCode' => $vendor_data['bankCode'],
                'amount' => $vendor_amount
            );
        }
        // If we have multiple vendors, distribute proportionally
        elseif (count($vendor_percentages) > 1) {
            $remaining_vendor_amount = $vendor_amount;
            $vendor_count = count($vendor_percentages);
            $current_vendor = 0;

            foreach ($vendor_percentages as $vendor_id => $vendor_data) {
                $current_vendor++;

                // For the last vendor, use the remaining amount to avoid rounding errors
                if ($current_vendor === $vendor_count) {
                    $vendor_settlement_amount = $remaining_vendor_amount;
                } else {
                    // Calculate this vendor's proportion of the total vendor sales
                    $vendor_proportion = $vendor_data['total'] / $order_total;
                    $vendor_settlement_amount = floatval(number_format($vendor_amount * $vendor_proportion, 2, '.', ''));
                    $remaining_vendor_amount -= $vendor_settlement_amount;
                }

                $formatted_settlement_accounts[] = array(
                    'accountName' => $vendor_data['accountName'],
                    'accountNumber' => $vendor_data['accountNumber'],
                    'bankCode' => $vendor_data['bankCode'],
                    'amount' => $vendor_settlement_amount
                );
            }
        }

        // Double-check that settlement totals exactly match item total
        $settlement_total = 0;
        foreach ($formatted_settlement_accounts as $account) {
            $settlement_total += $account['amount'];
        }

        // Log verification
        if ($this->debug) {
            $this->log("Settlement verification - Item total: " . $total_item_value . ", Settlement total: " . $settlement_total);
            if (abs($total_item_value - $settlement_total) > 0.001) {
                $this->log("WARNING: Settlement total does not match item total. Difference: " . ($total_item_value - $settlement_total));
            } else {
                $this->log("Settlement verification passed - Amounts match exactly.");
            }
        }

        // Format items as expected by API with proper decimal formatting
        $formatted_items = array();
        foreach ($items as $item) {
            // Make sure price is a proper decimal with 2 decimal places
            $price = floatval(number_format((float)$item['price'], 2, '.', ''));

            $formatted_items[] = array(
                'name' => $item['name'],
                'description' => $item['description'],
                'quantity' => (int)$item['quantity'],
                'price' => $price
            );

            if ($this->debug) {
                $this->log("Formatted item: {$item['name']}, Price: {$price}, Quantity: {$item['quantity']}");
            }
        }

        // Final request data structure aligned with PayScrow v3 API documentation
        $request_data = array(
            'items' => $formatted_items,
            'merchantEmailAddress' => $merchant_email,
            'merchantName' => $merchant_name,
            'transactionReference' => $unique_ref,
            'merchantChargePercentage' => (float)$admin_percentage,
            'currencyCode' => $order->get_currency(),
            'returnUrl' => $return_url,
            'webhookNotificationUrl' => $webhook_url,
            'settlementAccounts' => $formatted_settlement_accounts,
            'customerName' => $order->get_formatted_billing_full_name(),
            'customerEmailAddress' => $order->get_billing_email()
        );

        // Add optional fields only if they have values
        // For phone numbers
        if (!empty($merchant_phone)) {
            $request_data['merchantPhoneNo'] = $merchant_phone;
        }

        // Process customer phone number
        $customer_phone = $order->get_billing_phone();

        if (!empty($customer_phone)) {
            $request_data['customerPhoneNo'] = $this->format_nigerian_phone($customer_phone);
        } else {
            // Use a standard Nigerian phone number as fallback
            $request_data['customerPhoneNo'] = '08012345678';
        }

        // Log the customer phone processing
        if ($this->debug) {
            $this->log("Original customer phone: " . $order->get_billing_phone());
            $this->log("Formatted customer phone: " . $request_data['customerPhoneNo']);
        }

        // For NIN fields - set to null if empty
        $request_data['merchantNIN'] = null;
        $request_data['customerNIN'] = null;

        if ($this->debug) {
            $this->log("API Request data for order #" . $order->get_id() . ": " . json_encode($request_data));
        }

        return $request_data;
    }

    /**
     * Add escrow code field at checkout
     *
     * @since    1.0.0
     */
    public function add_escrow_code_field()
    {
        // Only show if our payment gateway is enabled
        if (WC()->session && WC()->session->get('chosen_payment_method') === 'payscrow_wc_escrow') {
            wc_get_template(
                'checkout/escrow-code-field.php',
                array(
                    'escrow_code' => WC()->session->get('escrow_code'),
                ),
                '',
                plugin_dir_path(dirname(__FILE__)) . 'public/partials/'
            );

            // Add JavaScript for escrow code handling
            wp_enqueue_script($this->plugin_name);
        }
    }

    /**
     * Apply escrow code discount
     *
     * @since    1.0.0
     * @param    WC_Cart  $cart    Cart object
     */
    public function apply_escrow_code_discount($cart)
    {
        if (!WC()->session) {
            return;
        }

        // Get escrow code from session
        $escrow_code = WC()->session->get('escrow_code');

        if (empty($escrow_code)) {
            return;
        }

        // Check if code is already verified
        $escrow_code_data = WC()->session->get('escrow_code_data');

        if (!$escrow_code_data) {
            // Verify code if not already verified
            $response = $this->api->verify_escrow_code($escrow_code);

            if (is_wp_error($response) || !isset($response['isValid']) || !$response['isValid']) {
                // Code is invalid, remove from session
                WC()->session->set('escrow_code', null);
                WC()->session->set('escrow_code_data', null);
                return;
            }

            // Store verified code data
            $escrow_code_data = $response;
            WC()->session->set('escrow_code_data', $escrow_code_data);
        }

        // Apply discount if valid
        if (isset($escrow_code_data['isValid']) && $escrow_code_data['isValid'] && isset($escrow_code_data['amount'])) {
            $discount_amount = floatval($escrow_code_data['amount']);

            if ($discount_amount > 0) {
                $cart->add_fee(
                    sprintf(__('Escrow Code Discount (%s)', 'payscrow-woocommerce-escrow'), $escrow_code),
                    -$discount_amount,
                    false
                );
            }
        }
    }

    /**
     * AJAX handler for applying escrow code
     *
     * @since    1.0.0
     */
    public function apply_escrow_code_ajax()
    {
        check_ajax_referer('apply-escrow-code', 'security');

        $escrow_code = isset($_POST['escrow_code']) ? sanitize_text_field($_POST['escrow_code']) : '';

        if (empty($escrow_code)) {
            wp_send_json_error(array(
                'message' => __('Please enter an escrow code.', 'payscrow-woocommerce-escrow')
            ));
            return;
        }

        // Verify escrow code
        $response = $this->api->verify_escrow_code($escrow_code);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }

        if (!isset($response['isValid']) || !$response['isValid']) {
            wp_send_json_error(array(
                'message' => __('Invalid escrow code.', 'payscrow-woocommerce-escrow')
            ));
            return;
        }

        // Store in session
        WC()->session->set('escrow_code', $escrow_code);
        WC()->session->set('escrow_code_data', $response);

        // Return success
        wp_send_json_success(array(
            'message' => sprintf(__('Escrow code applied. You will receive a discount of %s.', 'payscrow-woocommerce-escrow'), wc_price($response['amount'])),
            'amount' => $response['amount']
        ));
    }

    /**
     * Log debug information
     *
     * @since    1.0.0
     * @param    string    $message      Message to log
     * @param    bool      $force_log    Whether to log even if debug mode is disabled
     */
    private function log($message, $force_log = false)
    {
        if (!$this->debug && !$force_log) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/payscrow-escrow-logs';
        $log_file = $log_dir . '/payment-' . date('Y-m-d') . '.log';

        // Create directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $time = date('Y-m-d H:i:s');
        error_log("[{$time}] {$message}\n", 3, $log_file);

        // Also log to PHP error log for critical issues
        if ($force_log) {
            error_log("PAYSCROW ESCROW: {$message}");
        }
    }
}
