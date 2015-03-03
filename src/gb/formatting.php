<?php
/**
 * Main GeniBase Formatting API.
 * 
 * Handles many functions for formatting output.
 * 
 * @package GeniBase
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress
 */

// Direct execution forbidden for this script
if(!defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Checks for invalid UTF8 in a string.
 *
 * @since 2.0.0
 *
 * @param string $string The text which is to be checked.
 * @param boolean $strip Optional. Whether to attempt to strip out invalid UTF8. Default is false.
 * @return string The checked text.
 */
function gb_check_invalid_utf8($string, $strip = false){
	$string = (string) $string;

	if(0 === strlen($string))	return '';

	// Check for support for utf8 in the installed PCRE library once and store the result in a static
	static $utf8_pcre;
	if(!isset($utf8_pcre))		$utf8_pcre = @preg_match('/^./u', 'a');
	// We can't demand utf8 in the PCRE installation, so just return the string in those cases
	if(!$utf8_pcre)		return $string;

	// preg_match fails when it encounters invalid UTF8 in $string
	if(1 === @preg_match('/^./us', $string))		return $string;

	// Attempt to strip the bad chars if requested (not recommended)
	if($strip && function_exists('iconv'))		return iconv('utf-8', 'utf-8', $string);

	return '';
}

/**
 * Converts a number of HTML entities into their special characters.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to decode " entities,
 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
 *
 * @since 2.0.0
 *
 * @param string $string The text which is to be decoded.
 * @param mixed $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old _gb_specialchars() values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @return string The decoded text without HTML entities.
 */
function gb_specialchars_decode($string, $quote_style = ENT_NOQUOTES){
	$string = (string) $string;

	if(0 === strlen($string))		return '';

	// Don't bother if there are no entities - saves a lot of processing
	if(strpos($string, '&') === false)		return $string;

	// Match the previous behaviour of _gb_specialchars() when the $quote_style is not an accepted value
	if(empty($quote_style))
		$quote_style = ENT_NOQUOTES;
	elseif(!in_array($quote_style, array(0, 2, 3, 'single', 'double'), true))
		$quote_style = ENT_QUOTES;

	// More complete than get_html_translation_table( HTML_SPECIALCHARS )
	$single = array('&#039;'  => '\'', '&#x27;' => '\'');
	$single_preg = array('/&#0*39;/'  => '&#039;', '/&#x0*27;/i' => '&#x27;');
	$double = array('&quot;' => '"', '&#034;'  => '"', '&#x22;' => '"');
	$double_preg = array('/&#0*34;/'  => '&#034;', '/&#x0*22;/i' => '&#x22;');
	$others = array('&lt;'   => '<', '&#060;'  => '<', '&gt;'   => '>', '&#062;'  => '>', '&amp;'  => '&', '&#038;'  => '&', '&#x26;' => '&');
	$others_preg = array('/&#0*60;/'  => '&#060;', '/&#0*62;/'  => '&#062;', '/&#0*38;/'  => '&#038;', '/&#x0*26;/i' => '&#x26;');

	if($quote_style === ENT_QUOTES){
		$translation = array_merge($single, $double, $others);
		$translation_preg = array_merge($single_preg, $double_preg, $others_preg);
	}elseif($quote_style === ENT_COMPAT || $quote_style === 'double'){
		$translation = array_merge($double, $others);
		$translation_preg = array_merge($double_preg, $others_preg);
	}elseif($quote_style === 'single'){
		$translation = array_merge($single, $others);
		$translation_preg = array_merge($single_preg, $others_preg);
	}elseif($quote_style === ENT_NOQUOTES){
		$translation = $others;
		$translation_preg = $others_preg;
	}

	// Remove zero padding on numeric entities
	$string = preg_replace(array_keys($translation_preg), array_values($translation_preg), $string);

	// Replace characters according to translation table
	return strtr($string, $translation);
}

/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to encode " to
 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string $string The text which is to be encoded.
 * @param int $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @param string $charset Optional. The character encoding of the string. Default is false.
 * @param boolean $double_encode Optional. Whether to encode existing html entities. Default is false.
 * @return string The encoded text with HTML entities.
 */
function _gb_specialchars($string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false){
	$string = (string) $string;

	if(0 === strlen($string))		return '';

	// Don't bother if there are no specialchars — saves some processing
	if(!preg_match('/[&<>"\']/', $string))		return $string;

	// Account for the previous behaviour of the function when the $quote_style is not an accepted value
	if(empty($quote_style))
		$quote_style = ENT_NOQUOTES;
	elseif(!in_array($quote_style, array(0, 2, 3, 'single', 'double'), TRUE))
		$quote_style = ENT_QUOTES;

	$charset = 'UTF-8';
	$_quote_style = $quote_style;

	if($quote_style === 'double'){
		$quote_style = ENT_COMPAT;
		$_quote_style = ENT_COMPAT;
	}elseif($quote_style === 'single')
		$quote_style = ENT_NOQUOTES;

	// Handle double encoding ourselves
	if($double_encode)
		$string = @htmlspecialchars($string, $quote_style, $charset);
	else {
		// Decode &amp; into &
		$string = gb_specialchars_decode($string, $_quote_style);

		// Guarantee every &entity; is valid or re-encode the &
		$string = gb_kses_normalize_entities($string);

		// Now re-encode everything except &entity;
		$string = preg_split('/(&#?x?[0-9a-z]+;)/i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ( $i = 0; $i < count($string); $i += 2 )
			$string[$i] = @htmlspecialchars($string[$i], $quote_style, $charset);

		$string = implode('', $string);
	}

	// Backwards compatibility
	if('single' === $_quote_style)
		$string = str_replace("'", '&#039;', $string);

	return $string;
}

/**
 * Escaping for HTML attributes.
 *
 * @since 2.0.0
 *
 * @param string $text
 * @return string
 */
function esc_attr($text) {
	$safe_text = gb_check_invalid_utf8($text);
	$safe_text = _gb_specialchars($safe_text, ENT_QUOTES);
	/**
	 * Filter a string cleaned and escaped for output in an HTML attribute.
	 *
	 * Text passed to esc_attr() is stripped of invalid or special characters
	 * before output.
	 *
	 * @since 2.0.0
	 *
	 * @param string $safe_text The text after it has been escaped.
	 * @param string $text      The text prior to being escaped.
	 */
// 	return apply_filters('escape_attribute', $safe_text, $text);
	return $safe_text;
}

/**
 * Escaping for HTML blocks.
 *
 * @since 2.0.0
 *
 * @param string $text
 * @return string
 */
function esc_html($text){
	$safe_text = gb_check_invalid_utf8($text);
	$safe_text = _gb_specialchars($safe_text, ENT_QUOTES);
	/**
	 * Filter a string cleaned and escaped for output in HTML.
	 *
	 * Text passed to esc_html() is stripped of invalid or special characters
	 * before output.
	 *
	 * @since 2.0.0
	 *
	 * @param string $safe_text The text after it has been escaped.
	 * @param string $text      The text prior to being escaped.
	 */
// 	return apply_filters('escape_html', $safe_text, $text);
	return $safe_text;
}

/**
 * Escape single quotes, htmlspecialchar " < > &, and fix line endings.
 *
 * Escapes text strings for echoing in JS. It is intended to be used for inline JS
 * (in a tag attribute, for example onclick="..."). Note that the strings have to
 * be in single quotes. The filter 'js_escape' is also applied here.
 *
 * @since 2.0.0
 *
 * @param string $text The text to be escaped.
 * @return string Escaped text.
 */
function esc_js( $text ) {
	$safe_text = gb_check_invalid_utf8( $text );
	$safe_text = _gb_specialchars( $safe_text, ENT_COMPAT );
	$safe_text = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes( $safe_text ) );
	$safe_text = str_replace( "\r", '', $safe_text );
	$safe_text = str_replace( "\n", '\\n', addslashes( $safe_text ) );
	/**
	 * Filter a string cleaned and escaped for output in JavaScript.
	 *
	 * Text passed to esc_js() is stripped of invalid or special characters,
	 * and properly slashed for output.
	 *
	 * @since 2.0.0
	 *
	 * @param string $safe_text The text after it has been escaped.
	 * @param string $text      The text prior to being escaped.
	 */
// 	return apply_filters( 'js_escape', $safe_text, $text );
	return $safe_text;
}

/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behaviour) ampersands are also replaced. The 'clean_url' filter
 * is applied to the returned cleaned URL.
 *
 * @since 2.0.0
 *
 * @param string $url The URL to be cleaned.
 * @param array $protocols Optional. An array of acceptable protocols.
 *		Defaults to 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn' if not set.
 * @param string $_context Private. Use esc_url_raw() for database usage.
 * @return string The cleaned $url after the 'clean_url' filter is applied.
 */
function esc_url( $url, $protocols = null, $_context = 'display' ) {
	$original_url = $url;

	if ( '' == $url )
		return $url;
	$url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
	$strip = array('%0d', '%0a', '%0D', '%0A');
	$url = _deep_replace($strip, $url);
	$url = str_replace(';//', '://', $url);
	/* If the URL doesn't appear to contain a scheme, we
	 * presume it needs http:// appended (unless a relative
	 * link starting with /, # or ? or a php file).
	*/
	if ( strpos($url, ':') === false && ! in_array( $url[0], array( '/', '#', '?' ) ) &&
			! preg_match('/^[a-z0-9-]+?\.php/i', $url) )
				$url = 'http://' . $url;

	// Replace ampersands and single quotes only when displaying.
	if ( 'display' == $_context ) {
		$url = gb_kses_normalize_entities( $url );
		$url = str_replace( '&amp;', '&#038;', $url );
		$url = str_replace( "'", '&#039;', $url );
	}

	if ( '/' === $url[0] ) {
		$good_protocol_url = $url;
	} else {
		if ( ! is_array( $protocols ) )
			$protocols = gb_allowed_protocols();
		$good_protocol_url = gb_kses_bad_protocol( $url, $protocols );
		if ( strtolower( $good_protocol_url ) != strtolower( $url ) )
			return '';
	}

	/**
	 * Filter a string cleaned and escaped for output as a URL.
	 *
	 * @since 2.3.0
	 *
	 * @param string $good_protocol_url The cleaned URL to be returned.
	 * @param string $original_url      The URL prior to cleaning.
	 * @param string $_context          If 'display', replace ampersands and single quotes only.
	 */
// 	return apply_filters( 'clean_url', $good_protocol_url, $original_url, $_context );
	return $good_protocol_url;
}

/**
 * Perform a deep string replace operation to ensure the values in $search are no longer present
 *
 * Repeats the replacement operation until it no longer replaces anything so as to remove "nested" values
 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
 * str_replace would return
 *
 * @since 2.0.0
 * @access private
 *
 * @param string|array $search The value being searched for, otherwise known as the needle. An array may be used to designate multiple needles.
 * @param string $subject The string being searched and replaced on, otherwise known as the haystack.
 * @return string The string with the replaced svalues.
 */
function _deep_replace( $search, $subject ) {
	$subject = (string) $subject;

	$count = 1;
	while ( $count ) {
		$subject = str_replace( $search, '', $subject, $count );
	}

	return $subject;
}

/**
 * Navigates through an array and encodes the values to be used in a URL.
 *
 * @since	2.0.0
 *
 * @param array|string $value The array or string to be encoded.
 * @return array|string $value The encoded array (or string from the callback).
 */
function urlencode_deep($value) {
	$value = is_array($value) ? array_map('urlencode_deep', $value) : urlencode($value);
	return $value;
}
