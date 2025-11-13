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
}
