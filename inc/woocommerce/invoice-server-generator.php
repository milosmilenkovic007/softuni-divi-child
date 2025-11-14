<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Server-side PDF generator using native PHP approach.
 * Generates invoice PDF without requiring client-side JavaScript.
 */

/**
 * Generate PDF invoice server-side for a given order.
 * Returns file path on success, false on failure.
 */
function su_generate_invoice_pdf_server_side( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		error_log("SU Invoice: Order #{$order_id} not found.");
		return false;
	}

	// Build invoice data
	$customer_type = $order->get_meta('customer_type');
	$is_company = ($customer_type === 'company');
	$order_status = $order->get_status();
	
	// Determine document type:
	// - Company + not completed → PROFAKTURA
	// - Company + completed → FAKTURA
	// - Individual → always FAKTURA
	if ( $is_company && $order_status !== 'completed' ) {
		$doc_title = 'PROFAKTURA';
	} else {
		$doc_title = 'FAKTURA';
	}

	$seller = [
		'name'        => 'SoftUni doo',
		'address'     => 'Pivljanina Baja 1, 11000 Beograd, Srbija',
		'city'        => '11000 Beograd',
		'country'     => 'Srbija',
		'activity'    => 'delatnost i sifra delatnosti: 8559 - Ostalo obrazovanje',
		'mb'          => 'maticni broj: 21848891',
		'pib'         => 'poreski broj: 113341376',
	];

	$buyer = [
		'name'  => trim($order->get_formatted_billing_full_name()),
		'addr1' => trim($order->get_billing_address_1()),
		'addr2' => trim($order->get_billing_address_2()),
		'city'  => trim($order->get_billing_postcode() . ' ' . $order->get_billing_city()),
		'email' => trim($order->get_billing_email()),
		'phone' => trim($order->get_billing_phone()),
	];
	
	// For company (PROFAKTURA), add company details
	if ( $is_company ) {
		$company_name = trim($order->get_meta('billing_company'));
		$company_mb = trim($order->get_meta('billing_mb'));
		$company_pib = trim($order->get_meta('billing_pib'));
		
		// Override name with company name if provided
		if ( $company_name ) {
			$buyer['name'] = $company_name;
		}
		
		$buyer['mb'] = $company_mb;
		$buyer['pib'] = $company_pib;
	}

	$invoice_no = $order->get_meta('su_invoice_number');
	if ( ! $invoice_no ) {
		$invoice_no = su_dc_next_invoice_number( $order_id );
	}

	$invoice_date = $order->get_date_created() ? $order->get_date_created()->date_i18n('F j, Y') : date('F j, Y');
	$invoice_date = su_localize_date_serbian( $invoice_date );
	$order_date = $invoice_date;

	// Items
	$items = [];
	foreach ( $order->get_items() as $item_id => $item ) {
		// Get product name
		$name = '';
		if ( is_object($item) && method_exists($item, 'get_name') ) {
			$name = $item->get_name();
		}
		if ( ! $name ) {
			$name = wc_get_order_item_meta( $item_id, 'name', true );
		}
		$name = $name ? $name : __('Stavka', 'divi-child');
		
		// Get product SKU
		$sku = '';
		if ( is_object($item) && method_exists($item, 'get_product') ) {
			$product = $item->get_product();
			if ( $product && method_exists($product, 'get_sku') ) {
				$sku = $product->get_sku();
			}
		}
		
		// Use ONLY the product name (WooCommerce already includes SKU in name if configured)
		$item_name = $name;
		
		$qty = (int) wc_get_order_item_meta( $item_id, '_qty', true );
		$line_total = (float) wc_get_order_item_meta( $item_id, '_line_total', true );
		$items[] = [
			'name'  => $item_name,
			'qty'   => $qty ?: 1,
			'total' => wc_price( $line_total, ['currency' => $order->get_currency()] ),
		];
	}

	$subtotal = wc_price( $order->get_subtotal(), ['currency' => $order->get_currency()] );
	$total = wc_price( $order->get_total(), ['currency' => $order->get_currency()] );

	$order_no = $order->get_id();
	$payment_method = $order->get_payment_method_title();

	$theme_dir = get_stylesheet_directory();
	$fakture_dir = $theme_dir . '/Fakture';
	if ( ! file_exists( $fakture_dir ) ) {
		wp_mkdir_p( $fakture_dir );
	}

	$filename = strtolower( $doc_title ) . '_' . $invoice_no . '.pdf';
	$file_path = $fakture_dir . '/' . $filename;

	// Use TCPDF for PDF generation (pure PHP, no external dependencies)
	require_once get_stylesheet_directory() . '/inc/woocommerce/tcpdf/tcpdf.php';
	
	try {
		// Create new PDF document
		$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
		
		// Set document information
		$pdf->SetCreator('SoftUni doo');
		$pdf->SetAuthor('SoftUni doo');
		$pdf->SetTitle($doc_title . ' ' . $invoice_no);
		$pdf->SetSubject('Faktura');
		
		// Remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		
		// Set margins
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 15);
		
		// Add a page
		$pdf->AddPage();
		
		// Logo at top left
		$logo_path = get_stylesheet_directory() . '/assets/img/softunilogo.png';
		if (file_exists($logo_path)) {
			$pdf->Image($logo_path, 15, 15, 40, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
		}
		
		// Seller info (top right)
		$pdf->SetFont('dejavusans', '', 8);
		$pdf->SetXY(120, 15);
		$pdf->MultiCell(75, 0, 
			$seller['name'] . "\n" .
			$seller['address'] . "\n" .
			$seller['city'] . "\n" .
			$seller['country'] . "\n" .
			$seller['activity'] . "\n" .
			$seller['mb'] . "\n" .
			$seller['pib'],
			0, 'R', 0, 1, '', '', true, 0, false, true, 0, 'T'
		);
		
		// Document title (centered, bold)
		$pdf->SetY(60);
		$pdf->SetFont('dejavusans', 'B', 18);
		$pdf->Cell(0, 10, $doc_title, 0, 1, 'C');
		
		// Buyer section (left) and Invoice details (right)
		$pdf->SetY(75);
		
		// Left column - Buyer
		$pdf->SetFont('dejavusans', 'B', 9);
		$pdf->SetX(15);
		$pdf->Cell(0, 5, 'Kupac:', 0, 1, 'L');
		
		$pdf->SetFont('dejavusans', '', 9);
		
		// For PROFAKTURA, show company details with labels
		if ( $is_company ) {
			$buyer_text = "Pravno lice: " . $buyer['name'] . "\n" .
			              $buyer['addr1'] . "\n" .
			              ($buyer['addr2'] ? $buyer['addr2'] . "\n" : '') .
			              $buyer['city'] . "\n" .
			              $buyer['email'] . "\n" .
			              $buyer['phone'];
			
			if ( !empty($buyer['mb']) ) {
				$buyer_text .= "\nMaticni broj: " . $buyer['mb'];
			}
			if ( !empty($buyer['pib']) ) {
				$buyer_text .= "\nPIB: " . $buyer['pib'];
			}
		} else {
			// For FAKTURA, show individual details
			$buyer_text = $buyer['name'] . "\n" .
			              $buyer['addr1'] . "\n" .
			              ($buyer['addr2'] ? $buyer['addr2'] . "\n" : '') .
			              $buyer['city'] . "\n" .
			              $buyer['email'] . "\n" .
			              $buyer['phone'];
		}
		
		$pdf->SetX(15);
		$pdf->MultiCell(90, 5, $buyer_text, 0, 'L', 0, 0);
		
		// Right column - Invoice details
		$pdf->SetXY(105, 80);
		$pdf->SetFont('dejavusans', '', 9);
		$details_html = '<table cellpadding="3" style="border: 0.5px solid #333;">
			<tr>
				<td style="width: 50mm; border-right: 0.5px solid #ccc;"><strong>Broj fakture:</strong></td>
				<td style="width: 40mm;">' . htmlspecialchars($invoice_no) . '</td>
			</tr>
			<tr style="background-color: #f9f9f9;">
				<td style="border-right: 0.5px solid #ccc;"><strong>Datum fakture:</strong></td>
				<td>' . htmlspecialchars($invoice_date) . '</td>
			</tr>
			<tr>
				<td style="border-right: 0.5px solid #ccc;"><strong>Broj porudzbine:</strong></td>
				<td>' . htmlspecialchars($order_no) . '</td>
			</tr>
			<tr style="background-color: #f9f9f9;">
				<td style="border-right: 0.5px solid #ccc;"><strong>Datum porudzbine:</strong></td>
				<td>' . htmlspecialchars($order_date) . '</td>
			</tr>
			<tr>
				<td style="border-right: 0.5px solid #ccc;"><strong>Nacin placanja:</strong></td>
				<td>' . htmlspecialchars($payment_method) . '</td>
			</tr>
		</table>';
		
		$pdf->writeHTMLCell(90, 0, 105, 80, $details_html, 0, 1, 0, true, 'L', true);
		
		// Items table
		$pdf->SetY(145);
		
		$items_html = '<table border="1" cellpadding="6" style="border-collapse: collapse;">
			<thead>
				<tr style="background-color: #333; color: #fff; font-weight: bold;">
					<th style="width: 105mm; text-align: left;">Proizvod</th>
					<th style="width: 30mm; text-align: center;">Kolicina</th>
					<th style="width: 45mm; text-align: right;">Cena</th>
				</tr>
			</thead>
			<tbody>';
		
		$row_bg = true;
		foreach ($items as $item) {
			$bg_color = $row_bg ? ' style="background-color: #f9f9f9;"' : '';
			// Strip HTML entities and tags from price
			$clean_price = html_entity_decode(strip_tags($item['total']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			
			// Convert \n to <br> for HTML display
			$item_name_html = nl2br(htmlspecialchars($item['name']), false);
			
			$items_html .= '<tr' . $bg_color . '>
				<td style="text-align: left;">' . $item_name_html . '</td>
				<td style="text-align: center;">' . htmlspecialchars($item['qty']) . '</td>
				<td style="text-align: right;">' . htmlspecialchars($clean_price) . '</td>
			</tr>';
			$row_bg = !$row_bg;
		}
		
		$items_html .= '</tbody></table>';
		
		$pdf->SetFont('dejavusans', '', 9);
		$pdf->writeHTML($items_html, true, false, true, false, '');
		
		// Total section - only UKUPNO
		$pdf->Ln(8);
		$pdf->SetFont('dejavusans', 'B', 13);
		
		// Clean total from HTML entities
		$clean_total = html_entity_decode(strip_tags($total), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		
		$pdf->Cell(135, 10, '', 0, 0, 'R');
		$pdf->Cell(45, 10, 'UKUPNO: ' . $clean_total, 'T', 1, 'R');
		
		// Footer
		$pdf->Ln(10);
		$pdf->SetFont('dejavusans', 'I', 8);
		$pdf->Cell(0, 5, 'PDV je ukljucen u cenu.', 'T', 1, 'C');
		
		// Output to file
		$pdf->Output($file_path, 'F');
		
		if ( ! file_exists($file_path) || filesize($file_path) < 100 ) {
			error_log("SU Invoice: TCPDF failed to generate PDF for order #{$order_id}.");
			return false;
		}
	} catch (Exception $e) {
		error_log("SU Invoice: TCPDF exception for order #{$order_id}: " . $e->getMessage());
		return false;
	}

	// Update order meta
	$order->update_meta_data( 'su_pdf_title', $doc_title );
	$order->update_meta_data( 'su_pdf_path', $file_path );
	$order->update_meta_data( 'su_pdf_url', get_stylesheet_directory_uri() . '/Fakture/' . $filename );
	$order->save();

	$order->add_order_note( sprintf( __('Faktura automatski generisana: %s', 'divi-child'), $filename ) );

	error_log("SU Invoice: PDF generated for order #{$order_id}: {$file_path}");
	return $file_path;
}

/**
 * Localize English month names to Serbian.
 */
function su_localize_date_serbian( $date_str ) {
	$months = [
		'January' => 'januar', 'February' => 'februar', 'March' => 'mart',
		'April' => 'april', 'May' => 'maj', 'June' => 'jun',
		'July' => 'jul', 'August' => 'avgust', 'September' => 'septembar',
		'October' => 'oktobar', 'November' => 'novembar', 'December' => 'decembar'
	];
	foreach ( $months as $en => $sr ) {
		$date_str = str_replace( $en, $sr, $date_str );
	}
	return $date_str;
}
