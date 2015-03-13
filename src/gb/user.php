<?php
/**
 * GeniBase User API
 *
 * @package GeniBase
 * @subpackage Users
 * 
 * @since	2.1.1
 * 
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

/**
 * Get current user ID.
 * 
 * @since	2.1.1
 *
 * @param boolean $renew_cookie	True to renew cookie with user ID.
 * @return string	Current user ID.
 */
function gb_userid($renew_cookie = false){
	if( !empty($_COOKIE[GB_COOKIE_USERID]) )
		$userid = $_COOKIE[GB_COOKIE_USERID];
	else{
		$salt = '';
		if( isset($_SERVER['HTTP_USER_AGENT']) )	$salt .= $_SERVER['HTTP_USER_AGENT'];
		if( isset($_SERVER['HTTP_REFERER']) )		$salt .= $_SERVER['HTTP_REFERER'];
		if( isset($_SERVER['REMOTE_ADDR']) )		$salt .= $_SERVER['REMOTE_ADDR'];
		$salt = md5($salt);
		$userid = $salt . '-' . (12345678 + (int) date('Ymd')) . '-' . (123456 + (int) date('His'));
		$renew_cookie = true;

		/**
		 * Filter just generated user ID.
		 *
		 * @since	2.1.1
		 *
		 * @param string $userid Just generated user ID
		 */
		$userid = apply_filters('gb_userid', $userid);
	}

	if( $renew_cookie && $userid ){
		// Set a cookie with new user ID
		$secure = ( 'https' === parse_url(site_url(), PHP_URL_SCHEME) && 'https' === parse_url(home_url(), PHP_URL_SCHEME) );
		@setcookie(GB_COOKIE_USERID, $userid, time() + YEAR_IN_SECONDS, GB_COOKIE_PATH, GB_COOKIE_DOMAIN, $secure);
	}

	return $userid;
}
