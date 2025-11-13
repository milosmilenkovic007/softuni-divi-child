<?php
if (!defined('ABSPATH')) { exit; }
// Ensure Fakture directory exists early (for PDF storage)
add_action('after_setup_theme', function(){
	$folder = trailingslashit( get_stylesheet_directory() ) . 'Fakture';
	if ( ! is_dir( $folder ) ) { wp_mkdir_p( $folder ); }
});

add_action('after_setup_theme', function () {
	load_child_theme_textdomain('divi-child', get_stylesheet_directory() . '/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','script','style']);
	add_theme_support('woocommerce');

	// meniji po potrebi
	register_nav_menus([
		'primary'   => 'Primary Menu',
		'secondary' => 'Secondary Menu',
	]);
});
