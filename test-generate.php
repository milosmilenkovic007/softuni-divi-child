<?php
// Test invoice and payment slip generation
require_once('../../../wp-load.php');

if (!isset($_GET['order_id'])) {
	die('Add ?order_id=322048 to URL');
}

$order_id = intval($_GET['order_id']);
$order = wc_get_order($order_id);

if (!$order) {
	die('Order not found');
}

echo "<h1>Order #{$order_id} - Test Generation</h1>";
echo "<p>Status: " . $order->get_status() . "</p>";
echo "<p>Payment: " . $order->get_payment_method() . "</p>";
echo "<p>Customer Type: " . $order->get_meta('customer_type') . "</p>";
echo "<p>Company: " . $order->get_billing_company() . "</p>";

echo "<hr><h2>Generating Invoice...</h2>";
require_once(get_stylesheet_directory() . '/inc/woocommerce/invoice-server-generator.php');
$pdf_result = su_generate_invoice_pdf_server_side($order_id);

if ($pdf_result) {
	echo "<p style='color:green'>✓ Invoice generated: " . $order->get_meta('su_pdf_url') . "</p>";
	echo "<p>Title: " . $order->get_meta('su_pdf_title') . "</p>";
} else {
	echo "<p style='color:red'>✗ Invoice generation failed</p>";
}

echo "<hr><h2>Generating Payment Slip...</h2>";
require_once(get_stylesheet_directory() . '/inc/woocommerce/payment-slip-generator.php');
$slip_result = su_generate_payment_slip_pdf($order_id);

if ($slip_result) {
	echo "<p style='color:green'>✓ Payment slip generated: " . $order->get_meta('su_payment_slip_url') . "</p>";
} else {
	echo "<p style='color:red'>✗ Payment slip generation failed</p>";
}

echo "<hr>";
echo "<a href='" . $order->get_checkout_order_received_url() . "'>View Thank You Page</a>";
