<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Generate Serbian Payment Slip (Uplatnica) PDF - Standard Format
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
		// Create new PDF document (A4 landscape)
		$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
		
		$pdf->SetCreator('SoftUni doo');
		$pdf->SetAuthor('SoftUni doo');
		$pdf->SetTitle('Uplatnica - Narudžbina ' . $order->get_order_number());
		
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(10, 10, 10);
		$pdf->SetAutoPageBreak(false);
		$pdf->AddPage();
		
		// Title - right aligned
		$pdf->SetFont('dejavusans', 'B', 16);
		$pdf->Cell(0, 10, 'NALOG ZA UPLATU', 0, 1, 'R');
		$pdf->Ln(3);
		
		// Define positions
		$leftX = 10;
		$rightX = 145;
		$startY = 28;
		$currentY = $startY;
		
		// LEFT COLUMN
		// 1. PLATILAC
		$pdf->SetXY($leftX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(50, 5, 'platilac', 0, 1, 'L');
		
		$pdf->Rect($leftX, $currentY + 5, 125, 30);
		$pdf->SetXY($leftX + 2, $currentY + 7);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(121, 5, $payer_name . "\n" . $payer_address . "\n" . $payer_city, 0, 'L');
		
		$currentY += 35;
		
		// 2. SVRHA UPLATE
		$pdf->SetXY($leftX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(50, 5, 'svrha uplate', 0, 1, 'L');
		
		$pdf->Rect($leftX, $currentY + 5, 125, 30);
		$pdf->SetXY($leftX + 2, $currentY + 7);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(121, 5, $purpose . "\nNarudžbina #" . $order->get_order_number(), 0, 'L');
		
		$currentY += 35;
		
		// 3. PRIMALAC
		$pdf->SetXY($leftX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(50, 5, 'primalac', 0, 1, 'L');
		
		$pdf->Rect($leftX, $currentY + 5, 125, 30);
		$pdf->SetXY($leftX + 2, $currentY + 7);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->MultiCell(121, 5, $receiver_name . "\n" . $receiver_address, 0, 'L');
		
		$currentY += 35;
		
		// 4. PECAT I POTPIS + MESTO I DATUM
		$pdf->SetXY($leftX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(62, 5, 'pečat i potpis  platioca', 0, 0, 'L');
		$pdf->Cell(63, 5, 'mesto i datum prijema', 0, 1, 'L');
		
		$pdf->Rect($leftX, $currentY + 5, 62, 15);
		$pdf->Rect($leftX + 63, $currentY + 5, 62, 15);
		
		// RIGHT COLUMN
		$currentY = $startY;
		
		// 1. ŠIFRA PLAĆANJA + VALUTA + IZNOS
		$pdf->SetXY($rightX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(20, 5, 'šifra', 0, 0, 'L');
		$pdf->Cell(27, 5, 'valuta', 0, 0, 'L');
		$pdf->Cell(0, 5, 'iznos', 0, 1, 'L');
		
		$pdf->Rect($rightX, $currentY + 5, 20, 12);
		$pdf->Rect($rightX + 21, $currentY + 5, 26, 12);
		$pdf->Rect($rightX + 48, $currentY + 5, 124, 12);
		
		$pdf->SetXY($rightX, $currentY + 5);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->Cell(20, 12, $payment_code, 0, 0, 'C');
		$pdf->Cell(27, 12, $currency, 0, 0, 'C');
		$pdf->SetFont('dejavusans', 'B', 11);
		$pdf->Cell(0, 12, number_format($amount, 2, ',', '.'), 0, 1, 'C');
		
		$currentY += 17;
		
		// 2. RAČUN PRIMAOCA
		$pdf->SetXY($rightX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(50, 5, 'račun primaoca', 0, 1, 'L');
		
		$pdf->Rect($rightX, $currentY + 5, 142, 12);
		$pdf->SetXY($rightX, $currentY + 5);
		$pdf->SetFont('dejavusans', 'B', 12);
		$pdf->SetTextColor(200, 0, 0); // RED
		$pdf->Cell(142, 12, $receiver_account, 0, 1, 'C');
		$pdf->SetTextColor(0, 0, 0);
		
		$currentY += 17;
		
		// 3. MODEL + POZIV NA BROJ
		$pdf->SetXY($rightX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(0, 5, 'model i poziv na broj (odobrenje)', 0, 1, 'L');
		
		$pdf->Rect($rightX, $currentY + 5, 20, 12);
		$pdf->Rect($rightX + 21, $currentY + 5, 121, 12);
		
		$pdf->SetXY($rightX, $currentY + 5);
		$pdf->SetFont('dejavusans', '', 11);
		$pdf->Cell(20, 12, $model, 0, 0, 'C');
		$pdf->Cell(121, 12, $reference_number, 0, 1, 'C');
		
		$currentY += 17;
		
		// 4. DATUM IZVRŠENJA
		$pdf->SetXY($rightX, $currentY);
		$pdf->SetFont('dejavusans', 'B', 8);
		$pdf->Cell(50, 5, 'datum izvršenja', 0, 1, 'L');
		
		$pdf->Rect($rightX, $currentY + 5, 142, 12);
		
		// Footer
		$pdf->SetY(185);
		$pdf->SetFont('dejavusans', 'I', 8);
		$pdf->SetTextColor(100, 100, 100);
		$pdf->MultiCell(0, 4, 
			"Ovaj dokument možete odštampati i odneti u banku ili koristiti podatke za online plaćanje.\n" .
			"Za online plaćanje unesite gornje podatke u vašoj banking aplikaciji.",
			0, 'C'
		);
		
		$pdf->Output($file_path, 'F');
		
		// Save to order meta
		$file_url = get_stylesheet_directory_uri() . '/Uplatnice/' . $filename;
		$order->update_meta_data( 'su_payment_slip_path', $file_path );
		$order->update_meta_data( 'su_payment_slip_url', $file_url );
		$order->save();
		
		error_log("SU Payment Slip: Generated successfully for order #{$order_id} at {$file_path}");
		return $file_url;
		
	} catch (Exception $e) {
		error_log("SU Payment Slip: Error generating PDF - " . $e->getMessage());
		return false;
	}
}

/**
 * Auto-generate payment slip when order status changes to on-hold or pending (BACS only)
 */
add_action( 'woocommerce_order_status_on-hold', 'su_auto_generate_payment_slip', 10, 1 );
add_action( 'woocommerce_order_status_pending', 'su_auto_generate_payment_slip', 10, 1 );

function su_auto_generate_payment_slip( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	
	// Only generate for BACS payment method
	if ( $order->get_payment_method() !== 'bacs' ) {
		return;
	}
	
	// Skip if already exists
	if ( $order->get_meta( 'su_payment_slip_url' ) ) {
		return;
	}
	
	error_log("SU Payment Slip: Auto-generating for order #{$order_id} (BACS payment)");
	su_generate_payment_slip_pdf( $order_id );
}
