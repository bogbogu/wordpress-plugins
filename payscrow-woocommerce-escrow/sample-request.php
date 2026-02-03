<?php
/**
 * Sample request for PayScrow API
 * 
 * This is a standalone script that shows the format of the request 
 * being sent to PayScrow's API without requiring WordPress.
 */

// Sample API key (replace with your actual key)
$api_key = "YOUR_BROKER_API_KEY";

// Sample data
$sample_data = array(
    'items' => array(
        array(
            'name' => 'Test Product',
            'description' => 'Test Product Description', // No HTML tags
            'quantity' => 1,
            'price' => 100.00 // As a number, not string
        )
    ),
    'merchantEmailAddress' => 'merchant@example.com',
    'merchantName' => 'Sample Merchant',
    'customerName' => 'Test Customer',
    'customerEmailAddress' => 'customer@example.com',
    'transactionReference' => 'TEST' . time(),
    'merchantChargePercentage' => 10.0, // As a number
    'currencyCode' => 'NGN',
    'returnUrl' => 'https://example.com/return',
    'webhookNotificationUrl' => 'https://example.com/webhook',
    'settlementAccounts' => array(
        array(
            'accountName' => 'Test Account',
            'accountNumber' => '1234567890',
            'bankCode' => '123',
            'amount' => 90.00 // As a number, not string
        )
    )
);

// Add optional fields properly
$sample_data['merchantPhoneNo'] = '1234567890';  // Add only if available
$sample_data['customerPhoneNo'] = '0987654321';  // Add only if available
$sample_data['merchantNIN'] = null;  // Use null instead of empty string
$sample_data['customerNIN'] = null;  // Use null instead of empty string

// API endpoint
$api_url = 'https://payscrow.net/api/v3/marketplace/transactions/start';

// Headers
$headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'BrokerApiKey: ' . $api_key
);

// Format request as JSON
$request_body = json_encode($sample_data, JSON_PRETTY_PRINT);

// Display sample curl command
echo "Sample curl command to test the API:\n\n";
echo "curl -X POST \\\n";
echo "  \"$api_url\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"Accept: application/json\" \\\n";
echo "  -H \"BrokerApiKey: {YOUR_API_KEY}\" \\\n";
echo "  -d '$request_body'\n\n";

// Display complete request information
echo "Complete Request Details:\n";
echo "----------------------\n";
echo "HTTP Method: POST\n";
echo "URL: $api_url\n\n";

echo "Headers:\n";
foreach ($headers as $header) {
    // Don't display actual API key
    if (strpos($header, 'BrokerApiKey:') === 0) {
        echo "BrokerApiKey: {YOUR_API_KEY}\n";
    } else {
        echo "$header\n";
    }
}

echo "\nRequest Body:\n";
echo $request_body;

echo "\n\nThis request matches the PayScrow v3 API documentation format.\n";
echo "To use this request in testing, replace {YOUR_API_KEY} with your actual PayScrow Broker API Key.\n";