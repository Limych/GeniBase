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
// TODO: actions
// GB_Hooks::add_action('gb_head', '_gb_render_title_tag', 1 );
GB_Hooks::add_action('gb_head', 'gb_enqueue_scripts', 1);
// GB_Hooks::add_action('gb_head', 'locale_stylesheet' );
GB_Hooks::add_action('gb_head', 'gb_print_styles', 8);
GB_Hooks::add_action('gb_head', 'gb_print_head_scripts', 9);
GB_Hooks::add_action('gb_head', 'rel_canonical');
GB_Hooks::add_action('gb_footer', 'gb_print_footer_scripts', 20);
GB_Hooks::add_action('gb_print_footer_scripts', '_gb_footer_scripts');
// GB_Hooks::add_action('shutdown', 'gb_ob_end_flush_all', 1 );

// GB Cron
// if ( !defined( 'DOING_CRON' ) )
// GB_Hooks::add_action( 'init', 'gb_cron' );

// Default authentication filters
GB_Hooks::add_filter('authenticate', 'gb_authenticate_email_password', 20, 3);
GB_Hooks::add_filter('determine_current_user', 'gb_validate_auth_cookie');
GB_Hooks::add_filter('determine_current_user', 'gb_validate_logged_in_cookie', 20);
