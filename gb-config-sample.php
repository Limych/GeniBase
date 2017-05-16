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
/**
 * MySQL hostname
 */
define('DB_HOST', 'localhost');

/**
 * MySQL database username
 */
define('DB_USER', 'your_username_here');

/**
 * MySQL database password
 */
define('DB_PASSWORD', 'your_password_here');

/**
 * The name of the database for WordPress
 */
define('DB_BASE', 'your_database_name_here');

/**
 * GeniBase Database Table prefix
 */
define('DB_PREFIX', 'gb_'); // Only numbers, letters, and underscores please!

/**
 * #@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 3.0.0
 */
define('AUTH_KEY', 'put your unique phrase here');
define('SECURE_AUTH_KEY', 'put your unique phrase here');
define('LOGGED_IN_KEY', 'put your unique phrase here');
define('NONCE_KEY', 'put your unique phrase here');
define('AUTH_SALT', 'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT', 'put your unique phrase here');
define('NONCE_SALT', 'put your unique phrase here');
/**#@-*/

/**
 * For developers: GeniBase debugging mode.
 *
 * Uncomment this to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use GB_DEBUG
 * in their development environments.
 */
// define( 'GB_DEBUG', true );
