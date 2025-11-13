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
	$participants_json = $order->get_meta('participants');
	if ( $participants_json ) {
		$decoded = json_decode( $participants_json, true );
		if ( is_array( $decoded ) ) { $participants = $decoded; }
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

// Keep the placeholder filter (no-op) to show we're wired in
add_filter('woocommerce_email_enabled_customer_invoice', function ($enabled) {
	return $enabled;
}, 1);
