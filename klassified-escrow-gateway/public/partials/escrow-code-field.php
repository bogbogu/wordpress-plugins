<?php
/**
 * Escrow Code field template at checkout
 *
 * This file displays the escrow code input field during checkout
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Klassified_Escrow_Gateway
 * @subpackage Klassified_Escrow_Gateway/public/partials
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="escrow-code-section">
    <h3><?php _e('Have an Escrow Code?', 'klassified-escrow-gateway'); ?></h3>
    
    <div class="escrow-info-notice">
        <?php _e('You can apply an escrow code to get a discount on your purchase.', 'klassified-escrow-gateway'); ?>
    </div>
    
    <label for="escrow-code" class="escrow-code-label">
        <?php _e('Enter your escrow code:', 'klassified-escrow-gateway'); ?>
    </label>
    
    <div class="escrow-code-controls">
        <input type="text" id="escrow-code" class="escrow-code-input" value="<?php echo esc_attr($escrow_code); ?>" placeholder="<?php _e('Enter code here', 'klassified-escrow-gateway'); ?>" />
        
        <div class="escrow-code-buttons">
            <button type="button" id="apply-escrow-code" class="button"><?php _e('Apply', 'klassified-escrow-gateway'); ?></button>
            <button type="button" id="clear-escrow-code" class="button"><?php _e('Clear', 'klassified-escrow-gateway'); ?></button>
        </div>
    </div>
    
    <div class="escrow-code-message">
        <?php if (!empty($escrow_code)): ?>
            <p class="success">
                <?php printf(__('Escrow code "%s" applied.', 'klassified-escrow-gateway'), esc_html($escrow_code)); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
