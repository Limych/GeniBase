<?php
/**
 * These functions are needed to load GeniBase.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress
 */

// Direct execution forbidden for this script
if(!defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Turn register globals off.
 *
 * @since 2.0.0
 * @access private
 *
 * @return null Will return null if register_globals PHP directive was disabled.
 */
function gb_unregister_GLOBALS() {
	if ( !ini_get( 'register_globals' ) )
		return;

	if ( isset( $_REQUEST['GLOBALS'] ) )
		die( 'GLOBALS overwrite attempt detected' );

	// Variables that shouldn't be unset
	$no_unset = array( 'GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', 'table_prefix' );

	$input = array_merge( $_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset( $_SESSION ) && is_array( $_SESSION ) ? $_SESSION : array() );
	foreach ( $input as $k => $v )
		if ( !in_array( $k, $no_unset ) && isset( $GLOBALS[$k] ) ) {
			unset( $GLOBALS[$k] );
		}
}

/**
 * Fix `$_SERVER` variables for various setups.
 *
 * @since 2.0.0
 * @access private
 *
 * @global string $PHP_SELF The filename of the currently executing script,
 *                          relative to the document root.
 */
function gb_fix_server_vars() {
	global $PHP_SELF;

	$default_server_values = array(
			'SERVER_SOFTWARE' => '',
			'REQUEST_URI' => '',
	);

	$_SERVER = array_merge( $default_server_values, $_SERVER );

	// Fix for IIS when running with PHP ISAPI
	if ( empty( $_SERVER['REQUEST_URI'] ) || ( php_sapi_name() != 'cgi-fcgi' && preg_match( '/^Microsoft-IIS\//', $_SERVER['SERVER_SOFTWARE'] ) ) ) {

		// IIS Mod-Rewrite
		if ( isset( $_SERVER['HTTP_X_ORIGINAL_URL'] ) ) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
		}
		// IIS Isapi_Rewrite
		else if ( isset( $_SERVER['HTTP_X_REWRITE_URL'] ) ) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
		} else {
			// Use ORIG_PATH_INFO if there is no PATH_INFO
			if ( !isset( $_SERVER['PATH_INFO'] ) && isset( $_SERVER['ORIG_PATH_INFO'] ) )
				$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];

			// Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
					$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
				else
					$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
			}

			// Append the query string if it exists and isn't null
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
			}
		}
	}

	// Fix for PHP as CGI hosts that set SCRIPT_FILENAME to something ending in php.cgi for all requests
	if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && ( strpos( $_SERVER['SCRIPT_FILENAME'], 'php.cgi' ) == strlen( $_SERVER['SCRIPT_FILENAME'] ) - 7 ) )
		$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'];

	// Fix for Dreamhost and other PHP as CGI hosts
	if ( strpos( $_SERVER['SCRIPT_NAME'], 'php.cgi' ) !== false )
		unset( $_SERVER['PATH_INFO'] );

	// Fix empty PHP_SELF
	$PHP_SELF = $_SERVER['PHP_SELF'];
	if ( empty( $PHP_SELF ) )
		$_SERVER['PHP_SELF'] = $PHP_SELF = preg_replace( '/(\?.*)?$/', '', $_SERVER["REQUEST_URI"] );
}

/**
 * Don't load all of system when handling a favicon.ico request.
 *
 * Instead, send the headers for a zero-length favicon and bail.
 *
 * @since 2.0.0
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
 * @since 2.0.0
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

// 		if ( @is_dir( ABSPATH . 'gb-content/languages' ) )
// 			$locations[] = ABSPATH . 'gb-content/languages';

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
 * @since 2.0.0
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
 * @since 2.0.0
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
 * Start the GeniBase micro-timer.
 *
 * @since 2.0.0
 * @access private
 *
 * @global float $gb_timer['start'] Unix timestamp set at the beginning of the page load.
 * @see timer_stop()
 */
function timer_start() {
	global $gb_timer;
	$gb_timer['start'] = microtime(true);
}

/**
 * Retrieve or display the time from the page start to when function is called.
 *
 * @since 2.0.0
 *
 * @global float $gb_timer['start'] Seconds from when timer_start() is called.
 * @global float $gb_timer['end']   Seconds from when function is called.
 *
 * @param int $display   Whether to echo or return the results. Accepts 0|false for return,
 *                       1|true for echo. Default 0|false.
 * @param int $precision The number of digits from the right of the decimal to display.
 *                       Default 3.
 * @return string The "second.microsecond" finished time calculation. The number is formatted
 *                for human consumption, both localized and rounded.
 */
function timer_stop($display = false, $precision = 3){
	global $gb_timer;
	$gb_timer['end'] = microtime(true);
	$timetotal = $gb_timer['end'] - $gb_timer['start'];
	$r = (function_exists('number_format_i18n'))
			? number_format_i18n($timetotal, $precision)
			: number_format($timetotal, $precision);
	if($display)
		echo $r;
	return $r;
}

/**
 * Set PHP error reporting based on GeniBase debug settings.
 *
 * Uses three constants: `GB_DEBUG`, `GB_DEBUG_DISPLAY`, and `GB_DEBUG_LOG`.
 * All three can be defined in gb-config.php, and by default are set to false.
 *
 * When `GB_DEBUG` is true, all PHP notices are reported. GeniBase will also
 * display internal notices: when a deprecated GeniBase function, function
 * argument, or file is used. Deprecated code may be removed from a later
 * version.
 *
 * It is strongly recommended that plugin and theme developers use `GB_DEBUG`
 * in their development environments.
 *
 * `GB_DEBUG_DISPLAY` and `GB_DEBUG_LOG` perform no function unless `GB_DEBUG`
 * is true.
 *
 * When `GB_DEBUG_DISPLAY` is true, GeniBase will force errors to be displayed.
 * `GB_DEBUG_DISPLAY` defaults to true. Defining it as null prevents GeniBase
 * from changing the global configuration setting. Defining `GB_DEBUG_DISPLAY`
 * as false will force errors to be hidden.
 *
 * When `GB_DEBUG_LOG` is true, errors will be logged to debug.log in the content
 * directory.
 *
 * Errors are never displayed for XML-RPC requests.
 *
 * @since 2.0.0
 * @access private
 */
function gb_debug_mode() {
	if ( GB_DEBUG ) {
		error_reporting( E_ALL );

		if ( GB_DEBUG_DISPLAY )
			ini_set( 'display_errors', 1 );
		elseif ( null !== GB_DEBUG_DISPLAY )
			ini_set( 'display_errors', 0 );

		if ( GB_DEBUG_LOG ) {
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', BASE_DIR . '/debug.log' );
		}
	} else {
		error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
	}
	if ( defined( 'XMLRPC_REQUEST' ) )
		ini_set( 'display_errors', 0 );
}

/**
 * In debug mode display hidden debug reports.
 *
 * @since 2.0.0
 *
 * @param mixed	$var	Variable to be displayed
 * @param mixed	$ignore	Class name or backtrace levels count to be ignored in report
 */
function gb_debug_info($var, $ignore = 0){
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
