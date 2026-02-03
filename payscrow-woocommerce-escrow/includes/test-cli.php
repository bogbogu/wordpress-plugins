<?php
/**
 * Command-line test script for PayScrow API integration
 *
 * This file generates a sample API request to test the PayScrow integration
 * and outputs the request and response information to the console.
 * 
 * Usage: php test-cli.php [api_key]
 * If api_key is not provided, it will use the one stored in the database.
 */

// This is a CLI script
if (PHP_SAPI !== 'cli') {
    echo "This script should be run from the command line.";
    exit(1);
}

// Bootstrap WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Load our plugin classes
require_once 'class-payscrow-escrow-gateway-api.php';

// Create API instance
$api = new PayScrow_WC_Escrow_API();

// Get current settings
$settings = get_option('payscrow_wc_escrow_settings', array());

// Override API key if provided as argument
if (isset($argv[1]) && !empty($argv[1])) {
    echo "Using provided API key: " . substr($argv[1], 0, 4) . "..." . substr($argv[1], -4) . "\n";
    $settings['broker_api_key'] = $argv[1];
}

// Enable debug mode for this test
$settings['debug_mode'] = 'yes';
update_option('payscrow_wc_escrow_settings', $settings);

echo "PayScrow API Test Request\n";
echo "-------------------------\n";

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
    'webhookNotificationUrl' => get_rest_url(null, 'payscrow/escrow/v1/webhook'),
    'settlementAccounts' => array(
        array(
            'accountName' => 'Test Account',
            'accountNumber' => '1234567890',
            'bankCode' => '123',
            'amount' => 90.00 // As number, not string
        )
    )
);

// Add optional fields properly
$sample_data['merchantPhoneNo'] = '1234567890'; // Only if available
$sample_data['customerPhoneNo'] = '0987654321'; // Only if available
$sample_data['merchantNIN'] = null; // Use null instead of empty string
$sample_data['customerNIN'] = null; // Use null instead of empty string

echo "Request Data:\n";
echo json_encode($sample_data, JSON_PRETTY_PRINT) . "\n\n";

// Make the API request
echo "Sending API request...\n";

try {
    // Use our public request method
    $endpoint = 'marketplace/transactions/start';
    $response = $api->request($endpoint, 'POST', $sample_data);
    
    echo "Response:\n";
    if (is_wp_error($response)) {
        echo "Error: " . $response->get_error_message() . "\n";
        if ($response->get_error_data()) {
            echo "Error Data: " . print_r($response->get_error_data(), true) . "\n";
        }
    } else {
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Get the log file location
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/payscrow-escrow-logs';
$log_file = $log_dir . '/payscrow-api-' . date('Y-m-d') . '.log';

if (file_exists($log_file)) {
    echo "\nLog File Location: " . $log_file . "\n";
    echo "Last 50 lines of log file:\n";
    echo "-------------------------\n";
    
    // Get the last 50 lines of the log file
    $log_content = file($log_file);
    $log_lines = array_slice($log_content, -50);
    foreach ($log_lines as $line) {
        echo $line;
    }
}

// Reset debug mode setting
$settings['debug_mode'] = isset($settings['debug_mode_original']) ? $settings['debug_mode_original'] : 'no';
update_option('payscrow_wc_escrow_settings', $settings);

echo "\nTest completed.\n";