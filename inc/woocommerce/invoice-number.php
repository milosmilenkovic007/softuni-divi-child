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
	$args = [
		'post_type'      => 'shop_order',
		'post_status'    => array_keys( wc_get_order_statuses() ),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'meta_key'       => 'su_invoice_number',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	];
	$q = new WP_Query( $args );
	echo '<div class="wrap"><h1>Fakture</h1>';
	if ( $q->have_posts() ) {
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>Broj</th><th>Tip</th><th>Porudžbina</th><th>Datum</th><th>Ukupno</th><th>Kupac</th><th>PDF</th>';
		echo '</tr></thead><tbody>';
		while ( $q->have_posts() ) { $q->the_post(); $order = wc_get_order( get_the_ID() );
			$inv_no = $order->get_meta('su_invoice_number');
			$doc_type = $order->get_meta('su_pdf_title');
			$pdf_url = $order->get_meta('su_pdf_url');
			$customer = $order->get_formatted_billing_full_name();
			$dt = $order->get_date_created();
			$link_order = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
			echo '<tr>';
			echo '<td>' . esc_html( $inv_no ) . '</td>';
			echo '<td>' . esc_html( $doc_type ? $doc_type : '—' ) . '</td>';
			echo '<td><a href="' . esc_url( $link_order ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
			echo '<td>' . esc_html( $dt ? $dt->date_i18n( 'Y-m-d H:i' ) : '' ) . '</td>';
			echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
			echo '<td>' . esc_html( $customer ) . '</td>';
			echo '<td>' . ( $pdf_url ? '<a target="_blank" rel="noopener" href="' . esc_url( $pdf_url ) . '">Preuzmi</a>' : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$total_pages = $q->max_num_pages;
		if ( $total_pages > 1 ) {
			$base = add_query_arg( 'paged', '%#%' );
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links( [ 'base' => $base, 'format' => '', 'current' => $paged, 'total' => $total_pages ] );
			echo '</div></div>';
		}
	} else {
		echo '<p>Još uvek nema sačuvanih faktura.</p>';
	}
	wp_reset_postdata();
	echo '</div>';
}
