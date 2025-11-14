<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Auto-generate PDF invoice server-side when order status changes to 'completed'.
 */

add_action('woocommerce_order_status_completed', 'su_auto_generate_invoice_on_completed', 20, 2);
add_action('woocommerce_order_status_on-hold', 'su_auto_generate_invoice_on_hold', 20, 2);
add_action('woocommerce_order_status_pending', 'su_auto_generate_invoice_on_hold', 20, 2);

function su_auto_generate_invoice_on_hold( $order_id, $order = null ) {
	if ( ! $order ) { $order = wc_get_order( $order_id ); }
	if ( ! $order ) { return; }
	
	// Skip if invoice already exists
	if ( $order->get_meta('su_pdf_url') ) {
		return;
	}
	
	$customer_type = $order->get_meta('customer_type');
	
	// ONLY generate for companies (PROFAKTURA)
	// For individuals, wait until order is completed
	if ( $customer_type === 'company' ) {
		error_log("SU Invoice: Auto-generating PROFAKTURA for company order #{$order_id} on on-hold status");
		su_generate_invoice_pdf_server_side( $order_id );
	} else {
		error_log("SU Invoice: Skipping invoice generation for individual order #{$order_id} on on-hold status (will generate on completed)");
	}
}

function su_auto_generate_invoice_on_completed( $order_id, $order = null ){
	if ( ! $order ) { $order = wc_get_order( $order_id ); }
	if ( ! $order ) { return; }

	$customer_type = $order->get_meta('customer_type');
	$is_company = ($customer_type === 'company');
	
	// For companies: check if FAKTURA already exists, generate if not
	// For individuals: check if any invoice exists
	$existing_pdf = $order->get_meta('su_pdf_url');
	$existing_title = $order->get_meta('su_pdf_title');
	
	// If company and only PROFAKTURA exists, we need to generate FAKTURA
	if ( $is_company && $existing_pdf && $existing_title === 'PROFAKTURA' ) {
		error_log("SU Invoice: Order #{$order_id} is company with PROFAKTURA, generating FAKTURA on completed status.");
		// Clear existing PDF meta to allow FAKTURA generation
		$order->delete_meta_data('su_pdf_url');
		$order->delete_meta_data('su_pdf_path');
		$order->delete_meta_data('su_pdf_title');
		$order->save();
	} elseif ( $existing_pdf ) {
		error_log("SU Invoice: Order #{$order_id} already has PDF (${existing_title}), skipping auto-generation.");
		return;
	}

	error_log("SU Invoice: Auto-generating PDF server-side for order #{$order_id} on completed status. Type: " . ($is_company ? 'FAKTURA (company)' : 'FAKTURA (individual)'));
	
	// Generate PDF server-side (will be FAKTURA since status is completed)
	$pdf_path = su_generate_invoice_pdf_server_side( $order_id );
	
	if ( $pdf_path ) {
		error_log("SU Invoice: Successfully generated PDF for order #{$order_id}: {$pdf_path}");
	} else {
		error_log("SU Invoice: Failed to generate PDF for order #{$order_id}.");
	}
}
