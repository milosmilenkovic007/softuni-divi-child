<?php
/**
 * Force WooCommerce checkout to use the custom Divi Child template.
 */
if (!defined('ABSPATH')) { exit; }

// Always use the custom checkout template for Woo checkout requests
add_filter('template_include', function($template){
	// Handle order-pay page (credit card payment)
	$is_order_pay = false;
	if ( function_exists('is_checkout_pay_page') && is_checkout_pay_page() ) {
		$is_order_pay = true;
		error_log('Routing: Detected order-pay via is_checkout_pay_page()');
	} elseif ( isset($_GET['key']) && strpos($_SERVER['REQUEST_URI'], 'order-pay') !== false ) {
		$is_order_pay = true;
		error_log('Routing: Detected order-pay via URL pattern. URI: ' . $_SERVER['REQUEST_URI']);
	}
	
	if ( $is_order_pay ) {
		$custom_orderpay = DIVICHILD_PATH . '/page-templates/order-pay-custom.php';
		if ( file_exists($custom_orderpay) ) {
			error_log('Routing: Using custom order-pay template: ' . $custom_orderpay);
			return $custom_orderpay; 
		} else {
			error_log('Routing: Custom order-pay template NOT FOUND: ' . $custom_orderpay);
		}
	}
	
	// Handle thank you / order received page with custom template
	// Check if this is order-received endpoint
	$is_order_received = false;
	
	if ( function_exists('is_order_received_page') && is_order_received_page() ) {
		$is_order_received = true;
		error_log('Routing: Detected order-received via is_order_received_page()');
	} elseif ( isset($_GET['key']) && strpos($_SERVER['REQUEST_URI'], 'order-received') !== false ) {
		// Fallback: check URL pattern
		$is_order_received = true;
		error_log('Routing: Detected order-received via URL pattern. URI: ' . $_SERVER['REQUEST_URI']);
	}
	
	if ( $is_order_received ) {
		$custom_thankyou = DIVICHILD_PATH . '/page-templates/thankyou-custom.php';
		if ( file_exists($custom_thankyou) ) {
			error_log('Routing: Using custom thank-you template: ' . $custom_thankyou);
			return $custom_thankyou; 
		} else {
			error_log('Routing: Custom thank-you template NOT FOUND: ' . $custom_thankyou);
		}
	}
	
	if ( function_exists('is_checkout') && is_checkout() ){
		// Do NOT override the payment page (order-pay)
		if ( ! $is_order_pay && ! $is_order_received ){
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
		$is_order_pay = (function_exists('is_checkout_pay_page') && is_checkout_pay_page()) || 
		                (isset($_GET['key']) && strpos($_SERVER['REQUEST_URI'], 'order-pay') !== false);
		$is_order_received = (function_exists('is_order_received_page') && is_order_received_page()) || 
		                     (isset($_GET['key']) && strpos($_SERVER['REQUEST_URI'], 'order-received') !== false);
		
		if ( $is_order_pay || $is_order_received ) { return; }
		$checkout_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('checkout') : 0;
		$current_id = get_queried_object_id();
		if ( $checkout_page_id && $current_id && $current_id !== (int)$checkout_page_id ){
			wp_safe_redirect( get_permalink( $checkout_page_id ) );
			exit;
		}
	}
}, 1);
