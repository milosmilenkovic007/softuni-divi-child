<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Sequential invoice number management.
 * Stores a global counter in wp_options: su_invoice_counter
 * Optionally can be extended later for yearly reset.
 */
function su_dc_get_existing_invoice_number( $order ){ return $order->get_meta('su_invoice_number'); }

function su_dc_next_invoice_number( $order_id ){
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return false; }
	$existing = su_dc_get_existing_invoice_number( $order );
	if ( $existing ) { return (int) $existing; }

	// Simple lock to reduce race conditions
	$lock_key = 'su_invoice_lock';
	if ( ! wp_cache_add( $lock_key, 1, 'su_invoice', 5 ) ) {
		// Failed to acquire lock, brief wait then retry once
		usleep( 150000 ); // 150ms
		if ( ! wp_cache_add( $lock_key, 1, 'su_invoice', 5 ) ) {
			return new WP_Error( 'lock_timeout', 'Ne mogu da dobijem zaključavanje za broj fakture.' );
		}
	}

	$counter = (int) get_option( 'su_invoice_counter', 0 );
	$counter++;
	update_option( 'su_invoice_counter', $counter, false );

	// Release lock
	wp_cache_delete( $lock_key, 'su_invoice' );

	$order->update_meta_data( 'su_invoice_number', $counter );
	$order->save();
	return $counter;
}

// Allocate invoice number as soon as order is created (frontend or admin), if missing
add_action('woocommerce_checkout_order_processed', function( $order_id ){
	$order = wc_get_order( $order_id );
	if ( $order && ! su_dc_get_existing_invoice_number( $order ) ) {
		su_dc_next_invoice_number( $order_id );
	}
}, 20);

add_action('woocommerce_new_order', function( $order_id ){
	$order = wc_get_order( $order_id );
	if ( $order && ! su_dc_get_existing_invoice_number( $order ) ) {
		su_dc_next_invoice_number( $order_id );
	}
}, 20);

add_action('woocommerce_order_status_changed', function( $order_id ){
	$order = wc_get_order( $order_id );
	if ( $order && ! su_dc_get_existing_invoice_number( $order ) ) {
		su_dc_next_invoice_number( $order_id );
	}
}, 20);

/**
 * AJAX: fetch (or allocate) invoice number before PDF generation.
 */
function su_dc_ajax_get_invoice_number(){
	$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
	$nonce    = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
	$doc_type = isset($_POST['doc_type']) ? sanitize_text_field( wp_unslash($_POST['doc_type']) ) : 'FAKTURA';
	if ( ! $order_id || ! wp_verify_nonce( $nonce, 'su_save_invoice_pdf_' . $order_id ) ) {
		wp_send_json_error(['message' => 'Neispravan zahtev.'], 400);
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) { wp_send_json_error(['message' => 'Porudžbina nije pronađena.'], 404); }
	$number = su_dc_next_invoice_number( $order_id );
	if ( is_wp_error( $number ) ) {
		wp_send_json_error(['message' => $number->get_error_message()], 500);
	}
	wp_send_json_success([
		'invoice_number' => $number,
		'doc_type'       => $doc_type,
	]);
}
add_action('wp_ajax_su_get_invoice_number', 'su_dc_ajax_get_invoice_number');
add_action('wp_ajax_nopriv_su_get_invoice_number', 'su_dc_ajax_get_invoice_number');

/**
 * Admin submenu listing invoices.
 */
function su_dc_register_invoices_submenu(){
	add_submenu_page(
		'woocommerce',
		'Fakture',
		'Fakture',
		'manage_woocommerce',
		'su-invoices',
		'su_dc_render_invoices_page'
	);
}
add_action('admin_menu', 'su_dc_register_invoices_submenu', 55);

function su_dc_render_invoices_page(){
	if ( ! current_user_can('manage_woocommerce') ) { wp_die('Nedovoljno privilegija.'); }
	
	$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
	$per_page = 20;
	
	// Use WC Order Query for HPOS compatibility
	$args = [
		'limit'      => $per_page,
		'page'       => $paged,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'return'     => 'ids',
		'meta_query' => [
			[
				'key'     => 'su_invoice_number',
				'compare' => 'EXISTS'
			]
		],
	];
	
	$order_ids = wc_get_orders( $args );
	
	// Get total count for pagination
	$args_count = $args;
	$args_count['limit'] = -1;
	$args_count['paginate'] = true;
	$results = wc_get_orders( $args_count );
	$total_orders = $results->total;
	$total_pages = ceil( $total_orders / $per_page );
	
	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">Fakture</h1>';
	echo '<hr class="wp-header-end">';
	
	if ( ! empty( $order_ids ) ) {
		echo '<table class="wp-list-table widefat fixed striped table-view-list">';
		echo '<thead><tr>';
		echo '<th class="manage-column column-primary">Ime i Prezime</th>';
		echo '<th class="manage-column">Broj Porudžbine</th>';
		echo '<th class="manage-column">Broj Fakture</th>';
		echo '<th class="manage-column">Tip</th>';
		echo '<th class="manage-column">Faktura (PDF)</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) { continue; }
			
			$inv_no = $order->get_meta('su_invoice_number');
			$doc_type = $order->get_meta('su_pdf_title');
			$pdf_url = $order->get_meta('su_pdf_url');
			$pdf_path = $order->get_meta('su_pdf_path');
			
			$customer_name = $order->get_formatted_billing_full_name();
			$order_number = $order->get_order_number();
			$link_order = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
			
			// Check if PDF file actually exists
			$pdf_exists = $pdf_path && file_exists($pdf_path);
			
			echo '<tr>';
			echo '<td class="column-primary" data-colname="Ime i Prezime">';
			echo '<strong>' . esc_html( $customer_name ) . '</strong>';
			echo '</td>';
			
			echo '<td data-colname="Broj Porudžbine">';
			echo '<a href="' . esc_url( $link_order ) . '">#' . esc_html( $order_number ) . '</a>';
			echo '</td>';
			
			echo '<td data-colname="Broj Fakture">';
			echo esc_html( $inv_no ? $inv_no : '—' );
			echo '</td>';
			
			echo '<td data-colname="Tip">';
			echo '<span class="dashicons ' . ($doc_type === 'PROFAKTURA' ? 'dashicons-businessperson' : 'dashicons-admin-users') . '"></span> ';
			echo esc_html( $doc_type ? $doc_type : '—' );
			echo '</td>';
			
			echo '<td data-colname="Faktura (PDF)">';
			if ( $pdf_exists && $pdf_url ) {
				echo '<a href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener" class="button button-small">';
				echo '<span class="dashicons dashicons-pdf" style="vertical-align: middle;"></span> Preuzmi';
				echo '</a>';
			} else {
				echo '<span style="color: #999;">Nema PDF-a</span>';
			}
			echo '</td>';
			
			echo '</tr>';
		}
		
		echo '</tbody></table>';
		
		// Pagination
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav bottom">';
			echo '<div class="tablenav-pages">';
			echo paginate_links([
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			]);
			echo '</div>';
			echo '</div>';
		}
	} else {
		echo '<div class="notice notice-info inline"><p>';
		echo '<strong>Nema generisanih faktura.</strong><br>';
		echo 'Fakture se automatski generišu kada porudžbina bude u statusu "Completed", ';
		echo 'ili možete manualno generisati klikom na dugme "Generisi Fakturu" u svakoj porudžbini.';
		echo '</p></div>';
	}
	
	echo '</div>';
}
