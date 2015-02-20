<?php
/**
 * These functions are needed to load GeniBase.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © GeniBase
 */

// Direct execution forbidden for this script
if(!defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Don't load all of system when handling a favicon.ico request.
 *
 * Instead, send the headers for a zero-length favicon and bail.
 *
 * @since 1.1.0
 */
function gb_favicon_request(){
	if ( '/favicon.ico' == $_SERVER['REQUEST_URI'] ) {
		header('Content-Type: image/vnd.microsoft.icon');
		header('Content-Length: 0');
		exit;
	}
}

/**
 * Attempt an early load of translations.
 *
 * Used for errors encountered during the initial loading process, before
 * the locale has been properly detected and loaded.
 *
 * Designed for unusual load sequences (like setup-config.php) or for when
 * the script will then terminate with an error, otherwise there is a risk
 * that a file can be double-included.
 *
 * @since 1.1.0
 * @access private
 *
 * @global $gb_locale The GeniBase date and time locale object.
 */
function gb_load_translations_early() {
	global $text_direction, $gb_locale;

	static $loaded = false;
	if ( $loaded )
		return;
	$loaded = true;

// 	if ( function_exists( 'did_action' ) && did_action( 'init' ) )
// 		return;

	// Translation and localization
	require_once(GB_INC_DIR . '/pomo/mo.php');
	require_once(GB_INC_DIR . '/l10n.php');
	require_once(GB_INC_DIR . '/locale.php');

	$locales = $locations = array();
	while ( true ) {
		if(defined('GB_LANG')){
			if('' == GB_LANG)
				break;
			$locales[] = GB_LANG;
		}

		if(defined('GB_LOCAL_PACKAGE') && '' != GB_LOCAL_PACKAGE)
			$locales[] = GB_LOCAL_PACKAGE;

		if(!$locales)
			break;

		if ( defined( 'GB_LANG_DIR' ) && @is_dir( GB_LANG_DIR ) )
			$locations[] = GB_LANG_DIR;

// 		if ( defined( 'GB_CONTENT_DIR' ) && @is_dir( GB_CONTENT_DIR . '/languages' ) )
// 			$locations[] = GB_CONTENT_DIR . '/languages';

// 		if ( @is_dir( ABSPATH . 'wp-content/languages' ) )
// 			$locations[] = ABSPATH . 'wp-content/languages';

		if ( @is_dir( GB_INC_DIR . '/languages' ) )
			$locations[] = GB_INC_DIR . '/languages';

		if ( ! $locations )
			break;

		$locations = array_unique( $locations );

		foreach ( $locales as $locale ) {
			foreach ( $locations as $location ) {
				if ( file_exists( $location . '/' . $locale . '.mo' ) ) {
					load_textdomain( 'default', $location . '/' . $locale . '.mo' );
// 					if ( defined( 'GB_SETUP_CONFIG' ) && file_exists( $location . '/admin-' . $locale . '.mo' ) )
// 						load_textdomain( 'default', $location . '/admin-' . $locale . '.mo' );
					break 3;
				}
			}
		}

		break;
	}

	$gb_locale = new GB_Locale();
}

/**
 * Die with a maintenance message when conditions are met.
 *
 * Checks for a file in the GeniBase root directory named ".maintenance".
 * If the file was created less than 10 minutes ago, GeniBase enters maintenance mode
 * and displays a message.
 *
 * The default message can be replaced by using a drop-in (maintenance_stub.php in
 * the GeniBase root directory).
 *
 * @since 1.1.0
 * @access private
 */
function gb_maintenance() {
	if ( !file_exists( BASE_DIR . '/.maintenance' ) || defined( 'GB_INSTALLING' ) )
		return;

	// If the upgrading timestamp is older than 10 minutes, don't die.
	$diff = time() - filemtime(BASE_DIR . '/.maintenance');
	if($diff < 0 || $diff >= 600){
		@unlink(BASE_DIR . '/.maintenance');
		return;
	}

	if(file_exists(BASE_DIR . '/maintenance_stub.php')){
		require_once(BASE_DIR . '/maintenance_stub.php');
		die();
	}

	gb_load_translations_early();

	$protocol = $_SERVER["SERVER_PROTOCOL"];
	if('HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol)
		$protocol = 'HTTP/1.0';
	$lang = str_replace('_', '-', get_locale());
	$rtl = (function_exists('is_rtl') && is_rtl()) ? ' dir="rtl"' : '';
	header("$protocol 503 Service Unavailable", true, 503);
	header('Content-Type: text/html; charset=utf-8');
	header('Retry-After: 600');
	?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"<?php print " lang='$lang' xml:lang='$lang'$rtl"; ?>><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php _e( 'Maintenance' ); ?></title>
	<style type="text/css">
		body	{
			font-family: sans-serif;
			background: #DDD;
			text-align: center;
		}
		div	{
			margin: 5em 3em;
		}
	</style>
</head><body>
	<div>
		<h1><?php _e( 'Maintenance' ); ?></h1>
		<p><?php _e( 'This website is currently under going maintenance and will be back online shortly. Thank you for your patience.' ); ?></p>
	</div>
<?php if(substr($lang, 0, 2) != 'en'): ?>
	<div lang="en" xml:lang="en" dir="ltr">
		<h1>Maintenance</h1>
		<p>This website is currently under going maintenance and will be back online shortly. Thank you for your patience.</p>
	</div>
<?php endif; ?>
</body></html>
<?php
	die();
}

/**
 * Check for the required PHP version, and the MySQL extension or
 * a database drop-in.
 *
 * Dies if requirements are not met.
 *
 * @since 1.1.0
 * @access private
 */
function gb_check_php_mysql_versions() {
	$php_version = phpversion();
	if ( version_compare( GB_PHP_REQUIRED, $php_version, '>' ) ) {
		gb_load_translations_early();
		header( 'Content-Type: text/html; charset=utf-8' );
		die( sprintf( __( 'Your server is running PHP version %1$s but GeniBase %2$s requires at least %3$s.' ), $php_version, GB_VERSION, GB_PHP_REQUIRED ) );
	}

	if ( ! extension_loaded( 'mysql' ) && ! extension_loaded( 'mysqli' ) ) {
		gb_load_translations_early();
		header( 'Content-Type: text/html; charset=utf-8' );
		die( __( 'Your PHP installation appears to be missing the MySQL extension which is required by GeniBase.' ) );
	}
}

/**
 * In debug mode display hidden debug reports.
 * 
 * @since 1.1.0
 * 
 * @param mixed	$var	Variable to be displayed
 * @param mixed	$ignore	Class name or backtrace levels count to be ignored in report
 */
function debug_info($var, $ignore = 0){
	if(!GB_DEBUG)	return;

	$trace = debug_backtrace();
	$place = substr($trace[0]['file'], strlen(BASE_DIR)) . ' #' . $trace[0]['line'];
	if($ignore !== 0){
		if(!is_string($ignore))
			$level = (int) $ignore;
		else{
			$level = 1;
			while(isset($trace[$level]) && isset($trace[$level]['class']) && $trace[$level]['class'] == $ignore)	$level++;
			$level--;
		}
		
		$place = substr($trace[$level]['file'], strlen(BASE_DIR)) .
				' #' . $trace[$level]['line'] . ' (' . $place . ')';
	}
	print("\n<!-- " . $place . ': ' . var_export($var, TRUE) . " -->\n");
}
