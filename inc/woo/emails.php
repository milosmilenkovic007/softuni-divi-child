<?php
if (!defined('ABSPATH')) { exit; }

/*
 * Mesto za buduće email prilagodbe (proforma logika, dodatni header/footer, itd.)
 * Trenutno je sve no-op, ne remeti postojeće mejlove.
 */

// Primer no-op filtera koji samo prosleđuje vrednost
add_filter('woocommerce_email_enabled_customer_invoice', function ($enabled, $order) {
	return $enabled;
}, 1, 2);

// Dodatan hook prostor za kasnije
add_action('init', function () {
	// ready for future email setup
}, 1);
