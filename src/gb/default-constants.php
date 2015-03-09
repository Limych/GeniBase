<?php
/**
 * Defines constants and global variables that can be overridden, generally in gb-config.php.
 *
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if(!defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Defines initial GeniBase constants
 *
 * @see gb_debug_mode()
 *
 * @since 2.0.0
 */
function gb_initial_constants() {
	// set memory limits
	if(!defined('GB_MEMORY_LIMIT'))
		define('GB_MEMORY_LIMIT', '40M');
	if(!defined('GB_MAX_MEMORY_LIMIT'))
		define('GB_MAX_MEMORY_LIMIT', '256M');
	//
	if(function_exists( 'memory_get_usage')){
		$current_limit = @ini_get( 'memory_limit' );
		$current_limit_int = intval( $current_limit );
		if ( false !== strpos( $current_limit, 'G' ) )
			$current_limit_int *= 1024;
		$gb_limit_int = intval( GB_MEMORY_LIMIT );
		if ( false !== strpos( GB_MEMORY_LIMIT, 'G' ) )
			$gb_limit_int *= 1024;

		if ( -1 != $current_limit && ( -1 == GB_MEMORY_LIMIT || $current_limit_int < $gb_limit_int ) )
			@ini_set( 'memory_limit', GB_MEMORY_LIMIT );
	}

	if(!defined('GB_CONTENT_DIR'))
		define('GB_CONTENT_DIR', BASE_DIR . '/gb-content'); // no trailing slash, full paths only - GB_CONTENT_URL is defined further down

	// Add define('GB_DEBUG', true); to gb-config.php to enable display of notices during development.
	if ( !defined('GB_DEBUG') )
		define( 'GB_DEBUG', false );

	// Add define('GB_DEBUG_DISPLAY', null); to gb-config.php use the globally configured setting for
	// display_errors and not force errors to be displayed. Use false to force display_errors off.
	if ( !defined('GB_DEBUG_DISPLAY') )
		define( 'GB_DEBUG_DISPLAY', true );

	// Add define('GB_DEBUG_LOG', true); to enable error logging to ./debug.log.
	if ( !defined('GB_DEBUG_LOG') )
		define('GB_DEBUG_LOG', false);

	// Constants for expressing human-readable intervals
	// in their respective number of seconds.
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS',   60 * MINUTE_IN_SECONDS );
	define( 'DAY_IN_SECONDS',    24 * HOUR_IN_SECONDS   );
	define( 'WEEK_IN_SECONDS',    7 * DAY_IN_SECONDS    );
	define( 'YEAR_IN_SECONDS',  365 * DAY_IN_SECONDS    );
}

/**
 * Defines plugin directory GeniBase constants
 *
 * @since 2.0.0
 */
function gb_plugin_constants() {
	if(!defined('GB_CONTENT_URL'))
		define( 'GB_CONTENT_URL', BASE_URL . '/gb-content'); // full url - GB_CONTENT_DIR is defined further up
// 		define( 'GB_CONTENT_URL', get_option('siteurl') . '/gb-content'); // full url - GB_CONTENT_DIR is defined further up

	/**
	 * Allows for the plugins directory to be moved from the default location.
	 *
	 * @since 2.0.0
	 */
// 	if(!defined('GB_PLUGIN_DIR'))
// 		define('GB_PLUGIN_DIR', GB_CONTENT_DIR . '/plugins'); // full path, no trailing slash

	/**
	 * Allows for the plugins directory to be moved from the default location.
	 *
	 * @since 2.0.0
	 */
// 	if(!defined('GB_PLUGIN_URL'))
// 		define('GB_PLUGIN_URL', GB_CONTENT_URL . '/plugins'); // full url, no trailing slash
}
