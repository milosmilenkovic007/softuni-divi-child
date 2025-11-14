<?php
/**
 * Force WooCommerce checkout to use the custom Divi Child template.
 */
if (!defined('ABSPATH')) { exit; }

// Always use the custom checkout template for Woo checkout requests
add_filter('template_include', function($template){
	// Handle thank you / order received page with custom template
	$on_thankyou = function_exists('is_order_received_page') ? is_order_received_page() : false;
	if ( $on_thankyou ) {
		$custom_thankyou = DIVICHILD_PATH . '/page-templates/thankyou-custom.php';
		if ( file_exists($custom_thankyou) ) { 
			return $custom_thankyou; 
		}
	}
	
	if ( function_exists('is_checkout') && is_checkout() ){
		// Do NOT override the payment page (order-pay)
		$on_pay_page = function_exists('is_checkout_pay_page') ? is_checkout_pay_page() : false;
		if ( ! $on_pay_page && ! $on_thankyou ){
			$custom = DIVICHILD_PATH . '/page-templates/checkout-custom.php';
			if ( file_exists($custom) ) { return $custom; }
		}
	}
	return $template;
}, 99);

// Optionally, normalize to the configured checkout page URL to avoid duplicate routes
add_action('template_redirect', function(){
	if ( function_exists('is_checkout') && is_checkout() ){
		// Skip normalization for order-pay and thank you pages
		$on_pay_page = function_exists('is_checkout_pay_page') ? is_checkout_pay_page() : false;
		$on_thankyou = function_exists('is_order_received_page') ? is_order_received_page() : false;
		if ( $on_pay_page || $on_thankyou ) { return; }
		$checkout_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('checkout') : 0;
		$current_id = get_queried_object_id();
		if ( $checkout_page_id && $current_id && $current_id !== (int)$checkout_page_id ){
			wp_safe_redirect( get_permalink( $checkout_page_id ) );
			exit;
		}
	}
}, 1);
