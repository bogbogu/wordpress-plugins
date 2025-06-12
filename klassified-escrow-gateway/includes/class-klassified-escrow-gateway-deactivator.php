<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/includes
 * @author     Your Name <email@example.com>
 */
class Klassified_Escrow_Gateway_Deactivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Flush rewrite rules on deactivation
        flush_rewrite_rules();
        
        // We'll keep the settings in the database for future use
        // If you want to remove all settings, uncomment this line:
        // delete_option('klassified_escrow_gateway_settings');
    }

}
