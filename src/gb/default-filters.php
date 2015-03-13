<?php
/**
 * Sets up the default filters and actions for most
 * of the GeniBase hooks.
 *
 * If you need to remove a default hook, this file will
 * give you the priority for which to use to remove the
 * hook.
 *
 * Not all of the default hooks are found in default-filters.php
 *
 * @package GeniBase
 * @since	2.1.0
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Actions
// add_action('gb_head',				'_gb_render_title_tag',		 1	);
add_action('gb_head',				'gb_enqueue_scripts',		 1	);
// add_action('gb_head',				'locale_stylesheet'				);
add_action('gb_head',				'gb_print_styles',			 8	);
add_action('gb_head',				'gb_print_head_scripts',	 9	);
add_action('gb_head',				'rel_canonical'					);
add_action('gb_footer',				'gb_print_footer_scripts',	20	);
add_action('gb_print_footer_scripts',	'_gb_footer_scripts'		);
// add_action('shutdown',				'gb_ob_end_flush_all',		 1	);
