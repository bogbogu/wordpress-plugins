<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete stored numbers from the database
delete_option( 'ccp_currency_numbers' );