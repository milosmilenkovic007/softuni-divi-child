<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Admin: Adds a meta box on WooCommerce order edit page with a button
 * to generate and save FAKTURA/PROFAKTURA (PDF) using the same
 * jsPDF client logic as the thank-you page.
 */

// Classic orders screen (post type shop_order)
add_action('add_meta_boxes', function(){
	add_meta_box(
		'su_invoice_metabox',
		__('Faktura / Profaktura (PDF)', 'divi-child'),
		'su_render_invoice_metabox',
		'shop_order',
		'side',
		'high'
	);
});

/**
 * Build structured payload for jsPDF.
 */
function su_build_invoice_payload( $order ){
	$customer_type = $order->get_meta('customer_type');
	$is_company = ($customer_type === 'company');
	$doc_title = $is_company ? 'PROFAKTURA' : 'FAKTURA';

	$seller = [
		'name'        => 'SoftUni doo',
		'address'     => 'Pivljanina Baja 1, 11000 Beograd, Srbija',
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

	$fmt_money = function( $amount ) use ( $order ){
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, [ 'currency' => $order->get_currency() ] ) ), ENT_QUOTES, 'UTF-8' );
	};

	$items = [];
	foreach ( $order->get_items() as $item_id => $item ) {
		// Get product name from item
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
		
		$qty   = (int) wc_get_order_item_meta( $item_id, '_qty', true );
		$line_total = (float) wc_get_order_item_meta( $item_id, '_line_total', true );
		
		// Use product name as-is (WooCommerce may already include SKU)
		$items[] = [ 
			'name' => $name, 
			'sku' => $sku, 
			'qty' => $qty ?: 1, 
			'total' => $fmt_money( $line_total ) 
		];
	}

	return [
		'docTitle' => $doc_title,
		'seller'   => $seller,
		'buyer'    => [ 'lines' => array_values( $buyer_lines ) ],
		'items'    => $items,
		'subtotal' => $fmt_money( $order->get_subtotal() ),
		'total'    => $fmt_money( $order->get_total() ),
		'meta'     => [
			'invoice_no'     => $order->get_meta('su_invoice_number') ?: null,
			'invoice_date'   => wc_format_datetime( $order->get_date_created(), 'F j, Y' ),
			'order_no'       => $order->get_id(),
			'order_date'     => wc_format_datetime( $order->get_date_created(), 'F j, Y' ),
			'payment_method' => $order->get_payment_method_title(),
		],
	];
}

// Render callback
function su_render_invoice_metabox( $post ){ 
	$order = wc_get_order( $post->ID );
	if( ! $order ){ echo '<p>' . esc_html__('Naručbina nije pronađena.', 'divi-child') . '</p>'; return; }
	$data = su_build_invoice_payload( $order );
	$doc_title = $data['docTitle'];
	$nonce = wp_create_nonce( 'su_save_invoice_pdf_' . $order->get_id() );
	echo '<p>' . esc_html__('Klikom na dugme generisaćete i preuzeti PDF, a kopija će biti sačuvana u /Fakture i povezana sa narudžbinom.', 'divi-child') . '</p>';
	echo '<p><button id="su-download-invoice" class="button button-primary" data-order-id="' . esc_attr($order->get_id()) . '" data-nonce="' . esc_attr($nonce) . '" data-doc-type="' . esc_attr( $doc_title ) . '">' . sprintf( esc_html__('Generiši %s (PDF)', 'divi-child'), esc_html($doc_title) ) . '</button></p>';
	echo '<script id="su-invoice-data" type="application/json">' . wp_json_encode( $data ) . '</script>';
	$GLOBALS['su_invoice_meta_box_printed'] = true;
}

// Fallback inline button if meta box isn't rendered (e.g. HPOS UI)
add_action('woocommerce_admin_order_data_after_billing_address', function( $order ){
	if ( ! $order instanceof WC_Order ) { return; }
	if ( ! empty( $GLOBALS['su_invoice_meta_box_printed'] ) ) { return; }
	$data = su_build_invoice_payload( $order );
	$doc_title = $data['docTitle'];
	$nonce = wp_create_nonce( 'su_save_invoice_pdf_' . $order->get_id() );
	echo '<div class="su-invoice-admin-inline" style="margin-top:12px;padding:10px 0;border-top:1px solid #ddd">'
		. '<button id="su-download-invoice" class="button" data-order-id="' . esc_attr($order->get_id()) . '" data-nonce="' . esc_attr($nonce) . '" data-doc-type="' . esc_attr( $doc_title ) . '">' . sprintf( esc_html__('Generiši %s (PDF)', 'divi-child'), esc_html($doc_title) ) . '</button>'
		. '</div>';
	echo '<script id="su-invoice-data" type="application/json">' . wp_json_encode( $data ) . '</script>';
}, 25);
