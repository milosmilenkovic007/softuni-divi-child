<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Save client-generated PDF to server and attach to order meta.
 * Accepts base64 data URI from jsPDF on thank-you page.
 */
function su_dc_save_invoice_pdf(){
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $nonce    = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    $title    = isset($_POST['title']) ? sanitize_text_field( wp_unslash($_POST['title']) ) : 'FAKTURA';
    $inv_num  = isset($_POST['invoice_number']) ? absint($_POST['invoice_number']) : 0;
    $filename = isset($_POST['filename']) ? sanitize_file_name( wp_unslash($_POST['filename']) ) : '';
    $dataUri  = isset($_POST['pdf']) ? wp_unslash($_POST['pdf']) : '';

    if ( ! $order_id || ! wp_verify_nonce( $nonce, 'su_save_invoice_pdf_' . $order_id ) ) {
        wp_send_json_error(['message' => 'Neispravan zahtev (nonce/order).'], 400);
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error(['message' => 'Porudžbina nije pronađena.'], 404);
    }

    if ( strpos($dataUri, 'data:application/pdf') === 0 ) {
        $parts = explode(',', $dataUri, 2);
        $base64 = isset($parts[1]) ? $parts[1] : '';
    } else {
        $base64 = $dataUri; // allow raw base64 as fallback
    }
    $binary = base64_decode( $base64 );
    if ( ! $binary ) {
        wp_send_json_error(['message' => 'Ne mogu da dekodiram PDF.'], 400);
    }

    $folder = trailingslashit( DIVICHILD_PATH ) . 'Fakture';
    if ( ! is_dir( $folder ) ) {
        wp_mkdir_p( $folder );
    }

    if ( ! $inv_num ) {
        // Fallback: use stored meta or allocate if missing
        $stored = $order->get_meta('su_invoice_number');
        if ( $stored ) { $inv_num = (int) $stored; }
    }
    if ( ! $inv_num ) {
        $next = su_dc_next_invoice_number( $order_id );
        if ( ! is_wp_error( $next ) ) { $inv_num = (int) $next; }
    }
    if ( ! $filename ) {
        $filename = strtolower( $title ) . '-' . ( $inv_num ? $inv_num : $order->get_order_number() ) . '.pdf';
    }
    $path = trailingslashit( $folder ) . $filename;
    $result = file_put_contents( $path, $binary );
    if ( ! $result ) {
        wp_send_json_error(['message' => 'Greška pri snimanju PDF fajla.'], 500);
    }

    $url = trailingslashit( DIVICHILD_URL ) . 'Fakture/' . rawurlencode( $filename );

    // Save meta for quick access in admin
    $order->update_meta_data( 'su_pdf_title', $title );
    $order->update_meta_data( 'su_pdf_path', $path );
    $order->update_meta_data( 'su_pdf_url', $url );
    if ( $inv_num ) { $order->update_meta_data( 'su_invoice_number', $inv_num ); }
    $order->save();

    // Add order note
    $order->add_order_note( sprintf( '%s snimljena: %s (Broj: %s)', $title, $filename, $inv_num ? $inv_num : 'n/a' ) );
    // Debug log (remove later if noisy)
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log('[SU_INVOICE] Saved ' . $title . ' for order ' . $order_id . ' at ' . $path );
    }

    wp_send_json_success([
        'message' => 'PDF sačuvan.',
        'url'     => $url,
    ]);
}
add_action('wp_ajax_su_save_invoice_pdf', 'su_dc_save_invoice_pdf');
add_action('wp_ajax_nopriv_su_save_invoice_pdf', 'su_dc_save_invoice_pdf');
