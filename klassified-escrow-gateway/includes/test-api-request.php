<?php
/**
 * Test script for PayScrow API integration
 *
 * This file generates a sample API request to test the PayScrow integration
 * Use it for debugging purposes only.
 */

// Bootstrap WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Ensure only admins can access this file
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied' );
}

// Load our plugin classes
require_once 'class-klassified-escrow-gateway-api.php';
require_once 'class-klassified-escrow-gateway-payment.php';

// Create API instance
$api = new Klassified_Escrow_Gateway_API();

// Get current settings
$settings = get_option('klassified_escrow_gateway_settings', array());

// Enable debug mode for this test
$settings['debug_mode'] = 'yes';
update_option('klassified_escrow_gateway_settings', $settings);

echo '<h1>PayScrow API Test Request</h1>';
echo '<p>This page generates a sample API request to test the PayScrow integration.</p>';

// Create sample data (matching expected JSON format with proper data types)
$sample_data = array(
    'items' => array(
        array(
            'name' => 'Test Product',
            'description' => 'Test Product Description', // Plain text, no HTML
            'quantity' => 1,
            'price' => 100.00 // As number
        )
    ),
    'merchantEmailAddress' => get_option('admin_email'),
    'merchantName' => get_bloginfo('name'),
    'customerName' => 'Test Customer',
    'customerEmailAddress' => 'test@example.com',
    'transactionReference' => 'TEST' . time(),
    'merchantChargePercentage' => 10.0, // As number
    'currencyCode' => 'NGN',
    'returnUrl' => home_url(),
    'webhookNotificationUrl' => get_rest_url(null, 'klassified/v1/webhook'),
    'settlementAccounts' => array(
        array(
            'accountName' => 'Test Account',
            'accountNumber' => '1234567890',
            'bankCode' => '123',
            'amount' => 90.00 // As number
        )
    )
);

// Add optional fields properly
$sample_data['merchantPhoneNo'] = '1234567890'; // Only if available
$sample_data['customerPhoneNo'] = '0987654321'; // Only if available
$sample_data['merchantNIN'] = null; // Use null instead of empty string
$sample_data['customerNIN'] = null; // Use null instead of empty string

echo '<h2>Request Data:</h2>';
echo '<pre>' . htmlspecialchars(json_encode($sample_data, JSON_PRETTY_PRINT)) . '</pre>';

// Make the API request
echo '<h2>Sending API request...</h2>';

// Use the make_request method directly to test
try {
    $endpoint = 'marketplace/transactions/start';
    $response = $api->request($endpoint, 'POST', $sample_data);
    
    echo '<h2>Response:</h2>';
    if (is_wp_error($response)) {
        echo '<p>Error: ' . $response->get_error_message() . '</p>';
    } else {
        echo '<pre>' . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) . '</pre>';
    }
} catch (Exception $e) {
    echo '<p>Exception: ' . $e->getMessage() . '</p>';
}

// Get the log file location
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/klassified-escrow-gateway-logs';
$log_file = $log_dir . '/payscrow-api-' . date('Y-m-d') . '.log';

if (file_exists($log_file)) {
    echo '<h2>Log File:</h2>';
    echo '<p>Log file location: ' . $log_file . '</p>';
    echo '<p>Check the log file for complete request and response details.</p>';
}

// Reset debug mode setting
$settings['debug_mode'] = isset($settings['debug_mode_original']) ? $settings['debug_mode_original'] : 'no';
update_option('klassified_escrow_gateway_settings', $settings);