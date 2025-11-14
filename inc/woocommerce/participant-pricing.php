<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Participant-based pricing for companies
 * Each participant multiplies the cart total
 */

// Store participant count in session
add_action('init', 'su_init_participant_session', 1);
function su_init_participant_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

// AJAX handler to update participant count
add_action('wp_ajax_update_participant_count', 'su_update_participant_count');
add_action('wp_ajax_nopriv_update_participant_count', 'su_update_participant_count');

function su_update_participant_count() {
    check_ajax_referer('wc_checkout_nonce', 'nonce', false);
    
    $count = isset($_POST['count']) ? intval($_POST['count']) : 1;
    $count = max(1, $count); // At least 1 participant (the buyer)
    
    $customer_type = isset($_POST['customer_type']) ? sanitize_text_field($_POST['customer_type']) : 'individual';
    
    WC()->session->set('participant_count', $count);
    WC()->session->set('customer_type', $customer_type);
    
    // Debug logging
    error_log('Participant count updated: ' . $count . ', customer_type: ' . $customer_type);
    
    // Clear cart cache to force recalculation
    WC()->cart->set_session();
    
    // Trigger cart calculation
    WC()->cart->calculate_totals();
    
    // Get updated totals
    $subtotal = WC()->cart->get_cart_subtotal();
    $total = WC()->cart->get_total();
    
    wp_send_json_success(array(
        'count' => $count,
        'customer_type' => $customer_type,
        'subtotal' => $subtotal,
        'total' => $total,
        'fragments' => array(
            '.cc-subtotal-amount' => $subtotal,
            '.cc-total-amount' => $total
        )
    ));
}

// AJAX handler to update customer type
add_action('wp_ajax_update_customer_type', 'su_update_customer_type');
add_action('wp_ajax_nopriv_update_customer_type', 'su_update_customer_type');

function su_update_customer_type() {
    check_ajax_referer('wc_checkout_nonce', 'nonce', false);
    
    $customer_type = isset($_POST['customer_type']) ? sanitize_text_field($_POST['customer_type']) : 'individual';
    
    WC()->session->set('customer_type', $customer_type);
    
    // Reset participant count if switching to individual
    if ($customer_type === 'individual') {
        WC()->session->set('participant_count', 1);
        error_log('Customer type changed to individual - participant count reset to 1');
    }
    
    // Clear cart cache and trigger calculation
    WC()->cart->set_session();
    WC()->cart->calculate_totals();
    
    error_log('Customer type updated to: ' . $customer_type);
    
    // Get updated totals
    $subtotal = WC()->cart->get_cart_subtotal();
    $total = WC()->cart->get_total();
    
    wp_send_json_success(array(
        'customer_type' => $customer_type,
        'subtotal' => $subtotal,
        'total' => $total,
        'fragments' => array(
            '.cc-subtotal-amount' => $subtotal,
            '.cc-total-amount' => $total
        )
    ));
}

// Modify cart item prices based on participant count
add_action('woocommerce_before_calculate_totals', 'su_adjust_cart_prices_for_participants', 10, 1);

function su_adjust_cart_prices_for_participants($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Avoid infinite loop
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }

    // Get participant count from session
    $participant_count = WC()->session->get('participant_count', 1);
    
    // Only apply for companies (check if we have this in session or cart meta)
    $customer_type = WC()->session->get('customer_type', 'individual');
    
    error_log('Adjusting prices - Count: ' . $participant_count . ', Type: ' . $customer_type);
    
    if ($customer_type !== 'company' || $participant_count <= 1) {
        error_log('Skipping price adjustment - not applicable');
        return;
    }

    // Multiply each cart item price by participant count
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Get the product
        $product = $cart_item['data'];
        
        // Get original price from the product ID (not from cart item which may be modified)
        $product_id = $product->get_id();
        $original_product = wc_get_product($product_id);
        
        if (!$original_product) {
            continue;
        }
        
        $original_price = $original_product->get_price();
        
        // Set new price
        $new_price = $original_price * $participant_count;
        $product->set_price($new_price);
        
        error_log('Price adjusted - Product ID: ' . $product_id . ', Original: ' . $original_price . ', New: ' . $new_price . ' (x' . $participant_count . ')');
    }
}

// Save customer type to session when checkout form is submitted
add_action('woocommerce_checkout_update_order_meta', 'su_save_participant_count_to_order', 10, 2);

function su_save_participant_count_to_order($order_id, $data) {
    $participant_count = WC()->session->get('participant_count', 1);
    
    if ($participant_count > 1) {
        $order = wc_get_order($order_id);
        $order->update_meta_data('participant_count', $participant_count);
        $order->save();
    }
}

// Clear participant count after order is placed
add_action('woocommerce_thankyou', 'su_clear_participant_session', 10, 1);

function su_clear_participant_session($order_id) {
    WC()->session->set('participant_count', 1);
    WC()->session->set('customer_type', 'individual');
}

// Display participant info in order details
add_action('woocommerce_order_details_after_order_table', 'su_display_participant_info_in_order', 10, 1);

function su_display_participant_info_in_order($order) {
    $participant_count = $order->get_meta('participant_count');
    
    if ($participant_count && $participant_count > 1) {
        echo '<div class="participant-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #00ba96;">';
        echo '<strong>Broj polaznika:</strong> ' . esc_html($participant_count);
        echo '</div>';
    }
}
