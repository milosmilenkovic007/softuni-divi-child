<?php
if (!defined('ABSPATH')) { exit; }

function divichild_acf_field_shortcode($atts) {
	$atts = shortcode_atts([
		'field'   => '',
		'post_id' => false,
	], $atts, 'acf');

	if (!function_exists('get_field') || !$atts['field']) {
		return '';
	}

	$value = get_field($atts['field'], $atts['post_id']);

	// Image (ID/array) → vrati URL
	if (is_array($value) && isset($value['url'])) {
		return esc_url($value['url']);
	}

	if (is_array($value)) {
		$out = [];
		foreach ($value as $item) {
			if (is_numeric($item)) {
				$post = get_post((int)$item);
				if ($post) { $out[] = get_the_title($post->ID); continue; }
				$term = get_term((int)$item);
				if (!is_wp_error($term) && $term) { $out[] = $term->name; continue; }
			}
			if (is_array($item) && isset($item['label'])) { $out[] = $item['label']; continue; }
			$out[] = is_scalar($item) ? (string)$item : '';
		}
		return esc_html(implode(', ', array_filter($out)));
	}

	if (is_numeric($value)) {
		$post = get_post((int)$value);
		if ($post)  return esc_html(get_the_title($post->ID));
		$term = get_term((int)$value);
		if (!is_wp_error($term) && $term) return esc_html($term->name);
	}

	return is_scalar($value) ? esc_html((string)$value) : '';
}
add_shortcode('acf', 'divichild_acf_field_shortcode');
