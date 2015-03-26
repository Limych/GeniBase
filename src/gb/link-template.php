<?php
/**
 * GeniBase Link Template Functions
 *
 * @package GeniBase
 * @subpackage Template
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © WordPress Team
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Retrieve the home url for the current site.
 *
 * Returns the 'home_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since	2.1.1
 *
 * @param string $path		Optional. Path relative to the home url.
 * @param string $scheme	Optional. Scheme to give the home url context. See set_url_scheme().
 * @return string	Home url link with optional path appended.
 */
function home_url($path = '', $scheme = null){
	return get_site_url($path, $scheme);
}

/**
 * Retrieve the site url for the current site.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since	2.0.1
 *
 * @param string $path Optional. Path relative to the site url.
 * @param string $scheme Optional. Scheme to give the site url context. See set_url_scheme().
 * @return string Site url link with optional path appended.
 */
function site_url($path = '', $scheme = null){
	return get_site_url($path, $scheme);
}

/**
 * Retrieve the site url for a given site.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * {@see is_ssl()} and 'http' otherwise. If `$scheme` is 'http' or 'https',
 * `is_ssl()` is overridden.
 *
 * @since	2.0.1
 *
 * @param string $path    Optional. Path relative to the site url. Default empty.
 * @param string $scheme  Optional. Scheme to give the site url context. Accepts
 *                        'http', 'https', 'login', 'login_post', 'admin', or
 *                        'relative'. Default null.
 * @return string Site url link with optional path appended.
 */
function get_site_url($path = '', $scheme = null) {
	$url = BASE_URL;	// TODO: options
// 	$url = get_option( 'siteurl' );

	$url = set_url_scheme( $url, $scheme );

	if( $path && is_string( $path ) )
		$url .= '/' . ltrim( $path, '/' );

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter the site URL.
		 *
		 * @since	2.1.1
		 *
		 * @param string      $url     The complete site URL including scheme and path.
		 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
		 * @param string|null $scheme  Scheme to give the site URL context. Accepts 'http', 'https', 'login',
		 *                             'login_post', 'admin', 'relative' or null.
		*/
		$url = GB_Hooks::apply_filters('site_url', $url, $path, $scheme);
	}

	return $url;
}

/**
 * Retrieve the url to the admin area for the current site.
 *
 * @since	2.0.1
 *
 * @param string $path Optional path relative to the admin url.
 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin url link with optional path appended.
 */
function admin_url( $path = '', $scheme = 'admin' ) {
	return get_admin_url($path, $scheme);
}

/**
 * Retrieves the url to the admin area for a given site.
 *
 * @since	2.0.1
 *
 * @param string $path    Optional. Path relative to the admin url. Default empty.
 * @param string $scheme  Optional. The scheme to use. Accepts 'http' or 'https',
 *                        to force those schemes. Default 'admin', which obeys
 *                        {@see force_ssl_admin()} and {@see is_ssl()}.
 * @return string Admin url link with optional path appended.
 */
function get_admin_url($path = '', $scheme = 'admin') {
	$url = get_site_url('gb-admin/', $scheme);

	if( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter the admin area URL.
		 *
		 * @since	2.1.1
		 *
		 * @param string   $url     The complete admin area URL including scheme and path.
		 * @param string   $path    Path relative to the admin area URL. Blank string if no path is specified.
		*/
		$url = GB_Hooks::apply_filters('admin_url', $url, $path);
	}
	return $url;
}

/**
 * Set the scheme for a URL
 *
 * @since	2.0.1
 *
 * @param string $url Absolute url that includes a scheme
 * @param string $scheme Optional. Scheme to give $url. Currently 'http', 'https', 'login', 'login_post', 'admin', or 'relative'.
 * @return string $url URL with chosen scheme.
 */
function set_url_scheme( $url, $scheme = null ) {
	$orig_scheme = $scheme;

	if( !$scheme ) {
		$scheme = is_ssl() ? 'https' : 'http';
	}elseif( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
		$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
	}elseif( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
		$scheme = is_ssl() ? 'https' : 'http';
	}

	$url = trim( $url );
	if( substr( $url, 0, 2 ) === '//' )
		$url = 'http:' . $url;

	if( 'relative' == $scheme ) {
		$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
		if( $url !== '' && $url[0] === '/' )
			$url = '/' . ltrim($url , "/ \t\n\r\0\x0B" );
	}else{
		$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
	}

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter the resulting URL after setting the scheme.
		 *
		 * @since	2.1.1
		 *
		 * @param string $url         The complete URL including scheme and path.
		 * @param string $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
		 * @param string $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login',
		 *                            'login_post', 'admin', 'rpc', or 'relative'.
		 */
		$url = GB_Hooks::apply_filters('set_url_scheme', $url, $scheme, $orig_scheme);
	}

	return $url;
}

/**
 * Output rel=canonical for current page.
 *
 * @since	2.1.1
 */
function rel_canonical() {
	$link = get_site_url($_SERVER['REQUEST_URI']);

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter rel=canonical link.
		 *
		 * @since	2.1.1
		 *
		 * @param string $link Full URL of rel=canonical link
		 */
		$link = GB_Hooks::apply_filters('rel_canonical', $link);
	}
	
	if(!empty($link))
		echo "<link rel='canonical' href='$link' />\n";
}

/**
 * Retrieve links for page numbers.
 *
 * @since	2.2.3
 *
 * @param int	$pagenum Optional. Page number.
 * @param bool	$escape Optional. Whether to escape the URL for display, with esc_url().
 * 				Defaults to true. Otherwise, prepares the URL with esc_url_raw().
 * @return string	The link URL for the given page number.
 */
function get_pagenum_link($pagenum = 1, $escape = true) {
	// TODO: rewrite
// 	global $gb_rewrite;

	$pagenum = (int) $pagenum;

	$request = remove_query_arg('pg');

	$home_root = parse_url(home_url());
	$home_root = ( isset($home_root['path']) ) ? $home_root['path'] : '';
	$home_root = preg_quote( $home_root, '|' );

	$request = preg_replace('|^'. $home_root . '|i', '', $request);
	$request = preg_replace('|^/+|', '', $request);

	// TODO: rewrite
// 	if ( !$gb_rewrite->using_permalinks() || is_admin() ) {
		$base = trailingslashit(home_url());

		if( $pagenum > 1 ){
			$result = add_query_arg('pg', $pagenum, $base . $request);
		}else{
			$result = $base . $request;
		}
	// TODO: rewrite
// 	} else {
// 		$qs_regex = '|\?.*?$|';
// 		preg_match( $qs_regex, $request, $qs_match );

// 		if ( !empty( $qs_match[0] ) ) {
// 			$query_string = $qs_match[0];
// 			$request = preg_replace( $qs_regex, '', $request );
// 		} else {
// 			$query_string = '';
// 		}

// 		$request = preg_replace( "|$gb_rewrite->pagination_base/\d+/?$|", '', $request);
// 		$request = preg_replace( '|^' . preg_quote( $gb_rewrite->index, '|' ) . '|i', '', $request);
// 		$request = ltrim($request, '/');

// 		$base = trailingslashit( get_bloginfo( 'url' ) );

// 		if ( $gb_rewrite->using_index_permalinks() && ( $pagenum > 1 || '' != $request ) )
// 			$base .= $gb_rewrite->index . '/';

// 		if ( $pagenum > 1 ) {
// 			$request = ( ( !empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $gb_rewrite->pagination_base . "/" . $pagenum, 'paged' );
// 		}

// 		$result = $base . $request . $query_string;
// 	}

	if( class_exists('GB_Hooks') ){
		/**
		 * Filter the page number link for the current request.
		 *
		 * @since 2.2.3
		 *
		 * @param string $result The page number link.
		 */
		$result = GB_Hooks::apply_filters('get_pagenum_link', $result);
	}

	if( $escape )
		return esc_url($result);
	else
		return esc_url_raw($result);
}
