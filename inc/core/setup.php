<?php
if (!defined('ABSPATH')) { exit; }

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
