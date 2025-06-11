<?php
/*
Plugin Name: Currency Converter Plugin
Description: Converts a base currency number (in USD) to the visitor’s local currency using IPInfo and CurrencyAPI. Each saved number generates a shortcode for frontend display.
Version: 1.4.1
Author: Benedict Ogbogu
*/

if (! defined('ABSPATH')) exit; // Exit if accessed directly.

// Define constants for API keys and endpoints.
define('IPINFO_API_USER', '___API KEY FROM IP INFO___'); // Replace with your actual IPInfo API key.
define('CURRENCY_API_KEY', '___API KEY FROM CURRENCY API___'); // Replace with your actual CurrencyAPI key.
define('CURRENCY_API_URL', 'https://api.currencyapi.com/v3/latest?apikey=' . CURRENCY_API_KEY); 

// Option name to store our currency numbers.
define('CCP_OPTION', 'ccp_currency_numbers');

/* ==========================================================================
   ADMIN DASHBOARD FUNCTIONS
   ========================================================================== */

// Add a menu page for the plugin.
function ccp_add_admin_menu()
{
    add_menu_page(
        'Currency Converter',
        'Currency Converter',
        'manage_options',
        'ccp-currency-converter',
        'ccp_admin_page'
    );
}
add_action('admin_menu', 'ccp_add_admin_menu');

// Display the admin page with delete functionality.
function ccp_admin_page()
{
    // Process adding a new number
    if (isset($_POST['ccp_new_number']) && check_admin_referer('ccp_add_number', 'ccp_nonce')) {
        $new_number = floatval($_POST['ccp_new_number']);
        $number_type = isset($_POST['ccp_number_type']) ? sanitize_text_field($_POST['ccp_number_type']) : 'normal';
        $numbers = get_option(CCP_OPTION, array());

        // Generate a unique ID
        $id = time();
        $numbers[$id] = [
            'value' => $new_number,
            'type' => $number_type
        ];

        update_option(CCP_OPTION, $numbers);

        echo '<div class="updated"><p>New number added. Use the shortcode [currency_converter id="' . esc_attr($id) . '"] in your posts or pages.</p></div>';
    }

    // Process deletion of a shortcode entry
    if (isset($_POST['ccp_delete_id']) && check_admin_referer('ccp_delete_number', 'ccp_delete_nonce')) {
        $delete_id = sanitize_text_field($_POST['ccp_delete_id']);
        $numbers = get_option(CCP_OPTION, array());

        if (isset($numbers[$delete_id])) {
            unset($numbers[$delete_id]); // Remove from array
            update_option(CCP_OPTION, $numbers);
            echo '<div class="updated"><p>Shortcode deleted successfully.</p></div>';
        }
    }

    // Retrieve saved numbers
    $numbers = get_option(CCP_OPTION, array());
?>
    <div class="wrap">
        <h1>Currency Converter Plugin</h1>

        <form method="post" action="">
            <?php wp_nonce_field('ccp_add_number', 'ccp_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enter a Number (in USD):</th>
                    <td><input type="text" name="ccp_new_number" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Number Type:</th>
                    <td>
                        <select name="ccp_number_type">
                            <option value="normal">Normal Price</option>
                            <option value="slashed">Slashed Price</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Add Number'); ?>
        </form>

        <h2>Saved Numbers</h2>
        <?php if (!empty($numbers)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Number (USD)</th>
                        <th>Type</th>
                        <th>Shortcode</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($numbers as $id => $data) : ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($data['value']); ?></td>
                            <td><?php echo esc_html($data['type']); ?></td>
                            <td>[currency_converter id="<?php echo esc_html($id); ?>"]</td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this shortcode?');">
                                    <?php wp_nonce_field('ccp_delete_number', 'ccp_delete_nonce'); ?>
                                    <input type="hidden" name="ccp_delete_id" value="<?php echo esc_attr($id); ?>" />
                                    <button type="submit" class="button button-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No numbers added yet.</p>
        <?php endif; ?>
    </div>
<?php
}
/* ==========================================================================
   SHORTCODE HANDLER & FRONTEND CONVERSION
   ========================================================================== */

/**
 * Shortcode: [currency_converter id="..."]
 * Retrieves the stored number, determines the visitor's local currency using their IP,
 * fetches the conversion rate from CurrencyAPI, and outputs the converted amount.
 */
function ccp_currency_converter_shortcode($atts)
{
    // nocache_headers();

    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'currency_converter');

    if (empty($atts['id'])) {
        return ''; // Return nothing if no ID is provided.
    }

    $numbers = get_option(CCP_OPTION, array());
    if (! isset($numbers[$atts['id']])) {
        return ''; // Return nothing if the ID is invalid.
    }

    $base_amount = floatval($numbers[$atts['id']]['value']);
    $number_type = $numbers[$atts['id']]['type'];

    // Get the visitor's IP address
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $location_url = "https://ipinfo.io/{$user_ip}/json?token=" . IPINFO_API_USER;

    // Fetch user location from IPInfo
    $response = wp_remote_get($location_url, array('timeout' => 15));

    if (is_wp_error($response)) {
        return ''; // Return empty if request fails
    }

    $location_data = json_decode(wp_remote_retrieve_body($response), true);
    $currency_code = ccp_get_currency_by_country(isset($location_data['country']) ? $location_data['country'] : '');

    if (empty($currency_code)) {
        $currency_code = 'CAD'; // Default fallback
    }

    // Use cached exchange rates
    $exchange_rates = ccp_get_cached_exchange_rates();

    if (!$exchange_rates || !isset($exchange_rates[$currency_code]['value'])) {
        // If cached rates are missing, use fallback rate
        $rate = ccp_get_fallback_rate($currency_code);
        if ($rate === false) {
            return ''; // Return empty if fallback rate is not available
        }
    } else {
        // Use the cached rate if available
        $rate = $exchange_rates[$currency_code]['value'];
    }

    // Use $rate for conversion
    $converted_amount = $base_amount * $rate;  // Use the rate (cached or fallback) for the calculation
    $formatted_price = esc_html($currency_code) . ' ' . number_format($converted_amount, 2);

    if ($number_type === 'slashed') {
        return "<del>$formatted_price</del>";
    }
    return $formatted_price;
}
add_shortcode('currency_converter', 'ccp_currency_converter_shortcode');

/* ==========================================================================
   HELPER FUNCTION: Map Country Code to Currency Code
   ========================================================================== */

/**
 * Maps a country code (from ipinfo) to its corresponding currency code.
 */
function ccp_get_currency_by_country($country_code)
{
    $map = array(
        'US' => 'USD',
        'CA' => 'CAD',
        'GB' => 'GBP',
        'FR' => 'EUR',
        'DE' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'IN' => 'INR',
        'JP' => 'JPY',
        'AU' => 'AUD',
        'NG' => 'NGN',
        'CN' => 'CNY',
        'BR' => 'BRL',
        'MX' => 'MXN',
        'RU' => 'RUB',
        'ZA' => 'ZAR',
        'KR' => 'KRW',
        'CH' => 'CHF',
        'SE' => 'SEK',
        'NO' => 'NOK',
        'DK' => 'DKK',
        'SG' => 'SGD',
        'HK' => 'HKD',
        'NZ' => 'NZD',
        'MY' => 'MYR',
        'TH' => 'THB',
        'ID' => 'IDR',
        'PH' => 'PHP',
        'PK' => 'PKR',
        'BD' => 'BDT',
        'EG' => 'EGP',
        'AE' => 'AED',
        'SA' => 'SAR',
        'TR' => 'TRY',
        'VN' => 'VND',
        'IL' => 'ILS',
        'PL' => 'PLN',
        'CZ' => 'CZK',
        'HU' => 'HUF',
        'RO' => 'RON',
        'AR' => 'ARS',
        'CL' => 'CLP',
        'CO' => 'COP',
        'PE' => 'PEN',
        'VE' => 'VES',
        'UA' => 'UAH',
        'KZ' => 'KZT',
        'IQ' => 'IQD',
        'IR' => 'IRR',
        'SY' => 'SYP',
        'LB' => 'LBP',
        'OM' => 'OMR',
        'KW' => 'KWD',
        'QA' => 'QAR',
        'BH' => 'BHD',
    );

    return isset($map[$country_code]) ? $map[$country_code] : 'CAD'; // Default to CAD if not found
}

/**
 * Fetch and cache exchange rates from CurrencyAPI for 12 hour.
 */
function ccp_get_cached_exchange_rates()
{
    $cache_key = 'ccp_exchange_rates';
    $cached_rates = get_transient($cache_key);

    if ($cached_rates !== false) {
        return $cached_rates; // Return cached rates if available
    }

    // Fetch fresh exchange rates
    $exchange_url = CURRENCY_API_URL . "&base_currency=USD";
    $exchange_response = wp_remote_get($exchange_url, array('timeout' => 15));

    if (is_wp_error($exchange_response)) {
        return false; // Return false if API request fails
    }

    $exchange_data = json_decode(wp_remote_retrieve_body($exchange_response), true);

    if (!isset($exchange_data['data'])) {
        return false; // Return false if response is invalid
    }

    // Store exchange rates in cache for 1 hour
    set_transient($cache_key, $exchange_data['data'], 12 * HOUR_IN_SECONDS);

    return $exchange_data['data'];
}

/**
 * Provides fallback exchange rates in case API is unavailable.
 */
function ccp_get_fallback_rate($currency)
{
    $fallback_rates = array(
        'CAD' => 1.4326301773, // Canadian Dollar to USD
        'EUR' => 0.9172701438, // Euro to USD
        'GBP' => 0.7712800836, // British Pound to USD
        'AUD' => 1.5793201962, // Australian Dollar to USD
        'JPY' => 149.7412655724, // Japanese Yen to USD
        'NGN' => 1530.9632228414, // Nigerian Naira to USD
        'USD' => 1, // US Dollar to USD
        'INR' => 86.295952697, // Indian Rupee to USD
        'CNY' => 7.231981214, // Chinese Yuan to USD
        'MXN' => 19.9585834457, // Mexican Peso to USD
        'ZAR' => 18.1817131863, // South African Rand to USD
        'SGD' => 1.3333002141, // Singapore Dollar to USD
        'BRL' => 5.6681710129, // Brazilian Real to USD
        'CHF' => 0.8781401344, // Swiss Franc to USD
    );

    return isset($fallback_rates[$currency]) ? $fallback_rates[$currency] : 1;
}


?>