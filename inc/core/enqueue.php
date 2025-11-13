<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Child theme assets loader
 * - Always enqueue child style.css
 * - Optionally enqueue assets/js/main.js if present
 * - Conditionally enqueue checkout assets only on Template: Checkout (Custom)
 */
add_action('wp_enqueue_scripts', function () {
	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();

	$file_ver = function(string $rel) use ($theme_dir): string {
		$path = $theme_dir . $rel;
		return file_exists($path) ? (string) filemtime($path) : wp_get_theme()->get('Version');
	};

	// 1) Base child stylesheet (after Divi parent when available)
	$parent_handle = 'divi-style';
	$child_rel = '/style.css';
	$child_deps = (wp_style_is($parent_handle, 'registered') || wp_style_is($parent_handle, 'enqueued')) ? [$parent_handle] : [];
	wp_enqueue_style('divi-child', $theme_uri . $child_rel, $child_deps, $file_ver($child_rel));

	// 2) Optional site-wide main.js
	$main_rel = '/assets/js/main.js';
	if ( file_exists($theme_dir . $main_rel) ) {
		wp_enqueue_script('divi-child-main', $theme_uri . $main_rel, ['jquery'], $file_ver($main_rel), true);
	}

	// 3) Custom Checkout template assets only on that template
	if ( function_exists('is_page_template') && is_page_template('page-templates/checkout-custom.php') ) {
		$ccss_rel = '/assets/css/checkout-custom.css';
		if ( file_exists($theme_dir . $ccss_rel) ) {
			wp_enqueue_style('divi-child-checkout-custom', $theme_uri . $ccss_rel, ['divi-child'], $file_ver($ccss_rel));
		}

		$cjs_rel = '/assets/js/checkout-custom.js';
		if ( file_exists($theme_dir . $cjs_rel) ) {
			wp_enqueue_script('divi-child-checkout-custom', $theme_uri . $cjs_rel, ['jquery'], $file_ver($cjs_rel), true);
		}
	}

	// Note: No WooCommerce-specific assets are enqueued from the child theme
}, 20);

// Conditionally enqueue PDF libs and generator on Woo thank-you page
add_action('wp_enqueue_scripts', function(){
	if ( function_exists('is_order_received_page') && is_order_received_page() ) {
		$theme_dir = get_stylesheet_directory();
		$theme_uri = get_stylesheet_directory_uri();

		// Prefer self-hosted vendors; fallback to CDN if missing locally
		$vendor_jspdf_rel = '/assets/js/vendors/jspdf.umd.min.js';
		$vendor_autotable_rel = '/assets/js/vendors/jspdf.plugin.autotable.min.js';
		if ( file_exists( $theme_dir . $vendor_jspdf_rel ) ) {
			wp_enqueue_script('jspdf', $theme_uri . $vendor_jspdf_rel, [], filemtime($theme_dir . $vendor_jspdf_rel), true);
		} else {
			wp_enqueue_script('jspdf', 'https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js', [], '2.5.2', true);
		}
		if ( file_exists( $theme_dir . $vendor_autotable_rel ) ) {
			wp_enqueue_script('jspdf-autotable', $theme_uri . $vendor_autotable_rel, ['jspdf'], filemtime($theme_dir . $vendor_autotable_rel), true);
		} else {
			wp_enqueue_script('jspdf-autotable', 'https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js', ['jspdf'], '3.8.2', true);
		}

		$rel = '/assets/js/pdf-invoice.js';
		if ( file_exists( $theme_dir . $rel ) ) {
			wp_enqueue_script('divi-child-pdf-invoice', $theme_uri . $rel, ['jquery','jspdf','jspdf-autotable'], filemtime($theme_dir . $rel), true);
			// Provide ajax URL for saving PDFs
			wp_add_inline_script('divi-child-pdf-invoice', 'window.SU_INVOICE_AJAX_URL = '. wp_json_encode( admin_url('admin-ajax.php') ) .';', 'before');
		}
	}
}, 25);

// Admin: enqueue PDF vendors on order edit screens
add_action('admin_enqueue_scripts', function( $hook ){
	// Detect Woo order edit screens (classic and HPOS wc-orders)
	$is_wc_order_screen = false;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ( $screen ) {
		if ( $screen->id === 'shop_order' || $screen->id === 'edit-shop_order' ) { $is_wc_order_screen = true; }
		if ( strpos( (string) $screen->id, 'wc-orders' ) !== false ) { $is_wc_order_screen = true; }
	}
	if ( isset($_GET['page']) && $_GET['page'] === 'wc-orders' ) { $is_wc_order_screen = true; }

	if ( ! $is_wc_order_screen ) { return; }

	$theme_dir = get_stylesheet_directory();
	$theme_uri = get_stylesheet_directory_uri();
	$vendor_jspdf_rel = '/assets/js/vendors/jspdf.umd.min.js';
	$vendor_autotable_rel = '/assets/js/vendors/jspdf.plugin.autotable.min.js';
	if ( file_exists( $theme_dir . $vendor_jspdf_rel ) ) {
		wp_enqueue_script('jspdf', $theme_uri . $vendor_jspdf_rel, [], filemtime($theme_dir . $vendor_jspdf_rel), true);
	}
	if ( file_exists( $theme_dir . $vendor_autotable_rel ) ) {
		wp_enqueue_script('jspdf-autotable', $theme_uri . $vendor_autotable_rel, ['jspdf'], filemtime($theme_dir . $vendor_autotable_rel), true);
	}
	$rel = '/assets/js/pdf-invoice.js';
	if ( file_exists( $theme_dir . $rel ) ) {
		wp_enqueue_script('divi-child-pdf-invoice', $theme_uri . $rel, ['jquery','jspdf','jspdf-autotable'], filemtime($theme_dir . $rel), true);
		$inline = [
			'SU_INVOICE_AJAX_URL' => admin_url('admin-ajax.php'),
			'SU_THEME_URL'        => $theme_uri,
			'SU_FONTS_PATH'       => $theme_uri . '/assets/fonts'
		];
		wp_add_inline_script('divi-child-pdf-invoice', 'window.SU_PDF_CTX = '. wp_json_encode( $inline ) .';', 'before');
	}
}, 20);
