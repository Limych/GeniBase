<?php
/**
 * These functions can be replaced via plugins. If plugins do not redefine these
 * functions, then these will be used instead.
 *
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © GeniBase Team
 */



if( !function_exists('gb_redirect') ):
/**
 * Redirects to another page.
 *
 * @since	2.2.3
 *
 * @param string $location The path to redirect to.
 * @param int $status Status code to use.
 * @return bool False if $location is not provided, true otherwise.
 */
function gb_redirect($location, $status = 302) {
	global $is_IIS;

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter the redirect location.
		 *
		 * @since	2.2.3
		 *
		 * @param string $location The path to redirect to.
		 * @param int    $status   Status code to use.
		 */
		$location = apply_filters( 'gb_redirect', $location, $status );
	
		/**
		 * Filter the redirect status code.
		 *
		 * @since	2.2.3
		 *
		 * @param int    $status   Status code to use.
		 * @param string $location The path to redirect to.
		 */
		$status = apply_filters( 'gb_redirect_status', $status, $location );
	}

	if( !$location )
		return false;

	$location = gb_sanitize_redirect($location);

	if( !$is_IIS && PHP_SAPI != 'cgi-fcgi' )
		status_header($status); // This causes problems on IIS and some FastCGI setups

	header("Location: $location", true, $status);

	return true;
}
endif;

if( !function_exists('gb_sanitize_redirect') ):
/**
 * Sanitizes a URL for use in a redirect.
 *
 * @since	2.2.3
 *
 * @return string redirect-sanitized URL
 **/
function gb_sanitize_redirect($location) {
	$regex = '/
		(
			(?: [\xC2-\xDF][\x80-\xBF]        # double-byte sequences   110xxxxx 10xxxxxx
			|   \xE0[\xA0-\xBF][\x80-\xBF]    # triple-byte sequences   1110xxxx 10xxxxxx * 2
			|   [\xE1-\xEC][\x80-\xBF]{2}
			|   \xED[\x80-\x9F][\x80-\xBF]
			|   [\xEE-\xEF][\x80-\xBF]{2}
			|   \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
			|   [\xF1-\xF3][\x80-\xBF]{3}
			|   \xF4[\x80-\x8F][\x80-\xBF]{2}
		){1,50}                              # ...one or more times
		)/x';
	$location = preg_replace_callback($regex, '_gb_sanitize_utf8_in_redirect', $location);
	$location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()]|i', '', $location);
	$location = gb_kses_no_null($location);

	// remove %0d and %0a from location
	$strip = array('%0d', '%0a', '%0D', '%0A');
	$location = _deep_replace($strip, $location);
	return $location;
}

/**
 * URL encode UTF-8 characters in a URL.
 *
 * @ignore
 * @since	2.2.3
 * @access private
 *
 * @see gb_sanitize_redirect()
 */
function _gb_sanitize_utf8_in_redirect($matches) {
	return urlencode($matches[0]);
}
endif;
