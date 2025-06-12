(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 */

	$(function() {
		// Handle escrow code application
		$('#apply-escrow-code').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $codeField = $('#escrow-code');
			var $messageArea = $('.escrow-code-message');
			var code = $codeField.val();
			
			if (!code) {
				$messageArea.html('<p class="error">' + klassified_escrow_params.i18n_error + 'Please enter an escrow code.</p>');
				return;
			}
			
			// Show loading state
			$button.prop('disabled', true).text('Applying...');
			$messageArea.html('<p>Verifying code...</p>');
			
			// Make AJAX request
			$.ajax({
				type: 'POST',
				url: klassified_escrow_params.ajax_url,
				data: {
					action: 'apply_escrow_code',
					escrow_code: code,
					security: klassified_escrow_params.apply_escrow_nonce
				},
				success: function(response) {
					if (response.success) {
						$messageArea.html('<p class="success">' + response.data.message + '</p>');
						
						// Trigger cart update
						$('body').trigger('update_checkout');
					} else {
						$messageArea.html('<p class="error">' + klassified_escrow_params.i18n_error + response.data.message + '</p>');
					}
				},
				error: function() {
					$messageArea.html('<p class="error">' + klassified_escrow_params.i18n_error + 'Could not connect to the server.</p>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Apply');
				}
			});
		});
		
		// Clear escrow code
		$('#clear-escrow-code').on('click', function(e) {
			e.preventDefault();
			
			var $codeField = $('#escrow-code');
			var $messageArea = $('.escrow-code-message');
			
			$codeField.val('');
			
			// Make AJAX request to clear code
			$.ajax({
				type: 'POST',
				url: klassified_escrow_params.ajax_url,
				data: {
					action: 'apply_escrow_code',
					escrow_code: '',
					security: klassified_escrow_params.apply_escrow_nonce
				},
				success: function() {
					$messageArea.html('');
					
					// Trigger cart update
					$('body').trigger('update_checkout');
				}
			});
		});
		
		// Update escrow code section visibility when payment method changes
		$('body').on('payment_method_selected', function() {
			var selectedMethod = $('input[name="payment_method"]:checked').val();
			
			if (selectedMethod === 'klassified_escrow') {
				$('.escrow-code-section').show();
			} else {
				$('.escrow-code-section').hide();
			}
		});
		
		// Initialize on page load
		var selectedMethod = $('input[name="payment_method"]:checked').val();
		if (selectedMethod === 'klassified_escrow') {
			$('.escrow-code-section').show();
		} else {
			$('.escrow-code-section').hide();
		}
	});

})( jQuery );
