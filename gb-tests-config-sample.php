<?php

/* Path to the GeniBase codebase you'd like to test. Add a backslash in the end. */
define('BASE_DIR', dirname( __FILE__ ) . '/src');

// Force known bugs to be run.
// Tests with an associated Trac ticket that is still open are normally skipped.
// define('GB_TESTS_FORCE_KNOWN_BUGS', true);

// Test with GeniBase debug mode (default).
define('GB_DEBUG', true);

// ** MySQL settings ** //

// This configuration file will be used by the copy of GeniBase being tested.
// genibase/gb-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define('DB_HOST',		'localhost');				// URL MySQL-сервера
define('DB_USER',		'yourusernamehere');		// Имя пользователя
define('DB_PASSWORD',	'yourpasswordhere');		// Пароль
define('DB_BASE',		'youremptytestdbnamehere');	// Имя базы данных
define('DB_PREFIX',		'gbtests_');	// Префикс таблиц в базе. Only numbers, letters, and underscores please!

define('GB_GITHUB_REPOS',	'Limych/GeniBase');

define('GB_TESTS_DOMAIN',	'example.org');
define('GB_TESTS_EMAIL',	'admin@example.org');

define('GB_PHP_BINARY', 'php');

define('GB_LANG', '');
