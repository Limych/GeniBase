<?php
/**
 * GeniBase API
 *
 * @package GeniBase
 *
 * @since	2.3.0
 *
 * @copyright	Copyright © 2016, Andrey Khrolenok (andrey@khrolenok.ru)
 */



/**
 * GeniBase class.
 *
 * @since 2.3.0
 * @package GeniBase
 */
class GB {

	/**
	 * The user.
	 *
	 * @since 2.3.0
	 * @access private
	 * @var GB_User
	 */
	static $user;

	/**
	 * Sets up object properties.
	 */
	static function init() {
		// Get/Generate user hash
		GB_User::get_hash(0 == rand(0, 99));	// Renew cookie every 100 launches
		
		gb_get_current_user();
	}
}
