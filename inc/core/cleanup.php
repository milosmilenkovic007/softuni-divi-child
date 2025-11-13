<?php
if (!defined('ABSPATH')) { exit; }

// Emojis
add_action('init', function () {
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
});

// Embeds
add_action('wp_enqueue_scripts', function () {
	wp_deregister_script('wp-embed');
}, 100);

// SVG upload
add_filter('upload_mimes', function ($m) { $m['svg'] = 'image/svg+xml'; return $m; });
