<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Customize WooCommerce Thank You (Order Received) page
 */

// Remove payment method icons/logos from thank you page
// Note: Do NOT modify woocommerce_thankyou_order_received_text filter as it breaks compatibility with payment gateway plugins

// Remove page title completely
add_filter('the_title', function( $title, $id = null ) {
	if ( is_wc_endpoint_url('order-received') && in_the_loop() ) {
		return '';
	}
	return $title;
}, 10, 2);

// Hide page title via body class
add_filter('body_class', function( $classes ) {
	if ( is_wc_endpoint_url('order-received') ) {
		$classes[] = 'softuni-thankyou-page';
	}
	return $classes;
});

// Add custom CSS to clean up thank you page
add_action('wp_head', function() {
	if ( ! is_wc_endpoint_url('order-received') ) { return; }
	?>
	<style>
		/* Force hide page title */
		body.softuni-thankyou-page .entry-title,
		body.softuni-thankyou-page h1.entry-title,
		body.softuni-thankyou-page .et_pb_title_container,
		body.softuni-thankyou-page .et_pb_title_container h1,
		body.softuni-thankyou-page .page-title,
		body.softuni-thankyou-page .woocommerce-order > h1:first-child {
			display: none !important;
			visibility: hidden !important;
			height: 0 !important;
			margin: 0 !important;
			padding: 0 !important;
		}
		
		/* Hide default WooCommerce order overview */
		ul.woocommerce-order-overview {
			display: none !important;
		}
		
		/* Hide payment method icons/logos - stronger selectors */
		.woocommerce-order-overview__payment-method img,
		.woocommerce-order-details .payment_method img,
		.wc-payment-method-logo,
		ul.wc_payment_methods li img,
		img[alt*="Visa"],
		img[alt*="MasterCard"],
		img[alt*="Maestro"],
		img[alt*="Dina"],
		img[src*="visa"],
		img[src*="mastercard"],
		img[src*="maestro"],
		img[src*="dina"],
		.payment_method img,
		.payment_method_bacs img,
		div[style*="background-image"] img {
			display: none !important;
			visibility: hidden !important;
			opacity: 0 !important;
			width: 0 !important;
			height: 0 !important;
		}
		
		/* Clean thank you page layout */
		.woocommerce-order {
			max-width: 1000px;
			margin: 0 auto;
		}
		
		/* Order overview styling */
		.woocommerce-order-overview {
			background: #f8f9fa;
			border: 1px solid #e1e8ed;
			border-radius: 8px;
			padding: 30px;
			margin-bottom: 30px;
			display: flex;
			flex-wrap: wrap;
			gap: 20px;
		}
		
		.woocommerce-order-overview__order,
		.woocommerce-order-overview__date,
		.woocommerce-order-overview__email,
		.woocommerce-order-overview__total,
		.woocommerce-order-overview__payment-method {
			flex: 1 1 calc(33.333% - 20px);
			min-width: 200px;
			margin: 0 !important;
			border: none !important;
			background: white;
			padding: 15px;
			border-radius: 6px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		
		.woocommerce-order-overview strong {
			display: block;
			font-size: 14px;
			color: #666;
			font-weight: 600;
			margin-bottom: 5px;
		}
		
		/* Order details section */
		.woocommerce-order-details {
			background: white;
			border: 1px solid #e1e8ed;
			border-radius: 8px;
			padding: 30px;
			margin-bottom: 30px;
		}
		
		.woocommerce-order-details__title {
			font-size: 20px;
			margin-bottom: 20px;
			padding-bottom: 15px;
			border-bottom: 2px solid #2c3e50;
		}
		
		/* Order table */
		.woocommerce-table {
			border: 1px solid #e1e8ed;
			border-radius: 6px;
			overflow: hidden;
		}
		
		.woocommerce-table thead {
			background: #2c3e50;
			color: white;
		}
		
		.woocommerce-table th {
			padding: 15px;
			font-weight: 600;
			text-align: left;
		}
		
		.woocommerce-table td {
			padding: 15px;
			border-bottom: 1px solid #e1e8ed;
		}
		
		.woocommerce-table tbody tr:last-child td {
			border-bottom: none;
		}
		
		.woocommerce-table tfoot {
			background: #f8f9fa;
			font-weight: 600;
		}
		
		.woocommerce-table tfoot th,
		.woocommerce-table tfoot td {
			padding: 15px;
			font-size: 16px;
		}
		
		/* Customer details */
		.woocommerce-customer-details {
			background: white;
			border: 1px solid #e1e8ed;
			border-radius: 8px;
			padding: 30px;
			margin-bottom: 30px;
		}
		
		.woocommerce-column__title {
			font-size: 18px;
			margin-bottom: 15px;
			padding-bottom: 10px;
			border-bottom: 2px solid #2c3e50;
		}
		
		.woocommerce-customer-details address {
			line-height: 1.8;
		}
		
		/* Invoice download button */
		.woocommerce-order a[href*="pdf"],
		.woocommerce-order button[onclick*="pdf"] {
			display: inline-block;
			background: #2c3e50;
			color: white !important;
			padding: 12px 30px;
			border-radius: 6px;
			text-decoration: none;
			font-weight: 600;
			margin: 20px 0;
			border: none;
			cursor: pointer;
			transition: background 0.3s ease;
		}
		
		.woocommerce-order a[href*="pdf"]:hover,
		.woocommerce-order button[onclick*="pdf"]:hover {
			background: #34495e;
		}
		
		/* Responsive */
		@media (max-width: 768px) {
			.woocommerce-order-overview__order,
			.woocommerce-order-overview__date,
			.woocommerce-order-overview__email,
			.woocommerce-order-overview__total,
			.woocommerce-order-overview__payment-method {
				flex: 1 1 100%;
			}
		}
	</style>
	<?php
});
