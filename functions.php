<?php
/**
 * Divi Child – Modular Functions Loader
 * -------------------------------------
 * Učitava jasno definisane module iz /inc foldera.
 */

if (!defined('ABSPATH')) { exit; }

/* -----------------------------------------------------
 * Definicije putanja
 * ----------------------------------------------------- */
define('DIVICHILD_PATH', get_stylesheet_directory());
define('DIVICHILD_URL',  get_stylesheet_directory_uri());

/* -----------------------------------------------------
 * CORE moduli
 * ----------------------------------------------------- */

// Osnovni setup i podrške
require_once DIVICHILD_PATH . '/inc/core/setup.php';

// Enqueue CSS/JS
require_once DIVICHILD_PATH . '/inc/core/enqueue.php';

// Cleanup i optimizacija (emoji, embeds, svg)
require_once DIVICHILD_PATH . '/inc/core/cleanup.php';

/* -----------------------------------------------------
 * ADMIN
 * ----------------------------------------------------- */

// Uklanjanje Divi Machine obaveštenja i drugih notifikacija
require_once DIVICHILD_PATH . '/inc/admin/notices.php';

/* -----------------------------------------------------
 * SHORTCODES
 * ----------------------------------------------------- */

// Shortcode [acf field="..."]
require_once DIVICHILD_PATH . '/inc/shortcodes/acf-field.php';

/* WooCommerce customizations removed per request. The child theme no longer overrides or hooks into Woo checkout. */

/* -----------------------------------------------------
 * WooCommerce – Order Meta (minimal)
 * ----------------------------------------------------- */
// Save and display custom company fields on orders (does not alter checkout UI)
if ( class_exists('WooCommerce') ) {
	require_once DIVICHILD_PATH . '/inc/woocommerce/order-meta.php';
	require_once DIVICHILD_PATH . '/inc/woocommerce/routing.php';
	// Email customizations (participants block, company meta in emails)
	require_once DIVICHILD_PATH . '/inc/woo/emails.php';
	// Redirect cart to checkout
	require_once DIVICHILD_PATH . '/inc/woo/redirect-cart-to-checkout.php';
	// PDF invoice/proforma generator (thank-you page button)
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-pdf.php';
	// Admin-side PDF generator button/meta box on order edit
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-admin.php';
	// AJAX handler to save generated PDF into /Fakture and attach to order
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-save.php';
	// Sequential invoice numbering + admin listing
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-number.php';
	// Server-side PDF generator
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-server-generator.php';
	// Auto-generate PDF when order status changes to completed
	require_once DIVICHILD_PATH . '/inc/woocommerce/invoice-auto-generate.php';
	// Generate payment slip (uplatnica) for BACS orders
	require_once DIVICHILD_PATH . '/inc/woocommerce/payment-slip-generator.php';
	// Customize thank you page (hide payment icons, clean design)
	require_once DIVICHILD_PATH . '/inc/woocommerce/thankyou-customization.php';
	// Participant-based pricing for companies
	require_once DIVICHILD_PATH . '/inc/woocommerce/participant-pricing.php';
}
