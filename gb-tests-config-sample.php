<?php

/* Path to the GeniBase codebase you'd like to test. Add a backslash in the end. */
define( 'ABSPATH', dirname( __FILE__ ) . '/src/' );

// Force known bugs to be run.
// Tests with an associated Trac ticket that is still open are normally skipped.
// define( 'GB_TESTS_FORCE_KNOWN_BUGS', true );

// Test with GeniBase debug mode (default).
define( 'GB_DEBUG', true );

// ** MySQL settings ** //

// This configuration file will be used by the copy of GeniBase being tested.
// genibase/gb-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME',		'youremptytestdbnamehere' );
define( 'DB_USER',		'yourusernamehere' );
define( 'DB_PASSWORD',	'yourpasswordhere' );
define( 'DB_HOST',		'localhost' );
define( 'DB_CHARSET',	'utf8' );
define( 'DB_COLLATE',	'' );

$table_prefix  = 'gbtests_';   // Only numbers, letters, and underscores please!

define( 'GB_PHP_BINARY', 'php' );

define( 'GBLANG', '' );
