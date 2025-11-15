<?php
/**
 * Redirect WooCommerce Cart directly to Checkout
 */

// 1. Posle add_to_cart uvek ide checkout
add_filter( 'woocommerce_add_to_cart_redirect', 'softuni_redirect_cart_to_checkout' );
function softuni_redirect_cart_to_checkout() {
    return wc_get_checkout_url();
}

// 2. Ako neko direktno otvori /cart/, prebaci ga na checkout
add_action( 'template_redirect', 'softuni_disable_cart_page' );
function softuni_disable_cart_page() {
    if ( is_cart() ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }
}

// 2b. Completely disable WooCommerce empty cart redirect
add_filter( 'woocommerce_cart_redirect_after_error', '__return_false', 999 );

// 2c. Allow custom checkout page to display even when cart is empty
add_action( 'template_redirect', 'softuni_allow_empty_checkout', 1 );
function softuni_allow_empty_checkout() {
    // If we're on the custom checkout template, allow it to load even if cart is empty
    if ( function_exists('is_page_template') && is_page_template('page-templates/checkout-custom.php') ) {
        // Don't redirect to cart page
        remove_action( 'template_redirect', 'wc_empty_cart_redirect_url' );
        
        // Disable the cart empty redirect that WooCommerce does on checkout
        add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
        add_filter( 'woocommerce_checkout_update_order_review_expired', '__return_false' );
    }
}

// 3. Ukloni poruku "dodat u korpu" + dugme "Pregled korpe"
add_filter( 'wc_add_to_cart_message_html', 'softuni_remove_add_to_cart_message', 10, 2 );
function softuni_remove_add_to_cart_message( $message, $products ) {
    // Pošto svakako ideš odmah na checkout, poruka nam ne treba
    return '';
}
