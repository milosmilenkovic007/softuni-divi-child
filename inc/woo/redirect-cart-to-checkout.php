<?php
/**
 * Redirect WooCommerce Cart directly to Checkout
 */

// Redirect cart → checkout
add_filter( 'woocommerce_add_to_cart_redirect', 'softuni_redirect_cart_to_checkout' );
function softuni_redirect_cart_to_checkout() {
    return wc_get_checkout_url();
}

// Disable cart page completely (optional)
add_action( 'template_redirect', 'softuni_disable_cart_page' );
function softuni_disable_cart_page() {
    if ( is_cart() ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }
}
