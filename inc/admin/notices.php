<?php
if (!defined('ABSPATH')) { exit; }

/*
 * Uklanja "Divi Machine is inactive..." admin obaveštenje.
 * Radi uklanjanjem callback-a i CSS fallback-om.
 */
add_action('admin_init', function () {
	global $wp_filter;

	foreach (['admin_notices','all_admin_notices'] as $hook) {
		if (empty($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) continue;

		foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
			foreach ($callbacks as $id => $data) {
				$fn = $data['function'];

				// Klase plugin-a
				if (is_array($fn) && is_object($fn[0])) {
					$class = get_class($fn[0]);
					if (stripos($class, 'divi') !== false && stripos($class, 'machine') !== false) {
						unset($wp_filter[$hook]->callbacks[$priority][$id]);
					}
				}

				// Proceduralne funkcije
				if (is_string($fn) && (stripos($fn, 'divi') !== false && stripos($fn, 'machine') !== false)) {
					unset($wp_filter[$hook]->callbacks[$priority][$id]);
				}
			}
		}
	}
});

add_action('admin_head', function () {
	echo '<style>
		.notice.notice-error:has(a[href*="divi-machine"]),
		.notice.notice-error:has(a[href*="divimachine"]),
		.notice:has(.divi-machine),
		.notice:has(.et_mct_notices),
		.notice:has([href*="divi-machine"]),
		.notice:has([href*="divimachine"]) {
			display:none!important;
		}
	</style>';
});
