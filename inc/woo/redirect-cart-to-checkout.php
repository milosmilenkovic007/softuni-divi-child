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

// 3. Ukloni poruku "dodat u korpu" + dugme "Pregled korpe"
add_filter( 'wc_add_to_cart_message_html', 'softuni_remove_add_to_cart_message', 10, 2 );
function softuni_remove_add_to_cart_message( $message, $products ) {
    // Pošto svakako ideš odmah na checkout, poruka nam ne treba
    return '';
}
