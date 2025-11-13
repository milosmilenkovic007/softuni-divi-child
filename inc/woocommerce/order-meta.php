<?php
/**
 * WooCommerce Order Meta for Custom Checkout Fields
 * - Saves customer type and company identifiers (PIB, MB)
 * - Displays them in WP Admin order screen
 */

if (!defined('ABSPATH')) { exit; }

// Save custom meta when an order is created via Woo checkout
add_action('woocommerce_checkout_create_order', function( $order, $data ){
	$customer_type = isset($_POST['customer_type']) ? sanitize_text_field( wp_unslash($_POST['customer_type']) ) : '';
	$pib = isset($_POST['billing_pib']) ? sanitize_text_field( wp_unslash($_POST['billing_pib']) ) : '';
	$mb  = isset($_POST['billing_mb'])  ? sanitize_text_field( wp_unslash($_POST['billing_mb']) )  : '';
	$company = isset($_POST['billing_company']) ? sanitize_text_field( wp_unslash($_POST['billing_company']) ) : '';

	if ($customer_type) { $order->update_meta_data('customer_type', $customer_type); }
	if ($pib)           { $order->update_meta_data('billing_pib', $pib); }
	if ($mb)            { $order->update_meta_data('billing_mb', $mb); }
	// Billing company is already part of billing data, but also store in meta for convenience
	if ($company)       { $order->update_meta_data('billing_company', $company); }
}, 10, 2);

// Show custom meta in admin order page
add_action('woocommerce_admin_order_data_after_billing_address', function( $order ){
	$customer_type = $order->get_meta('customer_type');
	$pib = $order->get_meta('billing_pib');
	$mb  = $order->get_meta('billing_mb');
	$company = $order->get_billing_company();
	$participants_json = $order->get_meta('participants');
	$participants = [];
	if ($participants_json) {
		$decoded = json_decode($participants_json, true);
		if (is_array($decoded)) { $participants = $decoded; }
	}
	echo '<div class="order-custom-meta">';
	if ($customer_type) { echo '<p><strong>Tip kupca:</strong> ' . esc_html($customer_type) . '</p>'; }
	if ($company)       { echo '<p><strong>Pravno lice:</strong> ' . esc_html($company) . '</p>'; }
	if ($mb)            { echo '<p><strong>Matični broj:</strong> ' . esc_html($mb) . '</p>'; }
	if ($pib)           { echo '<p><strong>PIB:</strong> ' . esc_html($pib) . '</p>'; }
	if (!empty($participants)) {
		echo '<div style="margin-top:8px">';
		echo '<strong>Polaznici:</strong>';
		echo '<ol style="margin:4px 0 0 18px">';
		foreach($participants as $p){
			$line = trim(($p['full_name'] ?? ''));
			if(!empty($p['email'] ?? '')){ $line .= ' – ' . esc_html($p['email']); }
			if(!empty($p['phone'] ?? '')){ $line .= ' – ' . esc_html($p['phone']); }
			echo '<li>' . esc_html($line) . '</li>';
		}
		echo '</ol>';
		echo '</div>';
	}
	$invoice_number = $order->get_meta('su_invoice_number');
	if ( $invoice_number ) {
		echo '<p><strong>Broj fakture:</strong> ' . esc_html( $invoice_number ) . '</p>';
	}
	// Link to saved PDF, if any
	$pdf_url = $order->get_meta('su_pdf_url');
	$pdf_title = $order->get_meta('su_pdf_title');
	if ( $pdf_url ) {
		echo '<p style="margin-top:8px"><strong>'. esc_html( $pdf_title ? $pdf_title : 'Dokument (PDF)' ) .':</strong> ';
		echo '<a href="'. esc_url( $pdf_url ) .'" target="_blank" rel="noopener">Preuzmi</a></p>';
	}
	echo '</div>';
});

