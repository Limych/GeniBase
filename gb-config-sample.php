<?php
/**
 * The base configurations of the GeniBase.
 *
 * This file has the following configurations: MySQL settings, Secret Keys,
 * and BASE_DIR. You can get the MySQL settings from your web host.
 *
 * This file is used by the gb-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "gb-config.php" and fill in the values.
 *
 * @package GeniBase
 */

// ** MySQL settings - You can get this info from your web host ** //
/** MySQL hostname */
define('DB_HOST',		'localhost');

/** MySQL database username */
define('DB_USER',		'your_username_here');

/** MySQL database password */
define('DB_PASSWORD',	'your_password_here');

/** The name of the database for WordPress */
define('DB_BASE',		'your_database_name_here');

/** GeniBase Database Table prefix. */
define('DB_PREFIX',		'gb_');	// Only numbers, letters, and underscores please!

/**
 * For developers: GeniBase debugging mode.
 *
 * Uncomment this to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use GB_DEBUG
 * in their development environments.
 */
// define('GB_DEBUG', true);



/* That's all, stop editing! ***************************************************/

/** Absolute path to the root directory of this site. */
if( !defined('BASE_DIR') )
	define('BASE_DIR', dirname(__FILE__));

/** Absolute path to the root directory of GeniBase core. */
if( !defined('GB_CORE_DIR') )
	define('GB_CORE_DIR', BASE_DIR . '/gb');

/** Load GeniBase. */
require_once(GB_CORE_DIR . '/gb.php');
