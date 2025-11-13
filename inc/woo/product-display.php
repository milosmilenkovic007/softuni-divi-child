<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Woo – onemogućava zoom, lightbox i slider na proizvodima
 * (zamena za Code Snippets: "No zoom WooCommerce product")
 */

// Isključi Divi product zoom/lightbox/slider funkcije
add_filter('ddpz_disable_lightbox', '__return_true');
add_filter('ddpz_disable_slider', '__return_true');
