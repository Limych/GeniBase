<?php

/**
 * Define base directory of site.
 *
 * @var string  Path to base directory of site.
 */
define('BASE_DIR', dirname(dirname(__FILE__))); // no trailing slash, full path only

/**
 * Allows for the administration module to be moved from the default location.
 *
 * @var string  Administration module location path.
 *
 * @since 3.0.0
 */
define('GB_ADMIN_DIR', dirname(__FILE__)); // no trailing slash, full path only

/**
 * Also define correct URL.
 * If not defined here, it will be automatically calculated later.
 *
 * @var string  Administration module location URL.
 *
 * @since 3.0.0
 */
// define('GB_ADMIN_URL', '/gb-admin'); // no trailing slash, full URL only
