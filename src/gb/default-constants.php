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
if( count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



// Запоминаем родительский каталог, как основу для всех подключаемых файлов системы
if( !defined('BASE_DIR'))
	define('BASE_DIR',	dirname(dirname(__FILE__)));	// no trailing slash, full paths only

/**
 * Defines initial GeniBase constants
 *
 * @see gb_debug_mode()
 *
 * @since 2.0.0
 */
function gb_initial_constants() {
	// set memory limits
	if( !defined('GB_MEMORY_LIMIT') )
		define('GB_MEMORY_LIMIT', '40M');
	if( !defined('GB_MAX_MEMORY_LIMIT') )
		define('GB_MAX_MEMORY_LIMIT', '256M');
	//
	if( function_exists( 'memory_get_usage') ){
		$current_limit = @ini_get( 'memory_limit' );
		$current_limit_int = intval( $current_limit );
		if( false !== strpos( $current_limit, 'G' ) )
			$current_limit_int *= 1024;
		$gb_limit_int = intval( GB_MEMORY_LIMIT );
		if( false !== strpos( GB_MEMORY_LIMIT, 'G' ) )
			$gb_limit_int *= 1024;

		if( -1 != $current_limit && ( -1 == GB_MEMORY_LIMIT || $current_limit_int < $gb_limit_int ) )
			@ini_set( 'memory_limit', GB_MEMORY_LIMIT );
	}

	/**
	 * Allows for the core directory to be moved from the default location.
	 * 
	 * GB_CORE_URL is defined further down
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_CORE_DIR') )
		define('GB_CORE_DIR',	BASE_DIR . '/gb');

	/**
	 * Allows for the core languages directory to be moved from the default location.
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_LANG_DIR') )
		define('GB_LANG_DIR',	GB_CORE_DIR . '/languages');

	/**
	 * Allows for the content directory to be moved from the default location.
	 * 
	 * GB_CONTENT_URL is defined further down
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_CONTENT_DIR') )
		define('GB_CONTENT_DIR', BASE_DIR . '/gb-content'); // no trailing slash, full paths only

	/**
	 * Allows for the cache directory to be moved from the default location.
	 * 
	 * GB_CONTENT_CACHE_URL is defined further down
	 *
	 * @since 2.1.1
	 */
	if( !defined('GB_CONTENT_CACHE_DIR') )
		define('GB_CONTENT_CACHE_DIR', GB_CONTENT_DIR . '/cache'); // no trailing slash, full paths only

	/**
	 * By default switch off debug mode.
	 * 
	 * Add define('GB_DEBUG', true); to gb-config.php to enable display of notices during development.
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_DEBUG') )
		define('GB_DEBUG', false);

	/**
	 * By default display debug messages on page.
	 *
	 * Add define('GB_DEBUG_DISPLAY', null); to gb-config.php use the globally configured
	 * setting for display_errors and not force errors to be displayed. Use false to force
	 * display_errors off.
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_DEBUG_DISPLAY') )
		define( 'GB_DEBUG_DISPLAY', true );

	/**
	 * By default switch off debug logging.
	 *
	 * Add define('GB_DEBUG_LOG', true); to enable error logging to gb-content/debug.log.
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_DEBUG_LOG') )
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
	// Запоминаем текущий каталог, как корень сайта
	if( !defined('BASE_URL') )
		// TODO: options
		define('BASE_URL', '//' . $_SERVER['HTTP_HOST'] . substr(BASE_DIR, strlen($_SERVER[ 'DOCUMENT_ROOT' ])));	// no trailing slash
// 		define( 'BASE_URL', get_option('siteurl')); // full url
	
	/**
	 * Allows for the core directory to be moved from the default location.
	 * 
	 * GB_CORE_DIR is defined further up
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_CORE_URL') )
		define( 'GB_CORE_URL', BASE_URL . '/gb');
	
	/**
	 * Allows for the content directory to be moved from the default location.
	 * 
	 * GB_CONTENT_DIR is defined further up
	 *
	 * @since 2.0.0
	 */
	if( !defined('GB_CONTENT_URL') )
		define( 'GB_CONTENT_URL', BASE_URL . '/gb-content');

	/**
	 * Allows for the cache directory to be moved from the default location.
	 * 
	 * GB_CONTENT_CACHE_DIR is defined further up
	 *
	 * @since 2.1.1
	 */
	if( !defined('GB_CONTENT_CACHE_URL') )
		define( 'GB_CONTENT_CACHE_URL', GB_CONTENT_URL . '/cache'); // full url

	/**
	 * Allows for the plugins directory to be moved from the default location.
	 *
	 * @since 2.0.0
	 */
	// TODO: plugins
// 	if( !defined('GB_PLUGIN_DIR') )
// 		define('GB_PLUGIN_DIR', GB_CONTENT_DIR . '/plugins'); // full path, no trailing slash

	/**
	 * Allows for the plugins directory to be moved from the default location.
	 *
	 * @since 2.0.0
	 */
	// TODO: plugins
// 	if( !defined('GB_PLUGIN_URL') )
// 		define('GB_PLUGIN_URL', GB_CONTENT_URL . '/plugins'); // full url, no trailing slash
}

/**
 * Defines cookie related GeniBase constants
 * 
 * @since 2.1.1
 */
function gb_cookie_constants() {
	/**
	 * Used to guarantee unique hash cookies
	 *
	 * @since 2.1.1
	 */
// 	if( !defined('GB_COOKIE_HASH') ){
// 		$siteurl = BASE_URL;	// TODO: options
// // 		$siteurl = get_site_option( 'siteurl' );
// 		if( $siteurl )
// 			define('GB_COOKIE_HASH', '_' . md5($siteurl));
// 		else
			define('GB_COOKIE_HASH', '');
// 	}

	/**
	 * @since 2.1.1
	 */
	if( !defined('GB_COOKIE_USERID') )
		define('GB_COOKIE_USERID', 'gb_uid' . GB_COOKIE_HASH);

	/**
	 * @since 2.1.1
	 */
	// TODO: users
// 	if( !defined('USER_COOKIE') )
// 		define('USER_COOKIE', 'wordpressuser_' . GB_COOKIE_HASH);

	/**
	 * @since 2.0.0
	 */
	// TODO: users
// 	if ( !defined('PASS_COOKIE') )
// 		define('PASS_COOKIE', 'wordpresspass_' . GB_COOKIE_HASH);

	/**
	 * @since 2.5.0
	 */
	// TODO: users
// 	if ( !defined('AUTH_COOKIE') )
// 		define('AUTH_COOKIE', 'wordpress_' . GB_COOKIE_HASH);

	/**
	 * @since 2.6.0
	 */
	// TODO: users
// 	if ( !defined('SECURE_AUTH_COOKIE') )
// 		define('SECURE_AUTH_COOKIE', 'wordpress_sec_' . GB_COOKIE_HASH);

	/**
	 * @since 2.6.0
	 */
	// TODO: users
// 	if ( !defined('LOGGED_IN_COOKIE') )
// 		define('LOGGED_IN_COOKIE', 'wordpress_logged_in_' . GB_COOKIE_HASH);

	/**
	 * @since 2.1.1
	 */
	if( !defined('GB_COOKIE_PATH') )
		define('GB_COOKIE_PATH', preg_replace('|^https?://[^/]+|i', '', site_url('/') ) );	// TODO: options
// 		define('GB_COOKIE_PATH', preg_replace('|^https?://[^/]+|i', '', get_option('home') . '/' ) );

	/**
	 * @since 2.6.0
	*/
	// TODO: admin
// 	if ( !defined('ADMIN_COOKIE_PATH') )
// 		define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin' );

	/**
	 * @since 2.6.0
	*/
	// TODO: plugins
// 	if ( !defined('PLUGINS_COOKIE_PATH') )
// 		define( 'PLUGINS_COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', WP_PLUGIN_URL)  );

	/**
	 * @since 2.1.1
	*/
	if( !defined('GB_COOKIE_DOMAIN') )
		define('GB_COOKIE_DOMAIN', false);
}
