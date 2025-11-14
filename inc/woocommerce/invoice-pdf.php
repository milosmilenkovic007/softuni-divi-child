<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Injects a Download PDF button on the thank-you page and exposes
 * structured order data to JS to generate FAKTURA/PROFAKTURA via jsPDF.
 */
add_action('woocommerce_thankyou', function( $order_id ){
	if ( ! $order_id ) { return; }
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }

	$customer_type = $order->get_meta('customer_type');
	$is_company = ($customer_type === 'company');
	$doc_title = $is_company ? 'PROFAKTURA' : 'FAKTURA';
	
	// Seller (company) details - keep in sync with legal footer
	$seller = [
		'name'        => 'SoftUni doo',
		'address'     => 'Pivljanina Baja 1, 11000 Beograd, Srbija',
		'activity'    => 'delatnost i sifra delatnosti: 8559 - Ostalo obrazovanje',
		'mb'          => 'maticni broj: 21848891',
		'pib'         => 'poreski broj: 113341376',
	];

	// Buyer details (billing)
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
		
		// For PROFAKTURA, show company details with labels
		$buyer_lines = array_filter([
			'Pravno lice: ' . ($company_name ?: $buyer['name']),
			$buyer['addr1'],
			$buyer['addr2'],
			$buyer['city'],
			$buyer['email'],
			$buyer['phone'],
		]);
		
		// Add company details to buyer lines
		if ( $company_mb ) {
			$buyer_lines[] = 'Maticni broj: ' . $company_mb;
		}
		if ( $company_pib ) {
			$buyer_lines[] = 'PIB: ' . $company_pib;
		}
	} else {
		// For FAKTURA, show individual details without labels
		$buyer_lines = array_filter([
			$buyer['name'],
			$buyer['addr1'],
			$buyer['addr2'],
			$buyer['city'],
			$buyer['email'],
			$buyer['phone'],
		]);
	}

	// Numbers formatting helper (plain text)
	$fmt_money = function( $amount ) use ( $order ){
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, [ 'currency' => $order->get_currency() ] ) ), ENT_QUOTES, 'UTF-8' );
	};

	// Items
	$items = [];
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$name = $item->get_name();
		$sku = $product ? $product->get_sku() : '';
		
		// Use product name as-is (WooCommerce may already include SKU)
		$items[] = [
			'name'  => $name,
			'sku'   => $sku,
			'qty'   => (int) $item->get_quantity(),
			'total' => $fmt_money( $item->get_total() ),
		];
	}

	$total = $fmt_money( $order->get_total() );
	$subtotal = $fmt_money( $order->get_subtotal() );
	
	$meta = [
		'invoice_no'     => null, // biće dohvaćen AJAX-om (sekvencijalni broj) pre generisanja PDF-a
		'invoice_date'   => wc_format_datetime( $order->get_date_created(), 'F j, Y' ),
		'order_no'       => $order->get_id(),
		'order_date'     => wc_format_datetime( $order->get_date_created(), 'F j, Y' ),
		'payment_method' => $order->get_payment_method_title(),
	];

	$data = [
		'docTitle' => $doc_title,
		'seller'   => $seller,
		'buyer'    => [ 'lines' => array_values($buyer_lines) ],
		'items'    => $items,
		'subtotal' => $subtotal,
		'total'    => $total,
		'meta'     => $meta,
	];

	// Button + data payload
	$nonce = wp_create_nonce( 'su_save_invoice_pdf_' . $order_id );
	echo '<div class="su-invoice-wrap" style="margin:18px 0">';
	echo '<button id="su-download-invoice" class="button alt" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonce) . '" data-doc-type="' . esc_attr( $doc_title ) . '">Preuzmi ' . esc_html( $doc_title ) . ' (PDF)</button>';
	echo '<script id="su-invoice-data" type="application/json">' . wp_json_encode( $data ) . '</script>';
	echo '</div>';
}, 25);
