<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Generate Serbian Payment Slip (Uplatnica) PDF using TCPDF
 */

function su_generate_payment_slip_pdf( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		error_log("SU Payment Slip: Order #{$order_id} not found.");
		return false;
	}

	// Payment slip data
	$payer_name = trim($order->get_formatted_billing_full_name());
	$payer_address = trim($order->get_billing_address_1());
	$payer_city = trim($order->get_billing_postcode() . ' ' . $order->get_billing_city());
	
	$receiver_name = 'SoftUni doo Beograd';
	$receiver_address = 'Pivljanina Baja 1, 11000 Beograd, Srbija';
	$receiver_account = '160-6000001539070-39';
	
	$amount = $order->get_total();
	$currency = 'RSD';
	$purpose = 'Uplata za porudžbinu';
	
	// Model 97, Poziv na broj = Order ID
	$model = '97';
	$reference_number = $order->get_order_number();
	
	// Payment code (default 189 - ostale uplate)
	$payment_code = '189';
	
	$theme_dir = get_stylesheet_directory();
	$uplatnice_dir = $theme_dir . '/Uplatnice';
	if ( ! file_exists( $uplatnice_dir ) ) {
		wp_mkdir_p( $uplatnice_dir );
	}

	$filename = 'uplatnica_' . $order->get_order_number() . '.pdf';
	$file_path = $uplatnice_dir . '/' . $filename;

	// Use TCPDF
	require_once get_stylesheet_directory() . '/inc/woocommerce/tcpdf/tcpdf.php';
	
	try {
		// Create new PDF document (A4 landscape for uplatnica)
		$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
		
		// Set document information
		$pdf->SetCreator('SoftUni doo');
		$pdf->SetAuthor('SoftUni doo');
		$pdf->SetTitle('Uplatnica - Narudžbina ' . $order->get_order_number());
		
		// Remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		
		// Set margins
		$pdf->SetMargins(10, 10, 10);
		$pdf->SetAutoPageBreak(false);
		
		// Add a page
		$pdf->AddPage();
		
		$pdf->SetFont('dejavusans', '', 10);
		
		// Title
		$pdf->SetFont('dejavusans', 'B', 14);
		$pdf->Cell(0, 10, 'NALOG ZA UPLATU', 0, 1, 'C');
		$pdf->Ln(5);
		
		// Instructions
		$pdf->SetFont('dejavusans', 'I', 9);
		$pdf->MultiCell(0, 5, 'Uplata po uplatnici. Instrukcije stižu mejlom.', 0, 'C');
		$pdf->Ln(8);
		
		$pdf->SetFont('dejavusans', '', 10);
		
		// Define column positions
		$col1_x = 15;
		$col2_x = 155;
		$field_height = 12;
		$current_y = $pdf->GetY();
		
		// Left Column - Uplatilac (Payer)
		$pdf->SetXY($col1_x, $current_y);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(60, 6, 'UPLATILAC:', 0, 1, 'L');
		
		$pdf->SetX($col1_x);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(130, 5, $payer_name . "\n" . $payer_address . "\n" . $payer_city, 'B', 'L');
		
		$pdf->Ln(3);
		
		// Svrha uplate (Purpose)
		$current_y = $pdf->GetY();
		$pdf->SetXY($col1_x, $current_y);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(60, 6, 'SVRHA UPLATE:', 0, 1, 'L');
		
		$pdf->SetX($col1_x);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(130, 5, $purpose, 'B', 'L');
		
		$pdf->Ln(3);
		
		// Primalac (Receiver)
		$current_y = $pdf->GetY();
		$pdf->SetXY($col1_x, $current_y);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(60, 6, 'PRIMALAC (naziv firme):', 0, 1, 'L');
		
		$pdf->SetX($col1_x);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(130, 5, $receiver_name, 'B', 'L');
		
		$pdf->Ln(3);
		
		// Adresa primaoca (Receiver address)
		$current_y = $pdf->GetY();
		$pdf->SetXY($col1_x, $current_y);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(60, 6, 'ADRESA PRIMAOCA:', 0, 1, 'L');
		
		$pdf->SetX($col1_x);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(130, 5, $receiver_address, 'B', 'L');
		
		$pdf->Ln(3);
		
		// Broj računa / IBAN
		$current_y = $pdf->GetY();
		$pdf->SetXY($col1_x, $current_y);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(60, 6, 'BROJ RAČUNA / IBAN:', 0, 1, 'L');
		
		$pdf->SetX($col1_x);
		$pdf->SetFont('dejavusans', '', 11);
		$pdf->SetTextColor(200, 0, 0); // Red color for account number
		$pdf->Cell(130, 8, $receiver_account, 'B', 1, 'L');
		$pdf->SetTextColor(0, 0, 0); // Reset to black
		
		$pdf->Ln(3);
		
		// Right Column - Model, Reference, Amount
		
		// Reset Y to top of form
		$pdf->SetXY($col2_x, 50);
		
		// Model
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(50, 6, 'MODEL:', 0, 1, 'L');
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', '', 11);
		$pdf->Cell(50, 8, $model, 'B', 1, 'L');
		
		$pdf->SetX($col2_x);
		$pdf->Ln(3);
		
		// Poziv na broj (Reference number)
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(50, 6, 'POZIV NA BROJ:', 0, 1, 'L');
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', '', 11);
		$pdf->Cell(50, 8, $reference_number, 'B', 1, 'L');
		
		$pdf->SetX($col2_x);
		$pdf->Ln(3);
		
		// Iznos (Amount)
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(50, 6, 'IZNOS:', 0, 1, 'L');
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', 'B', 14);
		$pdf->SetTextColor(200, 0, 0); // Red color for amount
		$pdf->Cell(50, 10, number_format($amount, 2, ',', '.') . ' ' . $currency, 'B', 1, 'L');
		$pdf->SetTextColor(0, 0, 0); // Reset to black
		
		$pdf->Ln(5);
		
		// Šifra plaćanja (Payment code)
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->Cell(50, 6, 'ŠIFRA PLAĆANJA:', 0, 1, 'L');
		$pdf->SetX($col2_x);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->Cell(50, 8, $payment_code, 'B', 1, 'L');
		
		// Footer note
		$pdf->SetY(190);
		$pdf->SetFont('dejavusans', 'I', 8);
		$pdf->SetTextColor(100, 100, 100);
		$pdf->MultiCell(0, 4, 
			"Ovaj dokument možete odštampati i odneti u banku ili koristiti za online plaćanje.\n" .
			"Za online plaćanje koristite podatke sa uplatnice u vašoj mobilnoj/internet banci.", 
			0, 'C'
		);
		
		// Output PDF
		$pdf->Output($file_path, 'F');
		
		// Save meta data to order
		$pdf_url = get_stylesheet_directory_uri() . '/Uplatnice/' . $filename;
		$order->update_meta_data('su_payment_slip_path', $file_path);
		$order->update_meta_data('su_payment_slip_url', $pdf_url);
		$order->save();
		
		error_log("SU Payment Slip: Generated for order #{$order_id}: {$file_path}");
		
		return $file_path;
		
	} catch (Exception $e) {
		error_log("SU Payment Slip: Error generating for order #{$order_id}: " . $e->getMessage());
		return false;
	}
}

// Auto-generate payment slip for orders with BACS payment method
add_action('woocommerce_order_status_on-hold', 'su_auto_generate_payment_slip', 20, 2);
add_action('woocommerce_order_status_pending', 'su_auto_generate_payment_slip', 20, 2);

function su_auto_generate_payment_slip( $order_id, $order = null ) {
	if ( ! $order ) { $order = wc_get_order( $order_id ); }
	if ( ! $order ) { return; }
	
	// Only generate for BACS (bank transfer) payment method
	if ( $order->get_payment_method() !== 'bacs' ) {
		return;
	}
	
	// Skip if payment slip already exists
	$existing_slip = $order->get_meta('su_payment_slip_url');
	if ( $existing_slip ) {
		error_log("SU Payment Slip: Order #{$order_id} already has payment slip, skipping.");
		return;
	}
	
	error_log("SU Payment Slip: Auto-generating for order #{$order_id} (BACS payment).");
	su_generate_payment_slip_pdf( $order_id );
}
