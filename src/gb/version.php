<?php
/**
 * The GeniBase version string.
 */
define('GB_VERSION', '3.0.0');

/**
 * GeniBase DB revision, increments when changes are made to the GeniBase DB schema.
 */
define('GB_DB_VERSION', 12);

/**
 * Required PHP version.
 */
define('GB_PHP_REQUIRED',	'5.3.0');

/**
 * Required MySQL version.
 */
define('GB_MYSQL_REQUIRED',	'5.0');



// ** Don't edit below this line ******************************************************* //

// Add GeniBase version to headers
@header("X-Generator: GeniBase/" . GB_VERSION . "\n");
