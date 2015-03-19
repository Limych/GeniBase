<?php
/**
 * Main GeniBase API.
 *
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * File validates against allowed set of defined rules.
 *
 * A return value of '1' means that the $file contains either '..' or './'. A
 * return value of '2' means that the $file contains ':' after the first
 * character. A return value of '3' means that the file is not in the allowed
 * files list.
 *
 * @since 2.0.0
 *
 * @param string $file File path.
 * @param array $allowed_files List of allowed files.
 * @return int 0 means nothing is wrong, greater than 0 means something was wrong.
 */
function validate_file( $file, $allowed_files = '' ) {
	if( false !== strpos( $file, '..' ) )
		return 1;

	if( false !== strpos( $file, './' ) )
		return 1;

	if( !empty( $allowed_files ) && ! in_array( $file, $allowed_files ) )
		return 3;

	if( ':' == substr( $file, 1, 1 ) )
		return 2;

	return 0;
}

/**
 * Convert a value to non-negative integer.
 *
 * @since 2.0.0
 *
 * @param	mixed	$val	Data you wish to have converted to a non-negative integer.
 * @return	integer	A non-negative integer.
 */
function absint($val) {
	return abs(intval($val));
}

/**
 * Convert integer number to format based on the locale.
 *
 * @since 2.0.0
 *
 * @param int $number   The number to convert based on locale.
 * @param int $decimals Optional. Precision of the number of decimal places. Default 0.
 * @return string Converted number in string format.
 */
function number_format_i18n($number, $decimals = 0){
	global $gb_locale;
	$formatted = number_format($number, absint($decimals),
			$gb_locale->number_format['decimal_point'],
			$gb_locale->number_format['thousands_sep'] );
	if( ' ' === $gb_locale->number_format['thousands_sep'] )
		$formatted = preg_replace('/^(\d)\D(\d{3})$/uS', '$1$2', $formatted);
	
	/**
	 * Filter the number formatted based on the locale.
	 *
	 * @since 2.1.1
	 *
	 * @param string $formatted Converted number in string format.
	*/
	return apply_filters('number_format_i18n', $formatted);
}

/**
 * Return a comma-separated string of functions that have been called to get
 * to the current point in code.
 *
 * @since 2.0.0
 *
 * @param string $ignore_class Optional. A class to ignore all function calls within - useful
 *                             when you want to just give info about the callee. Default null.
 * @param int    $skip_frames  Optional. A number of stack frames to skip - useful for unwinding
 *                             back to the source of the issue. Default 0.
 * @param bool   $pretty       Optional. Whether or not you want a comma separated string or raw
 *                             array returned. Default true.
 * @return string|array Either a string containing a reversed comma separated trace or an array
 *                      of individual calls.
 */
function gb_debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
	if( version_compare( PHP_VERSION, '5.2.5', '>=' ) )
		$trace = debug_backtrace( false );
	else
		$trace = debug_backtrace();

	$caller = array();
	$check_class = ! is_null( $ignore_class );
	$skip_frames++; // skip this function

	foreach ( $trace as $call ) {
		if( $skip_frames > 0 ) {
			$skip_frames--;
		}elseif( isset( $call['class'] ) ) {
			if( $check_class && $ignore_class == $call['class'] )
				continue; // Filter out calls

			$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
		}else{
			if( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
				$caller[] = "{$call['function']}('{$call['args'][0]}')";
			}elseif( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
				$caller[] = $call['function'] . "('" . str_replace( array( GB_CONTENT_DIR, ABSPATH ) , '', $call['args'][0] ) . "')";
			}else{
				$caller[] = $call['function'];
			}
		}
	}
	if( $pretty )
		return join( ', ', array_reverse( $caller ) );
	else
		return $caller;
}

/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 2.0.0
 *
 * @param string $data   Value to check to see if was serialized.
 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data, $strict = true ) {
	// if it isn't a string, it isn't serialized.
	if( !is_string($data))
		return false;

	$data = trim( $data );
	if( 'N;' == $data ) {
		return true;
	}
	if( strlen( $data ) < 4 ) {
		return false;
	}
	if( ':' !== $data[1] ) {
		return false;
	}
	if( $strict ) {
		$lastc = substr( $data, -1 );
		if( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	}else{
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if( false !== $semicolon && $semicolon < 3 )
			return false;
		if( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if( $strict ) {
				if( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			}elseif( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}

/**
 * Serialize data, if needed.
 *
 * @since 2.0.0
 *
 * @param string|array|object $data Data that might be serialized.
 * @return mixed A scalar data
 */
function maybe_serialize($data){
	if( is_array($data) || is_object($data))
		return serialize($data);

	return $data;
}

/**
 * Parses a string into variables to be stored in an array.
 *
 * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
 * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
 *
 * @since 2.0.0
 *
 * @param string $string The string to be parsed.
 * @param array $array Variables will be stored in this array.
 */
function gb_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array );
	/**
	 * Filter the array of variables derived from a parsed string.
	 *
	 * @since 2.1.0
	 *
	 * @param array $array The array populated with variables.
	*/
	$array = apply_filters( 'gb_parse_str', $array );
}

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 * @since 2.0.0
 *
 * @param string|array $args     Value to merge with $defaults
 * @param array        $defaults Optional. Array that serves as the defaults. Default empty.
 * @return array Merged user defined values with defaults.
 */
function gb_parse_args( $args, $defaults = '' ) {
	if( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif( is_array( $args ) )
	$r =& $args;
	else
		gb_parse_str( $args, $r );

	if( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}

/**
 * Retrieve the description for the HTTP status.
 *
 * @since 2.0.0
 *
 * @param int $code HTTP status code.
 * @return string Empty string if not found, or description if found.
 */
function get_status_header_desc($code){
	global $gb_header_to_desc;

	$code = absint( $code );

	if( !isset( $gb_header_to_desc ) ) {
		$gb_header_to_desc = array(
				100 => 'Continue',
				101 => 'Switching Protocols',
				102 => 'Processing',

				200 => 'OK',
				201 => 'Created',
				202 => 'Accepted',
				203 => 'Non-Authoritative Information',
				204 => 'No Content',
				205 => 'Reset Content',
				206 => 'Partial Content',
				207 => 'Multi-Status',
				226 => 'IM Used',

				300 => 'Multiple Choices',
				301 => 'Moved Permanently',
				302 => 'Found',
				303 => 'See Other',
				304 => 'Not Modified',
				305 => 'Use Proxy',
				306 => 'Reserved',
				307 => 'Temporary Redirect',

				400 => 'Bad Request',
				401 => 'Unauthorized',
				402 => 'Payment Required',
				403 => 'Forbidden',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				406 => 'Not Acceptable',
				407 => 'Proxy Authentication Required',
				408 => 'Request Timeout',
				409 => 'Conflict',
				410 => 'Gone',
				411 => 'Length Required',
				412 => 'Precondition Failed',
				413 => 'Request Entity Too Large',
				414 => 'Request-URI Too Long',
				415 => 'Unsupported Media Type',
				416 => 'Requested Range Not Satisfiable',
				417 => 'Expectation Failed',
				418 => 'I\'m a teapot',
				422 => 'Unprocessable Entity',
				423 => 'Locked',
				424 => 'Failed Dependency',
				426 => 'Upgrade Required',
				428 => 'Precondition Required',
				429 => 'Too Many Requests',
				431 => 'Request Header Fields Too Large',

				500 => 'Internal Server Error',
				501 => 'Not Implemented',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
				505 => 'HTTP Version Not Supported',
				506 => 'Variant Also Negotiates',
				507 => 'Insufficient Storage',
				510 => 'Not Extended',
				511 => 'Network Authentication Required',
		);
	}

	if( isset( $gb_header_to_desc[$code] ) )
		return $gb_header_to_desc[$code];
	else
		return '';
}

/**
 * Set HTTP status header.
 *
 * @since 2.0.0
 *
 * @see get_status_header_desc()
 *
 * @param int $code HTTP status code.
 */
function status_header( $code ) {
	$description = get_status_header_desc( $code );

	if( empty( $description ) )
		return;

	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol )
		$protocol = 'HTTP/1.0';
	$status_header = "$protocol $code $description";

	if( function_exists('apply_filters')){
		/**
		 * Filter an HTTP status header.
		 *
		 * @since 2.1.0
		 *
		 * @param string $status_header HTTP status header.
		 * @param int    $code          HTTP status code.
		 * @param string $description   Description for the status code.
		 * @param string $protocol      Server protocol.
		 */
		$status_header = apply_filters( 'status_header', $status_header, $code, $description, $protocol );
	}

	@header( $status_header, true, $code );
}

/**
 * Get the header information to prevent caching.
 *
 * The several different headers cover the different ways cache prevention
 * is handled by different browsers
 *
 * @since 2.0.0
 *
 * @return array The associative array of header names and field values.
 */
function get_nocache_headers() {
	$headers = array(
		'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
		'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
		'Pragma' => 'no-cache',
	);

	if( function_exists('apply_filters')){
		/**
		 * Filter the cache-controlling headers.
		 *
		 * @since 2.1.0
		 *
		 * @see gb_get_nocache_headers()
		 *
		 * @param array $headers {
		 *     Header names and field values.
		 *
		 *     @type string $Expires       Expires header.
		 *     @type string $Cache-Control Cache-Control header.
		 *     @type string $Pragma        Pragma header.
		 * }
		 */
		$headers = (array) apply_filters( 'nocache_headers', $headers );
	}
	$headers['Last-Modified'] = false;
	return $headers;
}

/**
 * Set the headers to prevent caching for the different browsers.
 *
 * Different browsers support different nocache headers, so several
 * headers must be sent so that all of them get the point that no
 * caching should occur.
 *
 * @since 2.0.0
 *
 * @see get_nocache_headers()
 */
function nocache_headers() {
	$headers = get_nocache_headers();

	unset( $headers['Last-Modified'] );

	// In PHP 5.3+, make sure we are not sending a Last-Modified header.
	if( function_exists( 'header_remove' ) ) {
		@header_remove( 'Last-Modified' );
	}else{
		// In PHP 5.2, send an empty Last-Modified header, but only as a
		// last resort to override a header already sent. #WP23021
		foreach ( headers_list() as $header ) {
			if( 0 === stripos( $header, 'Last-Modified' ) ) {
				$headers['Last-Modified'] = '';
				break;
			}
		}
	}

	foreach( $headers as $name => $field_value )
		@header("{$name}: {$field_value}");
}

/**
 * Kill GeniBase execution and display HTML message with error message.
 *
 * This function complements the `die()` PHP function. The difference is that
 * HTML will be displayed to the user. It is recommended to use this function
 * only when the execution should not continue any further. It is not recommended
 * to call this function very often, and try to handle as many errors as possible
 * silently or more gracefully.
 *
 * As a shorthand, the desired HTTP response code may be passed as an integer to
 * the `$title` parameter (the default title would apply) or the `$args` parameter.
 *
 * @since 2.0.0
 *
 * @param string|GB_Error  $message Optional. Error message. If this is a {@see GB_Error} object,
 *                                  the error's messages are used. Default empty.
 * @param string|int       $title   Optional. Error title. If `$message` is a `GB_Error` object,
 *                                  error data with the key 'title' may be used to specify the title.
 *                                  If `$title` is an integer, then it is treated as the response
 *                                  code. Default empty.
 * @param string|array|int $args {
 *     Optional. Arguments to control behavior. If `$args` is an integer, then it is treated
 *     as the response code. Default empty array.
 *
 *     @type int    $response       The HTTP response code. Default 500.
 *     @type bool   $back_link      Whether to include a link to go back. Default false.
 *     @type string $text_direction The text direction. This is only useful internally, when WordPress
 *                                  is still loading and the site's locale is not set up yet. Accepts 'rtl'.
 *                                  Default is the value of {@see is_rtl()}.
 * }
 */
function gb_die( $message = '', $title = '', $args = array() ) {

	if( is_int($args) ){
		$args = array( 'response' => $args );
	}elseif( is_int($title) ){
		$args  = array( 'response' => $title );
		$title = '';
	}

	if( defined('DOING_AJAX') && DOING_AJAX ){
		/**
		 * Filter callback for killing WordPress execution for AJAX requests.
		 *
		 * @since 2.1.0
		 *
		 * @param callback $function Callback function name.
		 */
		$function = apply_filters( 'gb_die_ajax_handler', '_ajax_gb_die_handler' );

	}elseif( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ){
		/**
		 * Filter callback for killing WordPress execution for XML-RPC requests.
		 *
		 * @since 2.1.0
		 *
		 * @param callback $function Callback function name.
		 */
		$function = apply_filters( 'gb_die_xmlrpc_handler', '_xmlrpc_gb_die_handler' );

	}else{
		/**
		 * Filter callback for killing WordPress execution for all non-AJAX, non-XML-RPC requests.
		 *
		 * @since 2.1.0
		 *
		 * @param callback $function Callback function name.
		 */
		$function = apply_filters( 'gb_die_handler', '_default_gb_die_handler' );
	}

	call_user_func( $function, $message, $title, $args );
}

/**
 * Kill GeniBase execution and display HTML message with error message.
 *
 * This is the default handler for gb_die if you want a custom one for your
 * site then you can overload using the gb_die_handler filter in gb_die
 *
 * @since 2.0.0
 * @access private
 *
 * @param string       $message Error message.
 * @param string       $title   Optional. Error title. Default empty.
 * @param string|array $args    Optional. Arguments to control behavior. Default empty array.
 */
function _default_gb_die_handler( $message, $title = '', $args = array() ) {
	$defaults = array( 'response' => 500 );
	$r = gb_parse_args($args, $defaults);

	$have_gettext = function_exists('__');

	if( function_exists('is_gb_error') && is_gb_error($message) ){
		if( empty($title) ){
			$error_data = $message->get_error_data();
			if( is_array( $error_data ) && isset( $error_data['title'] ) )
				$title = $error_data['title'];
		}
		$errors = $message->get_error_messages();
		switch ( count( $errors ) ) {
			case 0 :
				$message = '';
				break;
			case 1 :
				$message = "<p>{$errors[0]}</p>";
				break;
			default :
				$message = "<ul>\n\t\t<li>" . join( "</li>\n\t\t<li>", $errors ) . "</li>\n\t</ul>";
				break;
		}
	}elseif( is_string($message) ){
		$message = "<p>$message</p>";
	}

	if( isset( $r['back_link'] ) && $r['back_link'] ) {
		$back_text = $have_gettext? __('&laquo; Back') : '&laquo; Back';
		$message .= "\n<p><a href='javascript:history.back()'>$back_text</a></p>";
	}

// 	if( !did_action( 'admin_head' ) ):	// TODO: action admin_head

	status_header($r['response']);
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	if( empty($title) )
		$title = $have_gettext ? __('GeniBase Error') : 'GeniBase Error';

	$text_direction = 'ltr';
	if( isset($r['text_direction']) && 'rtl' == $r['text_direction'] )
		$text_direction = 'rtl';
	elseif( function_exists( 'is_rtl' ) && is_rtl() )
	$text_direction = 'rtl';
	?>
<!DOCTYPE html>
<!-- IE bug fix: always pad the error page with enough characters such that it is greater than 512 bytes, even after gzip compression abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono
-->
<html xmlns="http://www.w3.org/1999/xhtml" <?php if( function_exists( 'language_attributes' ) && function_exists( 'is_rtl' ) ) language_attributes(); else echo "dir='$text_direction'"; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title ?></title>
	<style type="text/css">
		html {
			background: #F1F1F1;
		}
		body {
			background: #FFF;
			color: #444;
			font-family: "Open Sans", sans-serif;
			margin: 2em auto;
			padding: 1em 2em;
			max-width: 700px;
			-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
			box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		}
		h1 {
			border-bottom: 1px solid #DADADA;
			clear: both;
			color: #666;
			font: 24px "Open Sans", sans-serif;
			margin: 30px 0 0 0;
			padding: 0;
			padding-bottom: 7px;
		}
		#error-page {
			margin-top: 50px;
		}
		#error-page p {
			font-size: 14px;
			line-height: 1.5;
			margin: 25px 0 20px;
		}
		#error-page code {
			font-family: Consolas, Monaco, monospace;
		}
		ul li {
			margin-bottom: 10px;
			font-size: 14px ;
		}
		a {
			color: #21759B;
			text-decoration: none;
		}
		a:hover {
			color: #D54E21;
		}
		.button {
			background: #F7F7F7;
			border: 1px solid #CCCCCC;
			color: #555;
			display: inline-block;
			text-decoration: none;
			font-size: 13px;
			line-height: 26px;
			height: 28px;
			margin: 0;
			padding: 0 10px 1px;
			cursor: pointer;
			-webkit-border-radius: 3px;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			-webkit-box-sizing: border-box;
			-moz-box-sizing:    border-box;
			box-sizing:         border-box;

			-webkit-box-shadow: inset 0 1px 0 #FFF, 0 1px 0 rgba(0,0,0,.08);
			box-shadow: inset 0 1px 0 #FFF, 0 1px 0 rgba(0,0,0,.08);
		 	vertical-align: top;
		}

		.button.button-large {
			height: 29px;
			line-height: 28px;
			padding: 0 12px;
		}

		.button:hover,
		.button:focus {
			background: #FAFAFA;
			border-color: #999;
			color: #222;
		}

		.button:focus  {
			-webkit-box-shadow: 1px 1px 1px rgba(0,0,0,.2);
			box-shadow: 1px 1px 1px rgba(0,0,0,.2);
		}

		.button:active {
			background: #EEE;
			border-color: #999;
			color: #333;
			-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		}

		<?php if( 'rtl' == $text_direction ) : ?>
		body { font-family: Tahoma, Arial; }
		<?php endif; ?>
	</style>
</head>
<body id="error-page">
<?php //endif; // ! did_action( 'admin_head' ) // TODO: action admin_head ?>
	<?php echo $message; ?>
</body>
</html>
<?php
	die();
}

/**
 * Kill GeniBase execution and display XML message with error message.
 *
 * This is the handler for gb_die when processing XMLRPC requests.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string       $message Error message.
 * @param string       $title   Optional. Error title. Default empty.
 * @param string|array $args    Optional. Arguments to control behavior. Default empty array.
 */
function _xmlrpc_gb_die_handler( $message, $title = '', $args = array() ) {
	global $gb_xmlrpc_server;
	$defaults = array( 'response' => 500 );

	$r = gb_parse_args($args, $defaults);

	// TODO: XMLRPC
// 	if( $gb_xmlrpc_server ) {
// 		$error = new IXR_Error( $r['response'] , $message);
// 		$gb_xmlrpc_server->output( $error->getXml() );
// 	}
	die();
}

/**
 * Kill WordPress ajax execution.
 *
 * This is the handler for gb_die when processing Ajax requests.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string $message Optional. Response to print. Default empty.
 */
function _ajax_gb_die_handler( $message = '' ) {
	if( is_scalar($message) )
		die((string) $message);
	die('0');
}

/**
 * Kill WordPress execution.
 *
 * This is the handler for gb_die when processing APP requests.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string $message Optional. Response to print. Default empty.
 */
function _scalar_gb_die_handler( $message = '' ) {
	if( is_scalar($message) )
		die((string) $message);
	die();
}

/**
 * Retrieve a list of protocols to allow in HTML attributes.
 *
 * @since 2.2.0
 *
 * @see gb_kses()
 * @see esc_url()
 *
 * @return array Array of allowed protocols.
 */
function gb_allowed_protocols() {
	static $protocols;

	if( empty($protocols) ){
		$protocols = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher',
				'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn', 'tel', 'fax', 'xmpp' );

		/**
		 * Filter the list of protocols allowed in HTML attributes.
		 *
		 * @since	2.1.0
		 *
		 * @param array $protocols Array of allowed protocols e.g. 'http', 'ftp', 'tel', and more.
		*/
		$protocols = apply_filters('kses_allowed_protocols', $protocols);
	}

	return $protocols;
}

/**
 * Parse query URI for protocol, base, query and anchor parts.
 * 
 * Mostly for internal use.
 * 
 * @since	2.0.1
 * 
 * @param string $uri
 * @return array
 */
function parse_query($uri = false){
	if( $uri === false )
		$uri = $_SERVER['REQUEST_URI'];

	if( $frag = strstr($uri, '#') )
		$uri = substr($uri, 0, -strlen($frag));
	else
		$frag = '';
	
	if( 0 === stripos($uri, 'http://') ){
		$protocol = 'http://';
		$uri = substr($uri, 7);
	}elseif( 0 === stripos($uri, 'https://') ){
		$protocol = 'https://';
		$uri = substr($uri, 8);
	}else{
		$protocol = '';
	}
	
	if( strpos($uri, '?') !== false ){
		list($base, $query) = explode('?', $uri, 2);
		$base .= '?';
	}elseif( $protocol || strpos($uri, '=') === false ){
		$base = $uri . '?';
		$query = '';
	}else{
		$base = '';
		$query = $uri;
	}
	
	return array($protocol, $base, $query, $frag);
}

/**
 * Retrieve a modified URL query string.
 *
 * You can rebuild the URL and append a new query variable to the URL query by
 * using this function. You can also retrieve the full URL with query data.
 *
 * Adding a single key & value or an associative array. Setting a key value to
 * an empty string removes the key. Omitting oldquery_or_uri uses the $_SERVER
 * value. Additional values provided are expected to be encoded appropriately
 * with urlencode() or rawurlencode().
 *
 * @since	2.0.0
 *
 * @param string|array $param1 Either newkey or an associative_array.
 * @param string       $param2 Either newvalue or oldquery or URI.
 * @param string       $param3 Optional. Old query or URI.
 * @return string New URL query string.
 */
function add_query_arg() {
	$args = func_get_args();
	if( is_array( $args[0] ) ) {
		if( count( $args ) < 2 || false === $args[1] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[1];
	}else{
		if( count( $args ) < 3 || false === $args[2] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[2];
	}

	list($protocol, $base, $query, $frag) = parse_query($uri);

	gb_parse_str( $query, $qs );
	$qs = urlencode_deep( $qs ); // this re-URL-encodes things that were already in the query string
	if( is_array( $args[0] ) ) {
		$kayvees = $args[0];
		$qs = array_merge( $qs, $kayvees );
	}else{
		$qs[ $args[0] ] = $args[1];
	}

	foreach ( $qs as $k => $v ) {
		if( $v === false )
			unset( $qs[$k] );
	}

	$ret = build_query( $qs );
	$ret = trim( $ret, '?' );
	$ret = preg_replace( '#=(&|$)#', '$1', $ret );
	$ret = $protocol . $base . $ret . $frag;
	$ret = rtrim( $ret, '?' );
	return $ret;
}

/**
 * Removes an item or list from the query string.
 *
 * @since	2.0.0
 *
 * @param string|array $key	Query key or keys to remove.
 * @param bool|string  $uri	Optional. When false uses the $_SERVER value. Default false.
 * @return string New URL query string.
 */
function remove_query_arg($key, $uri = false){
	if( is_array( $key ) ) { // removing multiple keys
		foreach ( $key as $k )
			$query = add_query_arg( $k, false, $uri);
		return $query;
	}
	return add_query_arg( $key, false, $uri);
}

/**
 * Build URL query based on an associative and, or indexed array.
 *
 * This is a convenient function for easily building url queries. It sets the
 * separator to '&' and uses _http_build_query() function.
 *
 * @since	2.0.0
 *
 * @see _http_build_query() Used to build the query
 * @see http://us2.php.net/manual/en/function.http-build-query.php for more on what
 *		http_build_query() does.
 *
 * @param array $data URL-encode key/value pairs.
 * @return string URL-encoded string.
 */
function build_query( $data ) {
	return _http_build_query( $data, null, '&', '', false );
}

/**
 * From php.net (modified by Mark Jaquith to behave like the native PHP5 function).
 *
 * @since	2.0.0
 * @access	private
 *
 * @see http://us1.php.net/manual/en/function.http-build-query.php
 *
 * @param array|object  $data       An array or object of data. Converted to array.
 * @param string        $prefix     Optional. Numeric index. If set, start parameter numbering with it.
 *                                  Default null.
 * @param string        $sep        Optional. Argument separator; defaults to 'arg_separator.output'.
 *                                  Default null.
 * @param string        $key        Optional. Used to prefix key name. Default empty.
 * @param bool          $urlencode  Optional. Whether to use urlencode() in the result. Default true.
 *
 * @return string The query string.
 */
function _http_build_query( $data, $prefix = null, $sep = null, $key = '', $urlencode = true ) {
	$ret = array();

	foreach ( (array) $data as $k => $v ) {
		if( $urlencode)
			$k = urlencode($k);
		if( is_int($k) && $prefix != null )
			$k = $prefix.$k;
		if( !empty($key) )
			$k = $key . '%5B' . $k . '%5D';
		if( $v === null )
			continue;
		elseif( $v === FALSE )
		$v = '0';

		if( is_array($v) || is_object($v) )
			array_push($ret,_http_build_query($v, '', $sep, $k, $urlencode));
		elseif( $urlencode )
		array_push($ret, $k.'='.urlencode($v));
		else
			array_push($ret, $k.'='.$v);
	}

	if( null === $sep )
		$sep = ini_get('arg_separator.output');

	return implode($sep, $ret);
}

/**
 * Checks if the browser/device is a robot
 *
 * @since  2.0.1
 * 
 * @param string $is_first_visited_page	FALSE, if this user visit other pages before. 
 * @return boolean	TRUE if user is a robot.
 */
function is_bot_user($is_first_visited_page = TRUE) {
// 	if( is_user_logged_in()){	// TODO: users
// 		return false;

	// XML RPC requests are probably from cybernetic beasts
	if( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST )
		return true;

	if( !empty($_SERVER['HTTP_USER_AGENT']) ){
		// the user agent could be google bot, bing bot or some other bot,  one would hope real user agents do not have the
		// string 'bot|spider|crawler|preview' in them, there are bots that don't do us the kindness of identifying themselves as such,
		// check for the user being logged in in a real user is using a bot to access content from our site
		$bot_agent_strings = array('alexa', 'altavista', 'ask jeeves', 'attentio', 'baiduspider',
				'bingbot', 'bot', 'chtml generic', 'crawler', 'fastmobilecrawl', 'feedfetcher-google',
				'firefly', 'froogle', 'gigabot', 'googlebot', 'googlebot-mobile', 'heritrix',
				'ia_archiver', 'infoseek', 'irlbot', 'jumpbot', 'lycos', 'mail.ru', 'mediapartners',
				'mediobot', 'motionbot', 'mshots', 'msnbot', 'openbot', 'php', 'preview',
				'pss-webkit-request', 'pythumbnail', 'robot', 'scooter', 'slurp', 'snapbot',
				'spider', 'stackrambler', 'taptubot', 'technoratisnoop', 'teleport', 'teoma',
				'twiceler', 'webalta', 'wget', 'wordpress', 'yahooseeker', 'yahooysmcm', 'yammybot', );
		$bot_agent_strings = apply_filters('bot_user_agents', $bot_agent_strings);
		foreach($bot_agent_strings as $bot){
			if( stripos($_SERVER['HTTP_USER_AGENT'], $bot) !== false)
				return true;
		}
	}
	
	if( !$is_first_visited_page ){
		// Common bots don't send referers
		if( !isset($_SERVER['HTTP_REFERER']) || empty($_SERVER['HTTP_REFERER']) )
			return true;

		// Common bots don't save cookies
		if( !isset($_COOKIE[GB_COOKIE_USERID]) && 0 == strncmp(site_url(), $_SERVER['HTTP_REFERER'], strlen(site_url())) )
			return true;
	}

	return false;
}

/**
 * Determine if SSL is used.
 *
 * @since 2.0.1
 *
 * @return bool True if SSL, false if not used.
 */
function is_ssl() {
	if( isset($_SERVER['HTTPS']) ){
		if( 'on' == strtolower($_SERVER['HTTPS']) )
			return true;
		if( '1' == $_SERVER['HTTPS'] )
			return true;
	}elseif( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ){
		return true;
	}
	return false;
}

/**
 * Whether SSL login should be forced.
 *
 * @since 2.0.1
 *
 * @see force_ssl_admin()
 *
 * @param string|bool $force Optional Whether to force SSL login. Default null.
 * @return bool True if forced, false if not forced.
 */
function force_ssl_login( $force = null ) {
	return force_ssl_admin($force);
}

/**
 * Whether to force SSL used for the Administration Screens.
 *
 * @since 2.0.1
 *
 * @param string|bool $force Optional. Whether to force SSL in admin screens. Default null.
 * @return bool True if forced, false if not forced.
 */
function force_ssl_admin( $force = null ) {
	static $forced = false;

	if( !is_null($force) ){
		$old_forced = $forced;
		$forced = $force;
		return $old_forced;
	}

	return $forced;
}

if( !function_exists('mb_ucfirst') ):
/**
 * Make a string's first character uppercase (Multibyte version)
 *
 * @since	1.0.0
 *
 * @param	string	$str	The input string
 * @return	string		The resilting string.
 */
function mb_ucfirst($str){
	return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
}
endif;

/**
 * Guess the URL for the site.
 *
 * Will remove gb-admin links to retrieve only return URLs not in the gb-admin
 * directory.
 *
 * @since 2.1.1
 *
 * @return string The guessed URL.
 */
function gb_guess_url() {
	if( defined('GB_SITEURL') && '' != GB_SITEURL ){
		$url = GB_SITEURL;
	}else{
		$abspath_fix = str_replace('\\', '/', BASE_DIR);
		$script_filename_dir = dirname( $_SERVER['SCRIPT_FILENAME'] );

		// The request is for the admin
		if( strpos($_SERVER['REQUEST_URI'], 'gb-admin') !== false ) {
			$path = preg_replace('#/(gb-admin/.*)#i', '', $_SERVER['REQUEST_URI'] );

			// The request is for a file in BASE_DIR
		}elseif( $script_filename_dir . '/' == $abspath_fix ){
			// Strip off any file/query params in the path
			$path = preg_replace('#/[^/]*$#i', '', $_SERVER['PHP_SELF']);

		}else{
			if( false !== strpos($_SERVER['SCRIPT_FILENAME'], $abspath_fix) ){
				// Request is hitting a file inside ABSPATH
				$directory = str_replace(BASE_DIR, '', $script_filename_dir);
				// Strip off the sub directory, and any file/query paramss
				$path = preg_replace('#/' . preg_quote($directory, '#') . '/[^/]*$#i', '' , $_SERVER['REQUEST_URI']);

			}elseif( false !== strpos($abspath_fix, $script_filename_dir) ){
				// Request is hitting a file above ABSPATH
				$subdirectory = substr($abspath_fix, strpos($abspath_fix, $script_filename_dir) + strlen($script_filename_dir));
				// Strip off any file/query params from the path, appending the sub directory to the install
				$path = preg_replace('#/[^/]*$#i', '' , $_SERVER['REQUEST_URI']) . $subdirectory;

			}else{
				$path = $_SERVER['REQUEST_URI'];
			}
		}

		$schema = is_ssl() ? 'https://' : 'http://'; // set_url_scheme() is not defined yet
		$url = $schema . $_SERVER['HTTP_HOST'] . $path;
	}

	return rtrim($url, '/');
}

/**
 * Mark a function as deprecated and inform when it has been used.
 *
 * There is a hook deprecated_function_run that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated
 * function.
 *
 * The current behavior is to trigger a user error if GB_DEBUG is true.
 *
 * This function is to be used in every function that is deprecated.
 *
 * @since	2.2.2
 * @access private
 *
 * @param string $function    The function that was called.
 * @param string $version     The version of GeniBase that deprecated the function.
 * @param string $replacement Optional. The function that should have been called. Default null.
 */
function _deprecated_function( $function, $version, $replacement = null ) {

	/**
	 * Fires when a deprecated function is called.
	 *
	 * @since	2.2.2
	 *
	 * @param string $function    The function that was called.
	 * @param string $replacement The function that should have been called.
	 * @param string $version     The version of GeniBase that deprecated the function.
	 */
	do_action( 'deprecated_function_run', $function, $replacement, $version );

	/**
	 * Filter whether to trigger an error for deprecated functions.
	 *
	 * @since	2.2.2
	 *
	 * @param bool $trigger Whether to trigger the error for deprecated functions. Default true.
	*/
	if( GB_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
		if( function_exists( '__' ) ) {
			if( ! is_null( $replacement ) )
				trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.'), $function, $version, $replacement ) );
			else
				trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.'), $function, $version ) );
		} else {
			if( ! is_null( $replacement ) )
				trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $function, $version, $replacement ) );
			else
				trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', $function, $version ) );
		}
	}
}

/**
 * Mark a file as deprecated and inform when it has been used.
 *
 * There is a hook deprecated_file_included that will be called that can be used
 * to get the backtrace up to what file and function included the deprecated
 * file.
 *
 * The current behavior is to trigger a user error if GB_DEBUG is true.
 *
 * This function is to be used in every file that is deprecated.
 *
 * @since	2.2.2
 * @access private
 *
 * @param string $file        The file that was included.
 * @param string $version     The version of GeniBase that deprecated the file.
 * @param string $replacement Optional. The file that should have been included based on ABSPATH.
 *                            Default null.
 * @param string $message     Optional. A message regarding the change. Default empty.
 */
function _deprecated_file( $file, $version, $replacement = null, $message = '' ) {

	/**
	 * Fires when a deprecated file is called.
	 *
	 * @since	2.2.2
	 *
	 * @param string $file        The file that was called.
	 * @param string $replacement The file that should have been included based on ABSPATH.
	 * @param string $version     The version of GeniBase that deprecated the file.
	 * @param string $message     A message regarding the change.
	 */
	do_action( 'deprecated_file_included', $file, $replacement, $version, $message );

	/**
	 * Filter whether to trigger an error for deprecated files.
	 *
	 * @since	2.2.2
	 *
	 * @param bool $trigger Whether to trigger the error for deprecated files. Default true.
	*/
	if( GB_DEBUG && apply_filters( 'deprecated_file_trigger_error', true ) ) {
		$message = empty( $message ) ? '' : ' ' . $message;
		if( function_exists( '__' ) ) {
			if( ! is_null( $replacement ) )
				trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.'), $file, $version, $replacement ) . $message );
			else
				trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.'), $file, $version ) . $message );
		} else {
			if( ! is_null( $replacement ) )
				trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $file, $version, $replacement ) . $message );
			else
				trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', $file, $version ) . $message );
		}
	}
}
/**
 * Mark a function argument as deprecated and inform when it has been used.
 *
 * This function is to be used whenever a deprecated function argument is used.
 * Before this function is called, the argument must be checked for whether it was
 * used by comparing it to its default value or evaluating whether it is empty.
 * For example:
 *
 *     if( ! empty( $deprecated ) ) {
 *         _deprecated_argument( __FUNCTION__, '3.0' );
 *     }
 *
 *
 * There is a hook deprecated_argument_run that will be called that can be used
 * to get the backtrace up to what file and function used the deprecated
 * argument.
 *
 * The current behavior is to trigger a user error if GB_DEBUG is true.
 *
 * @since	2.2.2
 * @access private
 *
 * @param string $function The function that was called.
 * @param string $version  The version of GeniBase that deprecated the argument used.
 * @param string $message  Optional. A message regarding the change. Default null.
 */
function _deprecated_argument( $function, $version, $message = null ) {

	/**
	 * Fires when a deprecated argument is called.
	 *
	 * @since	2.2.2
	 *
	 * @param string $function The function that was called.
	 * @param string $message  A message regarding the change.
	 * @param string $version  The version of GeniBase that deprecated the argument used.
	 */
	do_action( 'deprecated_argument_run', $function, $message, $version );

	/**
	 * Filter whether to trigger an error for deprecated arguments.
	 *
	 * @since	2.2.2
	 *
	 * @param bool $trigger Whether to trigger the error for deprecated arguments. Default true.
	*/
	if( GB_DEBUG && apply_filters( 'deprecated_argument_trigger_error', true ) ) {
		if( function_exists( '__' ) ) {
			if( ! is_null( $message ) )
				trigger_error( sprintf( __('%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s! %3$s'), $function, $version, $message ) );
			else
				trigger_error( sprintf( __('%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s with no alternative available.'), $function, $version ) );
		} else {
			if( ! is_null( $message ) )
				trigger_error( sprintf( '%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s! %3$s', $function, $version, $message ) );
			else
				trigger_error( sprintf( '%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s with no alternative available.', $function, $version ) );
		}
	}
}

/**
 * Mark something as being incorrectly called.
 *
 * There is a hook doing_it_wrong_run that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated
 * function.
 *
 * The current behavior is to trigger a user error if GB_DEBUG is true.
 *
 * @since	2.2.2
 * @access private
 *
 * @param string $function The function that was called.
 * @param string $message  A message explaining what has been done incorrectly.
 * @param string $version  The version of GeniBase where the message was added.
 */
function _doing_it_wrong( $function, $message, $version ) {

	/**
	 * Fires when the given function is being used incorrectly.
	 *
	 * @since	2.2.2
	 *
	 * @param string $function The function that was called.
	 * @param string $message  A message explaining what has been done incorrectly.
	 * @param string $version  The version of GeniBase where the message was added.
	 */
	do_action( 'doing_it_wrong_run', $function, $message, $version );

	/**
	 * Filter whether to trigger an error for _doing_it_wrong() calls.
	 *
	 * @since	2.2.2
	 *
	 * @param bool $trigger Whether to trigger the error for _doing_it_wrong() calls. Default true.
	*/
	if( GB_DEBUG && apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
		if( function_exists( '__' ) ) {
			$version = is_null( $version ) ? '' : sprintf( __( '(This message was added in version %s.)' ), $version );
			// TODO Debugging link
// 			$message .= ' ' . __( 'Please see <a href="http://codex.wordpress.org/Debugging_in_GeniBase">Debugging in GeniBase</a> for more information.' );
			trigger_error( sprintf( __( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s' ), $function, $message, $version ) );
		} else {
			$version = is_null( $version ) ? '' : sprintf( '(This message was added in version %s.)', $version );
			// TODO Debugging link
// 			$message .= ' Please see <a href="http://codex.wordpress.org/Debugging_in_GeniBase">Debugging in GeniBase</a> for more information.';
			trigger_error( sprintf( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s', $function, $message, $version ) );
		}
	}
}

/**
 * Returns true.
 *
 * Useful for returning true to filters easily.
 *
 * @since	2.2.2
 *
 * @see __return_false()
 *
 * @return bool True.
 */
function __return_true() {
	return true;
}

/**
 * Returns false.
 *
 * Useful for returning false to filters easily.
 *
 * @since	2.2.2
 *
 * @see __return_true()
 *
 * @return bool False.
 */
function __return_false() {
	return false;
}

/**
 * Returns 0.
 *
 * Useful for returning 0 to filters easily.
 *
 * @since	2.2.2
 *
 * @return int 0.
 */
function __return_zero() {
	return 0;
}

/**
 * Returns an empty array.
 *
 * Useful for returning an empty array to filters easily.
 *
 * @since	2.2.2
 *
 * @return array Empty array.
 */
function __return_empty_array() {
	return array();
}

/**
 * Returns null.
 *
 * Useful for returning null to filters easily.
 *
 * @since	2.2.2
 *
 * @return null Null value.
 */
function __return_null() {
	return null;
}

/**
 * Returns an empty string.
 *
 * Useful for returning an empty string to filters easily.
 *
 * @since	2.2.2
 *
 * @see __return_null()
 *
 * @return string Empty string.
 */
function __return_empty_string() {
	return '';
}
