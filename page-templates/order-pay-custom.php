<?php
/**
 * Template Name: SoftUni Order Pay (Custom)
 * Description: Custom order payment page for credit card payments
 */

// Redirect to home if no order key
if ( ! isset( $_GET['key'] ) ) {
	wp_safe_redirect( home_url() );
	exit;
}

$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
$order_id = wc_get_order_id_by_order_key( $order_key );
$order = $order_id ? wc_get_order( $order_id ) : null;

if ( ! $order ) {
	wp_safe_redirect( home_url() );
	exit;
}

// Prevent template caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( sprintf( __('Plaćanje porudžbine #%s', 'divi-child'), $order->get_order_number() ) ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Reset for content area only */
		.orderpay-container * { box-sizing: border-box; }
		
		body.softuni-orderpay-page {
			background: #f5f7fa;
		}
		
		/* Main Content */
		.orderpay-container {
			max-width: 1100px;
			margin: 40px auto;
			padding: 0 20px;
		}
		
		/* Order Info Cards */
		.order-info-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			gap: 20px;
			margin-bottom: 30px;
		}
		
		.info-card {
			background: white;
			border-radius: 12px;
			padding: 20px;
			border-left: 4px solid #234465;
			box-shadow: 0 2px 8px rgba(0,0,0,0.08);
		}
		
		.info-card-label {
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 1px;
			color: #6c757d;
			margin-bottom: 8px;
			font-weight: 600;
		}
		
		.info-card-value {
			font-size: 18px;
			font-weight: 700;
			color: #212529;
		}
		
		/* Two Column Layout */
		.two-column-sections {
			display: grid;
			grid-template-columns: 2fr 1fr;
			gap: 30px;
			margin-bottom: 30px;
		}
		
		/* Order Details Section */
		.details-section {
			background: white;
			border-radius: 12px;
			padding: 30px;
			box-shadow: 0 2px 12px rgba(0,0,0,0.08);
		}
		
		.details-section h2 {
			font-size: 20px;
			font-weight: 700;
			color: #212529;
			margin: 0 0 20px;
			padding-bottom: 10px;
			border-bottom: 3px solid #234465;
		}
		
		/* Order Table */
		.order-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 0;
		}
		
		.order-table thead {
			background: #2c3e50;
			color: white;
		}
		
		.order-table th {
			padding: 15px;
			text-align: left;
			font-weight: 600;
			font-size: 14px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		
		.order-table td {
			padding: 15px;
			border-bottom: 1px solid #e9ecef;
		}
		
		.order-table tbody tr:last-child td {
			border-bottom: none;
		}
		
		.order-table .text-right {
			text-align: right;
		}
		
		.order-table tfoot {
			background: #f8f9fa;
		}
		
		.order-table tfoot th,
		.order-table tfoot td {
			padding: 12px 15px;
			border-top: 2px solid #dee2e6;
		}
		
		.order-table tfoot .total-row th,
		.order-table tfoot .total-row td {
			font-size: 20px;
			font-weight: 700;
			color: #234465;
			padding-top: 15px;
		}
		
		/* Payment Button */
		.payment-action {
			text-align: center;
			margin: 30px 0;
		}
		
		/* Inline Payment Action (inside details-section) */
		.payment-action-inline {
			margin-top: 30px;
			padding-top: 20px;
			border-top: 2px solid #e9ecef;
		}
		
		/* Style NestPay button */
		.payment-action #nestpay-payment-form,
		.payment-action-inline #nestpay-payment-form {
			display: block;
			text-align: left;
		}
		
		.payment-action .button-proceed,
		.payment-action input[type="submit"],
		.payment-action-inline .button-proceed,
		.payment-action-inline input[type="submit"] {
			background: linear-gradient(135deg, #28a745 0%, #234465 100%) !important;
			color: white !important;
			padding: 18px 50px !important;
			border: none !important;
			border-radius: 50px !important;
			font-size: 18px !important;
			font-weight: 700 !important;
			cursor: pointer !important;
			box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3) !important;
			transition: all 0.3s ease !important;
			text-decoration: none !important;
			display: inline-block !important;
		}
		
		.payment-action .button-proceed:hover,
		.payment-action input[type="submit"]:hover,
		.payment-action-inline .button-proceed:hover,
		.payment-action-inline input[type="submit"]:hover {
			transform: translateY(-2px) !important;
			box-shadow: 0 12px 30px rgba(40, 167, 69, 0.4) !important;
		}
		
		/* hCaptcha spacing */
		.payment-action .h-captcha,
		.payment-action-inline .h-captcha {
			display: inline-block;
			margin-bottom: 20px;
		}
		
		/* Address Block */
		.address-block {
			background: #f8f9fa;
			padding: 20px;
			border-radius: 8px;
			border-left: 4px solid #1e88e5;
			line-height: 1.8;
		}
		
		/* Custom Footer */
		.softuni-custom-footer {
			background: #2c3e50;
			color: white;
			padding: 30px 0;
			margin-top: 60px;
		}
		
		.footer-content {
			max-width: 1100px;
			margin: 0 auto;
			padding: 0 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 20px;
		}
		
		.footer-left {
			font-size: 14px;
		}
		
		.footer-right {
			display: flex;
			gap: 25px;
		}
		
		.footer-right a {
			color: white;
			text-decoration: none;
			font-size: 14px;
			transition: opacity 0.3s;
		}
		
		.footer-right a:hover {
			opacity: 0.8;
		}
		
		@media (max-width: 768px) {
			.orderpay-container {
				margin: 20px auto;
			}
			
			.two-column-sections {
				grid-template-columns: 1fr;
				gap: 20px;
			}
			
			.details-section {
				padding: 20px;
			}
			
			.order-table {
				font-size: 14px;
			}
			
			.order-table th,
			.order-table td {
				padding: 10px;
			}
			
			.footer-content {
				flex-direction: column;
				text-align: center;
			}
			
			.footer-right {
				flex-direction: column;
				gap: 10px;
			}
		}
	</style>
</head>
<body <?php body_class('softuni-orderpay-page'); ?>>

<?php
// Include standard header
get_header();
?>

<div class="orderpay-container">
	
	<!-- Order Info Cards -->
	<div class="order-info-grid">
		<div class="info-card">
			<div class="info-card-label"><?php esc_html_e( 'BROJ NARUDŽBINE', 'divi-child' ); ?></div>
			<div class="info-card-value"><?php echo esc_html( $order->get_order_number() ); ?></div>
		</div>
		
		<div class="info-card">
			<div class="info-card-label"><?php esc_html_e( 'DATUM', 'divi-child' ); ?></div>
			<div class="info-card-value"><?php echo esc_html( $order->get_date_created()->date_i18n( 'F j, Y' ) ); ?></div>
		</div>
		
		<div class="info-card">
			<div class="info-card-label"><?php esc_html_e( 'EMAIL', 'divi-child' ); ?></div>
			<div class="info-card-value"><?php echo esc_html( $order->get_billing_email() ); ?></div>
		</div>
		
		<div class="info-card">
			<div class="info-card-label"><?php esc_html_e( 'UKUPNO', 'divi-child' ); ?></div>
			<div class="info-card-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
		</div>
	</div>
	
	<!-- Two Column Layout: Order Details + Billing Address -->
	<div class="two-column-sections">
		<!-- Order Details -->
		<div class="details-section">
			<h2><?php esc_html_e( 'Detalji narudžbine', 'divi-child' ); ?></h2>
			
			<table class="order-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Proizvod', 'divi-child' ); ?></th>
						<th class="text-right"><?php esc_html_e( 'Ukupno', 'divi-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $order->get_items() as $item_id => $item ) {
						$product = $item->get_product();
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $item->get_name() ); ?></strong>
								<?php if ( $product && $product->get_sku() ) : ?>
									<br><small style="color: #6c757d;"><?php echo esc_html( $product->get_sku() ); ?></small>
								<?php endif; ?>
								<br><small style="color: #6c757d;">× <?php echo esc_html( $item->get_quantity() ); ?></small>
							</td>
							<td class="text-right">
								<?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e( 'Svega:', 'divi-child' ); ?></th>
						<td class="text-right"><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td>
					</tr>
					<tr class="total-row">
						<th><?php esc_html_e( 'Ukupno:', 'divi-child' ); ?></th>
						<td class="text-right"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Način plaćanja:', 'divi-child' ); ?></th>
						<td class="text-right"><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
					</tr>
				</tfoot>
			</table>
		</div>
		
		<!-- Billing Address -->
		<div class="details-section">
			<h2><?php esc_html_e( 'Adresa za naplatu', 'divi-child' ); ?></h2>
			<div class="address-block">
				<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
				<?php if ( $order->get_billing_phone() ) : ?>
					<br><strong><?php esc_html_e( 'Telefon:', 'divi-child' ); ?></strong> <?php echo esc_html( $order->get_billing_phone() ); ?>
				<?php endif; ?>
			</div>
			
			<!-- Payment Action -->
			<?php if ( $order->needs_payment() ) : ?>
				<div class="payment-action-inline">
					<?php
					// Directly call NestPay receipt page to generate payment form
					$chosen_payment_method = $order->get_payment_method();
					
					error_log('Order-Pay Custom Template: Order ID = ' . $order->get_id());
					error_log('Order-Pay Custom Template: Payment Method = ' . $chosen_payment_method);
					error_log('Order-Pay Custom Template: Order needs payment = ' . ($order->needs_payment() ? 'YES' : 'NO'));
					
					if ( $chosen_payment_method ) {
						error_log('Order-Pay Custom Template: Calling woocommerce_receipt_' . $chosen_payment_method);
						// This will render the NestPay payment form with proper hash
						do_action( 'woocommerce_receipt_' . $chosen_payment_method, $order->get_id() );
						error_log('Order-Pay Custom Template: Receipt action called');
					} else {
						error_log('Order-Pay Custom Template: ERROR - No payment method found');
						?>
						<p style="color: #dc3545; font-weight: 600;">
							<?php esc_html_e( 'Način plaćanja nije dostupan. Molimo kontaktirajte podršku.', 'divi-child' ); ?>
						</p>
						<?php
					}
					?>
				</div>
			<?php else : ?>
				<div class="payment-action-inline">
					<p style="color: #28a745; font-weight: 600; font-size: 16px;">
						✓ <?php esc_html_e( 'Ova porudžbina je već plaćena.', 'divi-child' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	
</div><!-- .orderpay-container -->

<!-- Custom Footer -->
<footer class="softuni-custom-footer">
	<div class="footer-content">
		<div class="footer-left">
			Copyright © 2025 SOFTUNI
		</div>
		<div class="footer-right">
			<a href="<?php echo esc_url( home_url('/politika-privatnosti/') ); ?>">Politika privatnosti</a>
			<a href="<?php echo esc_url( home_url('/opsta-pravila/') ); ?>">Opšta pravila</a>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
