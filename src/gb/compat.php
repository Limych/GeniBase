<?php
/**
 * GeniBase implementation for PHP functions either missing from older PHP versions or not included by default.
 *
 * @package PHP
 * @access private
 */

/**
 * If gettext isn't available
 */ 
if( !function_exists('_') ):
function _($string) {
	return $string;
}
endif;

/**
 * mb_substr()
 */
if( !function_exists('mb_substr') ):
function mb_substr( $str, $start, $length = null, $encoding = null ) {
	return _mb_substr($str, $start, $length, $encoding);
}
endif;

function _mb_substr( $str, $start, $length=null, $encoding=null ) {
// 	// the solution below, works only for utf-8, so in case of a different
// 	// charset, just use built-in substr
// 	$charset = get_option( 'blog_charset' );
// 	if( !in_array( $charset, array('utf8', 'utf-8', 'UTF8', 'UTF-8') ) ) {
// 		return is_null( $length )? substr( $str, $start ) : substr( $str, $start, $length);
// 	}
	// use the regex unicode support to separate the UTF-8 characters into an array
	preg_match_all( '/./us', $str, $match );
	$chars = is_null( $length )? array_slice( $match[0], $start ) : array_slice( $match[0], $start, $length );
	return implode( '', $chars );
}

/**
 * mb_ucfirst()
 */
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
 * hash_hmac()
 */
if( !function_exists('hash_hmac') ):
function hash_hmac($algo, $data, $key, $raw_output = false) {
	return _hash_hmac($algo, $data, $key, $raw_output);
}
endif;

function _hash_hmac($algo, $data, $key, $raw_output = false) {
	$packs = array('md5' => 'H32', 'sha1' => 'H40');

	if( !isset($packs[$algo]) )
		return false;

	$pack = $packs[$algo];

	if(strlen($key) > 64)
		$key = pack($pack, $algo($key));

	$key = str_pad($key, 64, chr(0));

	$ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
	$opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));

	$hmac = $algo($opad . pack($pack, $algo($ipad . $data)));

	if( $raw_output )
		return pack( $pack, $hmac );
	return $hmac;
}

/**
 * json_encode()
 */
// TODO: class-json
// if( !function_exists('json_encode') ):
// function json_encode( $string ) {
// 	global $gb_json;

// 	if( !is_a($gb_json, 'Services_JSON') ){
// 		require_once(GB_CORE_DIR . '/class-json.php');
// 		$gb_json = new Services_JSON();
// 	}

// 	return $gb_json->encodeUnsafe($string);
// }
// endif;

/**
 * json_decode()
 */
// TODO: class-json
// if( !function_exists('json_decode') ):
// function json_decode( $string, $assoc_array = false ) {
// 	global $gb_json;

// 	if( !is_a($gb_json, 'Services_JSON') ) {
// 		require_once(GB_CORE_DIR . '/class-json.php');
// 		$gb_json = new Services_JSON();
// 	}

// 	$res = $gb_json->decode( $string );
// 	if( $assoc_array )
// 		$res = _json_decode_object_helper( $res );
// 	return $res;
// }
// function _json_decode_object_helper($data) {
// 	if( is_object($data) )
// 		$data = get_object_vars($data);
// 	return is_array($data) ? array_map(__FUNCTION__, $data) : $data;
// }
// endif;

/**
 * hash_equals()
 */
if( !function_exists('hash_equals') ):
/**
 * Compare two strings in constant time.
 *
 * This function was added in PHP 5.6.
 * It can leak the length of a string.
 *
 * @since	2.2.2
 *
 * @param string $a Expected string.
 * @param string $b Actual string.
 * @return bool Whether strings are equal.
 */
function hash_equals( $a, $b ) {
	$a_length = strlen($a);
	if( $a_length !== strlen($b) )
		return false;
	$result = 0;

	// Do not attempt to "optimize" this.
	for($i = 0; $i < $a_length; $i++){
		$result |= ord($a[$i]) ^ ord($b[$i]);
	}

	return $result === 0;
}
endif;

// JSON_PRETTY_PRINT was introduced in PHP 5.4
// Defined here to prevent a notice when using it with gb_json_encode()
if( !defined('JSON_PRETTY_PRINT') )
	define( 'JSON_PRETTY_PRINT', 128 );
