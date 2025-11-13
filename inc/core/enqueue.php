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
