<?php

/**
 * Handle API operations with PayScrow
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/includes
 */

/**
 * Handle API operations with PayScrow.
 *
 * This class defines all the methods needed to communicate with the PayScrow API.
 *
 * @since      1.0.0
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/includes
 * @author     Your Name <email@example.com>
 */
class Klassified_Escrow_Gateway_API {

    /**
     * API base URL
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_base    The base URL for API calls
     */
    private $api_base;

    /**
     * API key
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The API key for authentication
     */
    private $api_key;

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
     */
    public function __construct() {
        $settings = get_option('klassified_escrow_gateway_settings', array());
        
        // Determine if we're in sandbox mode
        $sandbox_mode = isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes';
        
        // Updated API endpoints based on PayScrow's official information
        $this->api_base = $sandbox_mode 
            ? 'https://sandbox.payscrow.net/api/v3/' 
            : 'https://payscrow.net/api/v3/';
            
        // Log the API base URL for troubleshooting
        $this->log("Using official PayScrow API base URL: " . $this->api_base, true);
        
        $this->api_key = isset($settings['broker_api_key']) ? $settings['broker_api_key'] : '';
        $this->debug = isset($settings['debug_mode']) && $settings['debug_mode'] === 'yes';
    }

    /**
     * Start a transaction with PayScrow
     *
     * @since    1.0.0
     * @param    array    $data    Data for the transaction
     * @return   array|WP_Error    Response data or WP_Error
     */
    public function start_transaction($data) {
        $this->log("start_transaction() was triggered with data:", true);
        $this->log(print_r($data, true), true);

        // Correct order: endpoint, method, data
        $endpoint = 'marketplace/transactions/start'; // Replace with actual endpoint string when ready
        return $this->request($endpoint, 'POST', $data);
    }

    
    /**
     * Make direct API request to PayScrow
     * 
     * @since    1.0.0
     * @param    string   $endpoint    API endpoint
     * @param    string   $method      HTTP method
     * @param    array    $data        Request data
     * @return   array|WP_Error        Response data or WP_Error
     */
    public function request($endpoint, $method = 'GET', $data = null) {
        return $this->make_request($method, $endpoint, $data);
    }

    /**
     * Get transaction status
     *
     * @since    1.0.0
     * @param    string    $transaction_id    Transaction ID to check
     * @return   array|WP_Error               Response data or WP_Error
     */
    public function get_transaction_status($transaction_id) {
        // Updated for v3 API format
        $endpoint = 'marketplace/transactions/' . $transaction_id . '/status';
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Verify escrow code
     *
     * @since    1.0.0
     * @param    string    $escrow_code    Escrow code to verify
     * @return   array|WP_Error            Response data or WP_Error
     */
    public function verify_escrow_code($escrow_code) {
        // Updated for v3 API format
        $endpoint = 'marketplace/escrow-codes/' . $escrow_code . '/verify';
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Apply escrow code
     *
     * @since    1.0.0
     * @param    string    $transaction_id    Transaction ID
     * @param    string    $escrow_code       Escrow code to apply
     * @return   array|WP_Error               Response data or WP_Error
     */
    public function apply_escrow_code($transaction_id, $escrow_code) {
        // Updated for v3 API format
        $endpoint = 'marketplace/transactions/apply-code';
        $data = array(
            'transactionId' => $transaction_id,
            'code' => $escrow_code
        );
        return $this->make_request('POST', $endpoint, $data);
    }

    /**
     * Make an API request to PayScrow
     *
     * @since    1.0.0
     * @param    string    $method     HTTP method
     * @param    string    $endpoint   API endpoint
     * @param    array     $data       Data to send (optional)
     * @return   array|WP_Error        Response data or WP_Error
     */
    private function make_request($method, $endpoint, $data = null) {
                // Main API URL

        $this->log("=== make_request called with method: $method, endpoint: $endpoint ===", true);
        $this->log('Incoming $data:', true);
        $this->log(print_r($data, true), true);

        $url = $this->api_base . $endpoint;
        
        // Get whether we're in sandbox mode based on the URL
        $is_sandbox = (strpos($this->api_base, 'sandbox') !== false);
        
        // Define alternative API base URLs to try if the primary one fails
        $alternative_bases = array();
        
        if ($is_sandbox) {
            $alternative_bases = array(
                'https://sandbox.payscrow.net/api/v3/',  // Primary sandbox URL with v3 API
                'https://sandbox-api.payscrow.net/api/v3/',
                'https://sandbox.payscrow.africa/api/v3/',
                'https://sandbox.payscrow.com/api/v3/'
            );
        } else {
            $alternative_bases = array(
                'https://payscrow.net/api/v3/',  // Primary production URL with exact path from PayScrow
                'https://api.payscrow.net/api/v3/',
                'https://payscrow.africa/api/v3/',
                'https://api.payscrow.africa/api/v3/'
            );
        }
        
        // Add the current base at the end as a final fallback
        if (!in_array($this->api_base, $alternative_bases)) {
            $alternative_bases[] = $this->api_base;
        }
        
        // Make sure API key exists and isn't empty
        if (empty($this->api_key)) {
            $this->log("ERROR: No API key configured. Please add your PayScrow API key in the plugin settings.", true);
            return new WP_Error('payscrow_api_error', 'PayScrow API key is missing. Please configure it in the plugin settings.');
        }
        
        // Format API requests for PayScrow v3 API according to documentation
        $headers = array(
            // CRITICAL: Set Content-Type as application/json as required by PayScrow API
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        // Use the documented BrokerApiKey header from the PayScrow specification
        if (!empty($this->api_key)) {
            $headers['BrokerApiKey'] = $this->api_key;
            
            if ($this->debug) {
                $this->log("Using BrokerApiKey header with value: " . substr($this->api_key, 0, 5) . "...");
            }
        }
        
        // Ensure headers are properly cased as some servers are case-sensitive
        // WordPress might convert header keys to lowercase, so we'll add both versions
        // to ensure compatibility with all server configurations
        $normalized_headers = array();
        foreach ($headers as $key => $value) {
            // Add original casing (Content-Type)
            $normalized_headers[$key] = $value;
            
            // Also add lowercase version (content-type) to ensure compatibility
            $normalized_headers[strtolower($key)] = $value;
        }
        
        $args = array(
            'method'  => $method,
            'headers' => $normalized_headers,
            'timeout' => 60, // Increased timeout for potentially slow API responses
            'sslverify' => false // Disable SSL verification temporarily for testing
        );
        
        // Only disable SSL verification in debug mode - we already set it above for testing
        // Keep this commented but available for later use
        // if ($this->debug) {
        //     $args['sslverify'] = false;
        // }
        
        if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
            // Ensure proper decimal formatting for all amounts in JSON
            // $json_options = JSON_NUMERIC_CHECK; Convert numeric strings to numbers

            $this->log('RAW $data before encoding:', true); // Log raw data before encoding
            $this->log(print_r($data, true), true);

            $encoded_body = wp_json_encode($data);
            $this->log('Encoded JSON Body:', true);
            $this->log($encoded_body, true);

            $args['body'] = $encoded_body; // Encode data as JSON

            // Double-check JSON formatting for decimal values
            if ($this->debug) {
                $this->log("Validating proper decimal formatting in JSON payload");
                
                // Check if any price or amount values might need decimal point formatting
                $json_data = json_decode($args['body'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $modified = false;
                    
                    // Check for decimal points in settlement accounts amounts
                    if (isset($json_data['settlementAccounts']) && is_array($json_data['settlementAccounts'])) {
                        foreach ($json_data['settlementAccounts'] as $key => $account) {
                            if (isset($account['amount']) && is_numeric($account['amount'])) {
                                // Ensure amount is a float with 2 decimal places
                                $amount = floatval(number_format((float)$account['amount'], 2, '.', ''));
                                if ($amount != $account['amount']) {
                                    $json_data['settlementAccounts'][$key]['amount'] = $amount;
                                    $modified = true;
                                    $this->log("Settlement account amount corrected from {$account['amount']} to {$amount}");
                                }
                            }
                        }
                    }
                    
                    // Check price in items
                    if (isset($json_data['items']) && is_array($json_data['items'])) {
                        foreach ($json_data['items'] as $i => $item) {
                            if (isset($item['price']) && is_numeric($item['price'])) {
                                // Ensure price is a float with 2 decimal places
                                $price = floatval(number_format((float)$item['price'], 2, '.', ''));
                                if ($price != $item['price']) {
                                    $json_data['items'][$i]['price'] = $price;
                                    $modified = true;
                                    $this->log("Item price corrected from {$item['price']} to {$price}");
                                }
                            }
                        }
                    }
                    
                    // Also clean text fields to remove problematic characters
                    if (isset($json_data['items']) && is_array($json_data['items'])) {
                        foreach ($json_data['items'] as $i => $item) {
                            // Clean item name
                            if (isset($item['name'])) {
                                $cleaned_name = $this->clean_text_for_api($item['name']);
                                if ($cleaned_name !== $item['name']) {
                                    $json_data['items'][$i]['name'] = $cleaned_name;
                                    $modified = true;
                                    $this->log("Item name cleaned from API layer");
                                }
                            }
                            
                            // Clean item description
                            if (isset($item['description'])) {
                                $cleaned_desc = $this->clean_text_for_api($item['description']);
                                if ($cleaned_desc !== $item['description']) {
                                    $json_data['items'][$i]['description'] = $cleaned_desc;
                                    $modified = true;
                                    $this->log("Item description cleaned from API layer");
                                }
                            }
                        }
                    }
                    
                    // Reapply if changes were made
                    if ($modified) {
                        $args['body'] = wp_json_encode($json_data, $json_options);
                        $this->log("JSON payload re-encoded after corrections");
                    }
                }
            }
        }
        
        // Enhanced detailed logging of the complete request for debugging
        $this->log('====== COMPLETE API REQUEST DETAILS ======', true);
        $this->log('API Request URL: ' . $url, true);
        $this->log('API Request Method: ' . $method, true);
        
        // Log all headers (including API key for debugging purposes)
        $this->log('API Request Headers:', true);
        $this->log('=== HEADER DEBUG START ===', true);
        foreach ($args['headers'] as $header_name => $header_value) {
            $this->log('  ' . $header_name . ': ' . $header_value, true);
        }
        
        // Highlight the Content-Type header specifically to verify it's being set correctly
        if (isset($args['headers']['Content-Type'])) {
            $this->log('CRITICAL - Content-Type header is set to: ' . $args['headers']['Content-Type'], true);
        } elseif (isset($args['headers']['content-type'])) {
            $this->log('CRITICAL - content-type (lowercase) header is set to: ' . $args['headers']['content-type'], true);
        } else {
            $this->log('WARNING - No Content-Type header found in any case format!', true);
        }
        $this->log('=== HEADER DEBUG END ===', true);
        
        // Log request body in both raw and formatted JSON if available
        if (isset($args['body'])) {
            $this->log('API Request Body (raw):', true);
            $this->log($args['body'], true);
            
            // Try to parse and format JSON for readability
            $json_data = json_decode($args['body']);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->log('API Request Body (formatted):', true);
                $this->log(print_r($json_data, true), true);
            }
        }
        $this->log('==========================================', true);
        
        // Try all possible PayScrow domain variations
        $this->log('Starting API request with fallback mechanism...', true);
        
        // Variable to track if we tried at least one domain successfully
        $tried_any_domain = false;
        $last_error = null;
        $working_url = null;
        
        // Try each base URL until one works
        foreach ($alternative_bases as $base_url) {
            $full_url = $base_url . $endpoint;
            $test_url = parse_url($full_url, PHP_URL_SCHEME) . '://' . parse_url($full_url, PHP_URL_HOST);
            
            $this->log('Testing connectivity to: ' . $test_url, true);
                        
            $tried_any_domain = true;
            $working_url = $full_url;
            $this->log('Successfully connected to ' . $test_url . ', trying API endpoint', true);
            
            // Now try the actual API endpoint
            $response = wp_remote_request($full_url, $args);
            
            // If successful, break out of the loop
            if (!is_wp_error($response)) {
                $this->log('Successfully connected to API at: ' . $full_url, true);
                
                // If this works, update the API base URL for future requests
                if ($base_url !== $this->api_base) {
                    $this->log('Updating API base URL from ' . $this->api_base . ' to ' . $base_url, true);
                    $this->api_base = $base_url;
                    // We can't permanently save this since this is a static method, but it will work for this request
                }
                
                // Use this URL going forward
                $url = $full_url;
                break;
            }
            
            // Log the error but continue trying other domains
            $this->log('API request to ' . $full_url . ' failed: ' . $response->get_error_message(), true);
            $last_error = $response;
        }
        
        // If we tried domains but all failed
        if ($tried_any_domain && is_wp_error($response)) {
            $domains_tried = implode(', ', array_map(function($base) { 
                return parse_url($base, PHP_URL_HOST); 
            }, $alternative_bases));
            
            $error_message = 'Connection failed to all PayScrow domains (' . $domains_tried . '). Last error: ' . $last_error->get_error_message();
            $this->log($error_message, true);
            return new WP_Error('payscrow_api_error', 'Connection error: ' . $last_error->get_error_message());
        }
        
        // If we couldn't even try any domains (all DNS failures)
        if (!$tried_any_domain) {
            $error_message = 'Cannot connect to any PayScrow server. Please check your internet connection or contact PayScrow support.';
            $this->log($error_message, true);
            return new WP_Error('payscrow_connection_error', $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Enhanced detailed logging of the complete response for debugging
        $this->log('====== COMPLETE API RESPONSE DETAILS ======', true);
        $this->log('API Response Status Code: ' . $response_code, true);
        
        // Log all response headers
        $this->log('API Response Headers:', true);
        foreach ($response_headers as $header_name => $header_value) {
            if (is_array($header_value)) {
                $this->log('  ' . $header_name . ': ' . implode(', ', $header_value), true);
            } else {
                $this->log('  ' . $header_name . ': ' . $header_value, true);
            }
        }
        
        // Log response body with quotes to show whitespace/emptiness
        $this->log('API Response Body (raw):', true);
        $this->log('"' . $response_body . '"', true);
        
        // Try to parse and format JSON for readability if not empty
        if (!empty($response_body)) {
            $json_data = json_decode($response_body);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->log('API Response Body (formatted JSON):', true);
                $this->log(print_r($json_data, true), true);
            }
        } else {
            $this->log('API Response Body is empty', true);
        }
        $this->log('===========================================', true);
        
        // Check for HTTP status code (regardless of empty body)
        if ($response_code >= 400) {
            $error_message = 'Server returned HTTP ' . $response_code;
            
            // Add body details if available
            if (!empty($response_body)) {
                $error_message .= ' with message: ' . $response_body;
            } else {
                $error_message .= ' with empty response body';
            }
            
            $this->log('API Error: ' . $error_message, true);
            return new WP_Error('payscrow_api_error', $error_message);
        }
        
        // Check for empty response separately
        if (empty($response_body)) {
            $this->log('API Error: Empty response body despite HTTP ' . $response_code . ' status', true);
            return new WP_Error('payscrow_api_error', 
                'Empty response from PayScrow API (HTTP ' . $response_code . '). Check API key and URL.');
        }
        
        // Try to decode JSON response
        $response_data = json_decode($response_body, true);
        
        // Check for JSON decoding errors
        if ($response_data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->log('API Error: JSON decode error - ' . json_last_error_msg());
            return new WP_Error('payscrow_api_error', 'Invalid response format. Please check PayScrow API documentation.');
        }
        
        // Check for error responses
        if ($response_code < 200 || $response_code >= 300) {
            // Get detailed error message from response if available
            $error_message = 'Unknown error';
            
            if (is_array($response_data)) {
                if (isset($response_data['message'])) {
                    $error_message = $response_data['message'];
                } elseif (isset($response_data['error'])) {
                    $error_message = $response_data['error'];
                } elseif (isset($response_data['errorMessage'])) {
                    $error_message = $response_data['errorMessage'];
                }
                
                // Check for nested error messages
                if (isset($response_data['errors']) && is_array($response_data['errors'])) {
                    $error_details = array();
                    foreach ($response_data['errors'] as $field => $messages) {
                        if (is_array($messages)) {
                            $error_details[] = $field . ': ' . implode(', ', $messages);
                        } else {
                            $error_details[] = $field . ': ' . $messages;
                        }
                    }
                    if (!empty($error_details)) {
                        $error_message .= ' - ' . implode('; ', $error_details);
                    }
                }
            }
            
            $this->log('API Error Response: ' . $error_message);
            return new WP_Error('payscrow_api_error', $error_message, array(
                'status' => $response_code,
                'response' => $response_data
            ));
        }
        
        return $response_data;
    }

    /**
     * Log debug information
     *
     * @since    1.0.0
     * @param    string    $message      Message to log
     * @param    bool      $force_log    Whether to log even if debug mode is disabled
     */
    
    /**
     * Clean text for API submission - removes problematic characters like non-breaking spaces
     * 
     * @since    1.0.0
     * @param    string    $text    Text to clean
     * @return   string             Cleaned text
     */
    private function clean_text_for_api($text) {
        if (empty($text)) {
            return '';
        }
        
        // Remove non-breaking spaces (\u00A0) and other problematic Unicode characters
        $text = preg_replace('/\xA0|\x{00A0}|\s+/u', ' ', $text);
        
        // Normalize whitespace (remove double spaces)
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace from beginning and end
        $text = trim($text);
        
        // Remove any non-printable characters
        $text = preg_replace('/[^\P{C}\n]+/u', '', $text);
        
        // Encode any remaining special characters that might cause JSON issues
        $text = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $text);
        
        // For extra safety in JSON, remove any characters that might break JSON structure
        $text = str_replace(["\r", "\n", "\t", "\f", "\b"], ' ', $text);
        
        // Log hex representation if in debug mode
        if ($this->debug) {
            $this->log("Text cleaned for API. Original length: " . strlen($text));
        }
        
        return $text;
    }
    
    /**
     * Log a message to the debug log
     *
     * @since    1.0.0
     * @param    string    $message      Message to log
     * @param    bool      $force_log    Whether to log even if debug mode is disabled
     */
    private function log($message, $force_log = false) {
        if (!$this->debug && !$force_log) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/klassified-escrow-gateway-logs';
        $log_file = $log_dir . '/payscrow-api-' . date('Y-m-d') . '.log';
        
        // Create directory if it doesn't exist
        if (!file_exists($log_dir)) {
            if (!wp_mkdir_p($log_dir)) {
                // If we can't create the directory, try an alternative location
                $log_dir = WP_CONTENT_DIR . '/uploads/klassified-escrow-gateway-logs';
                $log_file = $log_dir . '/payscrow-api-' . date('Y-m-d') . '.log';
                
                if (!file_exists($log_dir)) {
                    wp_mkdir_p($log_dir);
                }
            }
        }
        
        // If we still can't write to the log, try to use the system temp directory
        if (!is_writable($log_dir) && function_exists('sys_get_temp_dir')) {
            $log_dir = rtrim(sys_get_temp_dir(), '/\\') . '/klassified-escrow-gateway-logs';
            $log_file = $log_dir . '/payscrow-api-' . date('Y-m-d') . '.log';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
        }
        
        // Set appropriate permissions on the log directory
        if (file_exists($log_dir)) {
            @chmod($log_dir, 0755);
        }
        
        $time = date('Y-m-d H:i:s');
        
        // Make sure message is a string
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        
        // Write to log file
        error_log("[{$time}] {$message}\n", 3, $log_file);
        
        // Also write critical API errors to PHP error log for backup
        if ($force_log && strpos($message, 'API Error') !== false) {
            error_log("KLASSIFIED ESCROW API ERROR: {$message}");
        }
    }
}
