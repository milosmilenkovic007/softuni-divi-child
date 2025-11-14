<?php
if (!defined('ABSPATH')) { exit; }

/*
 * Woo email customizations
 * - Appends participants list for company orders
 * - Outputs company identifiers
 */

/**
 * Render participants and company data inside Woo emails
 */
add_action('woocommerce_email_order_meta', function( $order, $sent_to_admin, $plain_text, $email ){
	if ( ! $order instanceof WC_Order ) { return; }

	$customer_type = $order->get_meta('customer_type');
	$company       = trim( (string) $order->get_billing_company() );
	$mb            = trim( (string) $order->get_meta('billing_mb') );
	$pib           = trim( (string) $order->get_meta('billing_pib') );
	$participants  = [];
	$participants_data = $order->get_meta('participants');
	if ( $participants_data ) {
		// Handle both array (new format) and JSON string (old format)
		if ( is_array( $participants_data ) ) {
			$participants = $participants_data;
		} elseif ( is_string( $participants_data ) ) {
			$decoded = json_decode( $participants_data, true );
			if ( is_array( $decoded ) ) { $participants = $decoded; }
		}
	}

	// Build output depending on plain or HTML email
	if ( $plain_text ) {
		$lines = [];
		if ( $customer_type ) { $lines[] = 'Tip kupca: ' . $customer_type; }
		if ( $company )       { $lines[] = 'Pravno lice: ' . $company; }
		if ( $mb )            { $lines[] = 'Matični broj: ' . $mb; }
		if ( $pib )           { $lines[] = 'PIB: ' . $pib; }
		if ( ! empty( $participants ) ) {
			$lines[] = 'Polaznici:';
			$i = 1;
			foreach ( $participants as $p ) {
				$row = $i++ . '. ' . trim( (string) ( $p['full_name'] ?? '' ) );
				if ( ! empty( $p['email'] ) ) { $row .= ' – ' . $p['email']; }
				if ( ! empty( $p['phone'] ) ) { $row .= ' – ' . $p['phone']; }
				$lines[] = $row;
			}
		}
		if ( ! empty( $lines ) ) {
			echo "\n" . implode( "\n", array_map( 'wp_strip_all_tags', $lines ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return;
	}

	echo '<div class="dc-email-meta" style="margin-top:12px">';
	if ( $customer_type ) {
		echo '<p><strong>Tip kupca:</strong> ' . esc_html( $customer_type ) . '</p>';
	}
	if ( $company ) {
		echo '<p><strong>Pravno lice:</strong> ' . esc_html( $company ) . '</p>';
	}
	if ( $mb ) {
		echo '<p><strong>Matični broj:</strong> ' . esc_html( $mb ) . '</p>';
	}
	if ( $pib ) {
		echo '<p><strong>PIB:</strong> ' . esc_html( $pib ) . '</p>';
	}
	if ( ! empty( $participants ) ) {
		echo '<div style="margin-top:6px">';
		echo '<strong>Polaznici:</strong>';
		echo '<ol style="margin:4px 0 0 18px">';
		foreach ( $participants as $p ) {
			$line = trim( (string) ( $p['full_name'] ?? '' ) );
			if ( ! empty( $p['email'] ) ) { $line .= ' – ' . sanitize_email( $p['email'] ); }
			if ( ! empty( $p['phone'] ) ) { $line .= ' – ' . esc_html( $p['phone'] ); }
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ol>';
		echo '</div>';
	}
	echo '</div>';
}, 20, 4);


/**
 * Attach invoices to WooCommerce emails based on customer type and payment method
 * 
 * Rules:
 * - Company + Bank transfer (bacs) → PROFAKTURA + Payment slip
 * - Individual + Bank transfer (bacs) → Payment slip only
 * - Individual + Credit card (nestpay) when completed → FAKTURA
 * - Individual → status changed to completed → FAKTURA
 */
add_filter('woocommerce_email_attachments', function( $attachments, $email_id, $order ) {
	// Only process if we have a valid order
	if ( ! $order instanceof WC_Order ) {
		return $attachments;
	}
	
	$customer_type = $order->get_meta('customer_type');
	$payment_method = $order->get_payment_method();
	$order_id = $order->get_id();
	$order_status = $order->get_status();
	
	// Log for debugging
	error_log("Email Attachments: Order #{$order_id}, Email: {$email_id}, Type: {$customer_type}, Payment: {$payment_method}, Status: {$order_status}");
	
	// Determine which documents to attach based on email type
	$attach_proforma = false;
	$attach_invoice = false;
	$attach_payment_slip = false;
	
	// Email types to handle
	$new_order_emails = ['new_order', 'customer_on_hold_order', 'customer_processing_order'];
	$completed_emails = ['customer_completed_order'];
	
	// Rule 1: Company + Bacs → PROFAKTURA + Payment slip (on new order)
	if ( $customer_type === 'company' && $payment_method === 'bacs' && in_array($email_id, $new_order_emails) ) {
		$attach_proforma = true;
		$attach_payment_slip = true;
		error_log("Email Attachments: Attaching PROFAKTURA + Payment slip for company order #{$order_id}");
	}
	
	// Rule 2: Individual + Bacs → Payment slip only (on new order)
	if ( $customer_type === 'individual' && $payment_method === 'bacs' && in_array($email_id, $new_order_emails) ) {
		$attach_payment_slip = true;
		error_log("Email Attachments: Attaching Payment slip only for individual bacs order #{$order_id}");
	}
	
	// Rule 3: Individual → FAKTURA (when completed)
	if ( $customer_type === 'individual' && in_array($email_id, $completed_emails) ) {
		$attach_invoice = true;
		error_log("Email Attachments: Attaching FAKTURA for completed individual order #{$order_id}");
	}
	
	// Rule 4: Company → FAKTURA (when completed)
	if ( $customer_type === 'company' && in_array($email_id, $completed_emails) ) {
		$attach_invoice = true;
		error_log("Email Attachments: Attaching FAKTURA for completed company order #{$order_id}");
	}
	
	// Generate and attach documents
	if ( $attach_proforma ) {
		$proforma_path = generate_proforma_pdf( $order_id );
		if ( $proforma_path && file_exists($proforma_path) ) {
			$attachments[] = $proforma_path;
		}
	}
	
	if ( $attach_invoice ) {
		$invoice_path = generate_invoice_pdf( $order_id );
		if ( $invoice_path && file_exists($invoice_path) ) {
			$attachments[] = $invoice_path;
		}
	}
	
	if ( $attach_payment_slip ) {
		$payment_slip_path = generate_payment_slip_pdf( $order_id );
		if ( $payment_slip_path && file_exists($payment_slip_path) ) {
			$attachments[] = $payment_slip_path;
		}
	}
	
	return $attachments;
}, 10, 3);


/**
 * Helper function to generate PROFAKTURA PDF
 */
function generate_proforma_pdf( $order_id ) {
	// Use existing invoice generator - it automatically creates PROFAKTURA for non-completed company orders
	$invoice_file = get_stylesheet_directory() . '/inc/woocommerce/invoice-server-generator.php';
	if ( ! file_exists($invoice_file) ) {
		return false;
	}
	
	require_once $invoice_file;
	
	// The existing function handles PROFAKTURA vs FAKTURA logic internally
	$pdf_path = su_generate_invoice_pdf_server_side( $order_id );
	
	return $pdf_path;
}


/**
 * Helper function to generate FAKTURA PDF
 */
function generate_invoice_pdf( $order_id ) {
	// Use existing invoice generator
	$invoice_file = get_stylesheet_directory() . '/inc/woocommerce/invoice-server-generator.php';
	if ( ! file_exists($invoice_file) ) {
		return false;
	}
	
	require_once $invoice_file;
	
	// For completed orders or individuals, it will generate FAKTURA
	$pdf_path = su_generate_invoice_pdf_server_side( $order_id );
	
	return $pdf_path;
}


/**
 * Helper function to generate payment slip (uplatnica) PDF
 */
function generate_payment_slip_pdf( $order_id ) {
	// Use existing payment slip generator
	$slip_file = get_stylesheet_directory() . '/inc/woocommerce/payment-slip-generator.php';
	if ( ! file_exists($slip_file) ) {
		return false;
	}
	
	require_once $slip_file;
	
	// Call the existing generator function
	$pdf_path = su_generate_payment_slip_pdf( $order_id );
	
	return $pdf_path;
}


/**
 * Auto-generate FAKTURA when order status changes to completed
 * Works for both individuals and companies
 */
add_action('woocommerce_order_status_completed', function( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	
	$customer_type = $order->get_meta('customer_type');
	
	// Generate FAKTURA for both individuals and companies when order is completed
	error_log("Order #{$order_id} completed - generating FAKTURA (Type: {$customer_type})");
	
	// Generate invoice (will be FAKTURA because order status is completed)
	$invoice_file = get_stylesheet_directory() . '/inc/woocommerce/invoice-server-generator.php';
	if ( file_exists($invoice_file) ) {
		require_once $invoice_file;
		su_generate_invoice_pdf_server_side( $order_id );
	}
}, 10, 1);


/**
 * Log all WooCommerce emails being sent for debugging (especially useful on localhost)
 */
add_action('woocommerce_email_before_send', function( $return, $email ) {
	if ( ! $email instanceof WC_Email || ! isset($email->object) ) {
		return $return;
	}
	
	$order = null;
	if ( $email->object instanceof WC_Order ) {
		$order = $email->object;
	}
	
	$log_entry = "\n" . str_repeat('=', 80) . "\n";
	$log_entry .= "EMAIL SENT: " . date('Y-m-d H:i:s') . "\n";
	$log_entry .= str_repeat('=', 80) . "\n";
	$log_entry .= "Email ID: " . $email->id . "\n";
	$log_entry .= "Email Subject: " . $email->get_subject() . "\n";
	$log_entry .= "Recipient: " . $email->get_recipient() . "\n";
	
	if ( $order ) {
		$order_id = $order->get_id();
		$customer_type = $order->get_meta('customer_type');
		$payment_method = $order->get_payment_method();
		$order_status = $order->get_status();
		
		$log_entry .= "\nORDER DETAILS:\n";
		$log_entry .= "- Order #: {$order_id}\n";
		$log_entry .= "- Customer Type: {$customer_type}\n";
		$log_entry .= "- Payment Method: {$payment_method}\n";
		$log_entry .= "- Order Status: {$order_status}\n";
		$log_entry .= "- Order Total: " . $order->get_formatted_order_total() . "\n";
		
		// Show company details if applicable
		if ( $customer_type === 'company' ) {
			$company = $order->get_meta('billing_company');
			$mb = $order->get_meta('billing_mb');
			$pib = $order->get_meta('billing_pib');
			$log_entry .= "- Company: {$company}\n";
			$log_entry .= "- MB: {$mb}\n";
			$log_entry .= "- PIB: {$pib}\n";
		}
		
		// Show participants if any
		$participants = $order->get_meta('participants');
		if ( !empty($participants) && is_array($participants) ) {
			$log_entry .= "- Participants: " . count($participants) . "\n";
			foreach ( $participants as $i => $p ) {
				$log_entry .= "  " . ($i+1) . ". " . ($p['full_name'] ?? 'N/A') . " - " . ($p['email'] ?? 'N/A') . "\n";
			}
		}
	}
	
	// Log attachments
	if ( !empty($email->attachments) && is_array($email->attachments) ) {
		$log_entry .= "\nATTACHMENTS (" . count($email->attachments) . "):\n";
		foreach ( $email->attachments as $attachment ) {
			$filename = basename($attachment);
			$filesize = file_exists($attachment) ? size_format(filesize($attachment)) : 'File not found';
			$log_entry .= "- {$filename} ({$filesize})\n";
		}
	} else {
		$log_entry .= "\nATTACHMENTS: None\n";
	}
	
	$log_entry .= str_repeat('=', 80) . "\n";
	
	error_log($log_entry);
	
	return $return;
}, 10, 2);


// Keep the placeholder filter (no-op) to show we're wired in
add_filter('woocommerce_email_enabled_customer_invoice', function ($enabled) {
	return $enabled;
}, 1);
