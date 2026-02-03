(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practice to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practicing this, we should strive to set a better example in our own work.
	 */

	$(function() {
		// Toggle dependent fields based on checkbox states
		$('#payscrow_wc_escrow_settings_sandbox_mode').on('change', function() {
			if ($(this).is(':checked')) {
				$('.api-key-description').text('Use sandbox API key');
			} else {
				$('.api-key-description').text('Use production API key');
			}
		});

		// Initialize on page load
		if ($('#payscrow_wc_escrow_settings_sandbox_mode').is(':checked')) {
			$('.api-key-description').text('Use sandbox API key');
		} else {
			$('.api-key-description').text('Use production API key');
		}
	});

})( jQuery );
