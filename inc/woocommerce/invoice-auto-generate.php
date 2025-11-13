<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Auto-generate PDF invoice server-side when order status changes to 'completed'.
 */

add_action('woocommerce_order_status_completed', 'su_auto_generate_invoice_on_completed', 20, 2);

function su_auto_generate_invoice_on_completed( $order_id, $order = null ){
	if ( ! $order ) { $order = wc_get_order( $order_id ); }
	if ( ! $order ) { return; }

	// Skip if PDF already exists
	$existing_pdf = $order->get_meta('su_pdf_url');
	if ( $existing_pdf ) {
		error_log("SU Invoice: Order #{$order_id} already has PDF, skipping auto-generation.");
		return;
	}

	error_log("SU Invoice: Auto-generating PDF server-side for order #{$order_id} on completed status.");
	
	// Generate PDF server-side
	$pdf_path = su_generate_invoice_pdf_server_side( $order_id );
	
	if ( $pdf_path ) {
		error_log("SU Invoice: Successfully generated PDF for order #{$order_id}: {$pdf_path}");
	} else {
		error_log("SU Invoice: Failed to generate PDF for order #{$order_id}. Check wkhtmltopdf installation or add Dompdf library.");
	}
}
