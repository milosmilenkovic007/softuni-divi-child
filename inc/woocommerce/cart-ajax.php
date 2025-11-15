<?php
/**
 * AJAX handlers for cart operations
 */

if (!defined('ABSPATH')) { exit; }

/**
 * AJAX handler: Remove item from cart
 */
add_action('wp_ajax_remove_cart_item', 'softuni_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_remove_cart_item', 'softuni_ajax_remove_cart_item');

function softuni_ajax_remove_cart_item() {
    // Verify nonce if needed (optional, depending on your security setup)
    // check_ajax_referer('woocommerce-cart', 'security');
    
    if (!isset($_POST['cart_item_key'])) {
        wp_send_json_error(array('message' => 'Nedostaje ključ proizvoda.'));
    }

    $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item_key']));
    
    if (!WC()->cart) {
        wp_send_json_error(array('message' => 'Korpa nije dostupna.'));
    }

    $removed = WC()->cart->remove_cart_item($cart_item_key);
    
    if ($removed) {
        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'message' => 'Proizvod je uklonjen iz korpe.',
            'cart_hash' => WC()->cart->get_cart_hash(),
            'cart_total' => WC()->cart->get_total(),
            'cart_subtotal' => WC()->cart->get_cart_subtotal(),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'fragments' => array()
        ));
    } else {
        wp_send_json_error(array('message' => 'Proizvod nije mogao biti uklonjen.'));
    }
}
