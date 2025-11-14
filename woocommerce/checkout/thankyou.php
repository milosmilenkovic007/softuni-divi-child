<?php
/**
 * Custom Thank You page for SoftUni
 * This template overrides the default WooCommerce thankyou.php
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order softuni-thankyou">

	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Nažalost, Vaša porudžbina nije mogla biti obrađena jer sistem plaćanja nije odgovorio na vreme. Molimo Vas pokušajte ponovo.', 'woocommerce' ); ?></p>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pokušaj ponovo', 'woocommerce' ); ?></a>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'Moj nalog', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</p>

		<?php else : ?>

			<div class="thankyou-success-message">
				<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="12" cy="12" r="10" stroke="#27ae60" stroke-width="2" fill="none"/>
					<path d="M8 12L11 15L16 9" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<h2><?php esc_html_e( 'Hvala, Vaša narudžbina je primljena!', 'divi-child' ); ?></h2>
			</div>

			<div class="order-overview-cards">
				<div class="overview-card">
					<span class="card-label"><?php esc_html_e( 'Broj narudžbine:', 'woocommerce' ); ?></span>
					<span class="card-value"><?php echo esc_html( $order->get_order_number() ); ?></span>
				</div>

				<div class="overview-card">
					<span class="card-label"><?php esc_html_e( 'Datum:', 'woocommerce' ); ?></span>
					<span class="card-value"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span>
				</div>

				<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
					<div class="overview-card">
						<span class="card-label"><?php esc_html_e( 'Email:', 'woocommerce' ); ?></span>
						<span class="card-value"><?php echo esc_html( $order->get_billing_email() ); ?></span>
					</div>
				<?php endif; ?>

				<div class="overview-card">
					<span class="card-label"><?php esc_html_e( 'Ukupno:', 'woocommerce' ); ?></span>
					<span class="card-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
				</div>

				<?php if ( $order->get_payment_method_title() ) : ?>
					<div class="overview-card">
						<span class="card-label"><?php esc_html_e( 'Način plaćanja:', 'woocommerce' ); ?></span>
						<span class="card-value"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
					</div>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

		<!-- Order Details Section -->
		<section class="order-details-section">
			<h3><?php esc_html_e( 'Detalji narudžbine', 'woocommerce' ); ?></h3>
			
			<table class="order-details-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Proizvod', 'woocommerce' ); ?></th>
						<th class="text-right"><?php esc_html_e( 'Ukupno', 'woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $order->get_items() as $item_id => $item ) {
						?>
						<tr>
							<td>
								<?php echo esc_html( $item->get_name() ); ?>
								<strong class="product-quantity">&times;&nbsp;<?php echo esc_html( $item->get_quantity() ); ?></strong>
							</td>
							<td class="text-right"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
				<tfoot>
					<tr class="order-subtotal">
						<th><?php esc_html_e( 'Svega:', 'woocommerce' ); ?></th>
						<td class="text-right"><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td>
					</tr>
					<tr class="order-total">
						<th><?php esc_html_e( 'Ukupno:', 'woocommerce' ); ?></th>
						<td class="text-right"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
					</tr>
					<tr class="payment-method">
						<th><?php esc_html_e( 'Način plaćanja:', 'woocommerce' ); ?></th>
						<td class="text-right"><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
					</tr>
				</tfoot>
			</table>
		</section>

		<!-- Billing Address Section -->
		<section class="billing-details-section">
			<h3><?php esc_html_e( 'Adresa za naplatu', 'woocommerce' ); ?></h3>
			<address>
				<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'woocommerce' ) ) ); ?>
				<?php if ( $order->get_billing_phone() ) : ?>
					<p class="phone"><strong><?php esc_html_e( 'Telefon:', 'woocommerce' ); ?></strong> <?php echo esc_html( $order->get_billing_phone() ); ?></p>
				<?php endif; ?>
				<?php if ( $order->get_billing_email() ) : ?>
					<p class="email"><strong><?php esc_html_e( 'Email:', 'woocommerce' ); ?></strong> <?php echo esc_html( $order->get_billing_email() ); ?></p>
				<?php endif; ?>
			</address>
		</section>

	<?php else : ?>

		<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Hvala. Vaša narudžbina je primljena.', 'woocommerce' ), null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

	<?php endif; ?>

</div>

<style>
	/* Hide default WooCommerce elements */
	.woocommerce-order-overview,
	.woocommerce-order-details,
	.woocommerce-customer-details {
		display: none !important;
	}
	
	/* Container */
	.softuni-thankyou {
		max-width: 900px;
		margin: 40px auto;
		padding: 0 20px;
	}
	
	/* Success Message */
	.thankyou-success-message {
		text-align: center;
		padding: 40px 20px;
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		border-radius: 12px;
		color: white;
		margin-bottom: 30px;
		box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
	}
	
	.thankyou-success-message svg {
		margin-bottom: 20px;
		animation: scaleIn 0.5s ease-out;
	}
	
	@keyframes scaleIn {
		from { transform: scale(0); }
		to { transform: scale(1); }
	}
	
	.thankyou-success-message h2 {
		margin: 0;
		font-size: 28px;
		font-weight: 600;
		color: white;
	}
	
	/* Overview Cards */
	.order-overview-cards {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 20px;
		margin-bottom: 40px;
	}
	
	.overview-card {
		background: white;
		padding: 20px;
		border-radius: 10px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.08);
		border-left: 4px solid #667eea;
		transition: transform 0.2s, box-shadow 0.2s;
	}
	
	.overview-card:hover {
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(0,0,0,0.12);
	}
	
	.overview-card .card-label {
		display: block;
		font-size: 12px;
		color: #888;
		text-transform: uppercase;
		font-weight: 600;
		letter-spacing: 0.5px;
		margin-bottom: 5px;
	}
	
	.overview-card .card-value {
		display: block;
		font-size: 18px;
		color: #2c3e50;
		font-weight: 600;
	}
	
	/* Sections */
	.order-details-section,
	.billing-details-section {
		background: white;
		padding: 30px;
		border-radius: 10px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.08);
		margin-bottom: 30px;
	}
	
	.order-details-section h3,
	.billing-details-section h3 {
		margin: 0 0 20px 0;
		font-size: 22px;
		color: #2c3e50;
		padding-bottom: 15px;
		border-bottom: 3px solid #667eea;
	}
	
	/* Order Table */
	.order-details-table {
		width: 100%;
		border-collapse: collapse;
	}
	
	.order-details-table thead {
		background: #2c3e50;
		color: white;
	}
	
	.order-details-table th,
	.order-details-table td {
		padding: 15px;
		text-align: left;
		border-bottom: 1px solid #e1e8ed;
	}
	
	.order-details-table th {
		font-weight: 600;
		font-size: 14px;
	}
	
	.order-details-table tbody tr:hover {
		background: #f8f9fa;
	}
	
	.order-details-table .text-right {
		text-align: right;
	}
	
	.order-details-table .product-quantity {
		color: #667eea;
		margin-left: 5px;
	}
	
	.order-details-table tfoot {
		background: #f8f9fa;
		font-weight: 600;
	}
	
	.order-details-table tfoot .order-total th,
	.order-details-table tfoot .order-total td {
		font-size: 18px;
		color: #2c3e50;
		padding-top: 20px;
		border-top: 2px solid #667eea;
	}
	
	/* Address */
	.billing-details-section address {
		line-height: 1.8;
		font-style: normal;
		color: #555;
	}
	
	.billing-details-section address p {
		margin: 5px 0;
	}
	
	/* Responsive */
	@media (max-width: 768px) {
		.softuni-thankyou {
			margin: 20px auto;
		}
		
		.thankyou-success-message h2 {
			font-size: 22px;
		}
		
		.order-overview-cards {
			grid-template-columns: 1fr;
		}
		
		.order-details-table th,
		.order-details-table td {
			padding: 10px;
			font-size: 14px;
		}
	}
</style>
