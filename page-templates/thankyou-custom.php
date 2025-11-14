<?php
/**
 * Template Name: SoftUni Thank You (Custom)
 * Description: Custom thank you page without Divi/WooCommerce defaults
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
	<title><?php esc_html_e( 'Hvala na porudžbini', 'divi-child' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Reset for content area only */
		.thankyou-container * { box-sizing: border-box; }
		
		body.softuni-thankyou-page {
			background: #f5f7fa;
		}
		
		/* Main Content */
		.thankyou-container {
			max-width: 1100px;
			margin: 40px auto;
			padding: 0 20px;
		}
		
		/* Success Banner */
		.success-banner {
			background-image: repeating-linear-gradient(90deg, #00ba96 0%, #3c1f83 100%);
			border-radius: 16px;
			padding: 40px 30px;
			text-align: center;
			color: white;
			margin-bottom: 30px;
			box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
			animation: slideDown 0.6s ease-out;
		}
		
		@keyframes slideDown {
			from { opacity: 0; transform: translateY(-30px); }
			to { opacity: 1; transform: translateY(0); }
		}
		
		.success-icon {
			width: 80px;
			height: 80px;
			margin: 0 auto 20px;
			animation: scaleIn 0.5s ease-out 0.2s both;
		}
		
		@keyframes scaleIn {
			from { transform: scale(0) rotate(-180deg); }
			to { transform: scale(1) rotate(0); }
		}
		
		.success-banner h1 {
			font-size: 32px;
			margin-bottom: 10px;
			font-weight: 700;
			color: #fff;
		}
		
		.success-banner p {
			font-size: 16px;
			opacity: 0.95;
			color: #fff;
		}
		
		/* Order Overview Section - Cards + Buttons */
		.order-summary-wrapper {
			display: flex;
			gap: 25px;
			margin-bottom: 30px;
			align-items: flex-start;
			flex-wrap: wrap;
		}
		
		.order-overview {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 15px;
			flex: 1;
			min-width: 600px;
		}
		
		.overview-card {
			background: white;
			padding: 18px;
			border-radius: 12px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.06);
			border-left: 4px solid #234465;
			transition: all 0.3s ease;
		}
		
		.overview-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 15px rgba(0,0,0,0.1);
		}
		
		.overview-card .label {
			display: block;
			font-size: 10px;
			text-transform: uppercase;
			letter-spacing: 1px;
			color: #95a5a6;
			font-weight: 600;
			margin-bottom: 6px;
		}
		
		.overview-card .value {
			display: block;
			font-size: 16px;
			font-weight: 700;
			color: #234465;
			word-wrap: break-word;
		}
		
		/* Invoice Download Buttons - Right Side */
		.invoice-download {
			display: flex;
			flex-direction: column;
			gap: 12px;
			min-width: 240px;
		}
		
		.invoice-btn {
			display: block;
			text-align: center;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white !important;
			padding: 14px 28px;
			border-radius: 8px;
			text-decoration: none !important;
			font-weight: 600;
			font-size: 15px;
			border: none;
			cursor: pointer;
			transition: all 0.3s ease;
			box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
		}
		
		.invoice-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
			color: white !important;
		}
		
		/* Details Section */
		.details-section {
			background: white;
			border-radius: 12px;
			padding: 30px;
			margin-bottom: 25px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.06);
		}
		
		.details-section h2 {
			font-size: 22px;
			color: #234465;
			margin-bottom: 20px;
			padding-bottom: 15px;
			border-bottom: 3px solid #234465;
		}
		
		/* Order Table */
		.order-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 20px;
		}
		
		.order-table thead {
			background: #234465;
			color: white;
		}
		
		.order-table th {
			padding: 16px;
			text-align: left;
			font-weight: 600;
			font-size: 14px;
		}
		
		.order-table td {
			padding: 16px;
			border-bottom: 1px solid #e8eef2;
		}
		
		.order-table tbody tr:hover {
			background: #f8f9fa;
		}
		
		.order-table .text-right {
			text-align: right;
		}
		
		.product-quantity {
			color: #234465;
			font-weight: 600;
			margin-left: 8px;
		}
		
		.order-table tfoot {
			background: #f8f9fa;
			font-weight: 600;
		}
		
		.order-table tfoot .total-row th,
		.order-table tfoot .total-row td {
			font-size: 20px;
			padding-top: 20px;
			color: #234465;
			border-top: 2px solid #234465;
		}
		
		/* Address Block */
		.address-block {
			line-height: 1.8;
			color: #555;
		}
		
		.address-block strong {
			color: #234465;
		}
		
		/* Custom Footer */
		.softuni-custom-footer {
			background: #234465;
			color: white;
			padding: 25px 0;
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
			gap: 15px;
		}
		
		.footer-left {
			font-size: 14px;
			color: rgba(255, 255, 255, 0.9);
		}
		
		.footer-right {
			display: flex;
			gap: 25px;
		}
		
		.footer-right a {
			color: rgba(255, 255, 255, 0.9);
			text-decoration: none;
			font-size: 14px;
			transition: color 0.3s ease;
		}
		
		.footer-right a:hover {
			color: #00ba96;
		}
		
		/* Responsive */
		@media (max-width: 992px) {
			.order-summary-wrapper {
				flex-direction: column;
			}
			
			.order-overview {
				min-width: 100%;
				grid-template-columns: repeat(2, 1fr);
			}
			
			.invoice-download {
				width: 100%;
				flex-direction: row;
			}
		}
		
		@media (max-width: 768px) {
			.success-banner h1 { font-size: 24px; }
			.order-overview { grid-template-columns: 1fr; }
			.invoice-download { flex-direction: column; }
			.order-table th, .order-table td { padding: 12px 8px; font-size: 14px; }
			.details-section { padding: 20px; }
			
			.footer-content {
				flex-direction: column;
				text-align: center;
			}
			
			.footer-right {
				flex-direction: column;
				gap: 12px;
			}
		}
	</style>
</head>
<body <?php body_class('softuni-thankyou-page'); ?>>

<?php 
// Load default Divi header
get_header(); 
?>

	<!-- Thank You Content -->
	<div class="thankyou-container">

		<?php if ( $order->has_status( 'failed' ) ) : ?>
			
			<!-- Failed Order -->
			<div class="success-banner" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
				<div class="success-icon">
					<svg width="80" height="80" viewBox="0 0 24 24" fill="none">
						<circle cx="12" cy="12" r="10" stroke="white" stroke-width="2"/>
						<path d="M8 8L16 16M16 8L8 16" stroke="white" stroke-width="2" stroke-linecap="round"/>
					</svg>
				</div>
				<h1><?php esc_html_e( 'Nažalost, došlo je do greške', 'divi-child' ); ?></h1>
				<p><?php esc_html_e( 'Vaša porudžbina nije mogla biti obrađena. Molimo pokušajte ponovo.', 'divi-child' ); ?></p>
			</div>
			
			<div class="details-section" style="text-align: center;">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="invoice-btn">
					<?php esc_html_e( 'Pokušaj ponovo', 'divi-child' ); ?>
				</a>
			</div>

		<?php else : ?>

			<!-- Success Banner -->
			<div class="success-banner">
				<div class="success-icon">
					<svg width="80" height="80" viewBox="0 0 24 24" fill="none">
						<circle cx="12" cy="12" r="10" stroke="white" stroke-width="2"/>
						<path d="M8 12L11 15L16 9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			<h1><?php esc_html_e( 'Hvala, Vaša narudžbina je primljena!', 'divi-child' ); ?></h1>
			<p><?php esc_html_e( 'Poslali smo Vam potvrdu na email adresu.', 'divi-child' ); ?></p>
		</div>

		<!-- Order Summary: Cards + Download Buttons -->
		<div class="order-summary-wrapper">
			<!-- Order Overview Cards -->
			<div class="order-overview">
				<div class="overview-card">
					<span class="label"><?php esc_html_e( 'Broj narudžbine', 'divi-child' ); ?></span>
					<span class="value"><?php echo esc_html( $order->get_order_number() ); ?></span>
				</div>
				
				<div class="overview-card">
					<span class="label"><?php esc_html_e( 'Datum', 'divi-child' ); ?></span>
					<span class="value"><?php echo esc_html( $order->get_date_created()->date_i18n( 'd.m.Y' ) ); ?></span>
				</div>
				
				<div class="overview-card">
					<span class="label"><?php esc_html_e( 'Email', 'divi-child' ); ?></span>
					<span class="value" style="font-size: 14px;"><?php echo esc_html( $order->get_billing_email() ); ?></span>
				</div>
				
				<div class="overview-card">
					<span class="label"><?php esc_html_e( 'Ukupno', 'divi-child' ); ?></span>
					<span class="value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
				</div>
				
				<div class="overview-card">
					<span class="label"><?php esc_html_e( 'Način plaćanja', 'divi-child' ); ?></span>
					<span class="value" style="font-size: 14px;"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
				</div>
			</div>

			<!-- Invoice Download Buttons -->
			<?php 
			$pdf_url = $order->get_meta('su_pdf_url');
			$pdf_title = $order->get_meta('su_pdf_title') ?: 'FAKTURA';
			$payment_slip_url = $order->get_meta('su_payment_slip_url');
			?>
			
			<div class="invoice-download">
				<?php if ( $pdf_url ) : ?>
					<a href="<?php echo esc_url( $pdf_url ); ?>" class="invoice-btn" target="_blank">
						📄 <?php echo esc_html( sprintf( __( '%s (PDF)', 'divi-child' ), $pdf_title ) ); ?>
					</a>
				<?php endif; ?>
				
				<?php if ( $payment_slip_url ) : ?>
					<a href="<?php echo esc_url( $payment_slip_url ); ?>" class="invoice-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);" target="_blank">
						💳 <?php esc_html_e( 'Preuzmi Uplatnicu (PDF)', 'divi-child' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>			<!-- Order Details -->
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
						<?php foreach ( $order->get_items() as $item ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $item->get_name() ); ?>
									<span class="product-quantity">&times; <?php echo esc_html( $item->get_quantity() ); ?></span>
								</td>
								<td class="text-right"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
							</tr>
						<?php endforeach; ?>
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
			</div>

		<?php endif; ?>

	</div><!-- .thankyou-container -->

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
