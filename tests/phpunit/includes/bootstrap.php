<?php
/**
 * Installs GeniBase for running the tests and loads GeniBase and the test libraries
 */


require_once 'PHPUnit/Autoload.php';

$config_file_path = dirname( __FILE__ ) . '/../gb-tests-config.php';
if (!is_readable($config_file_path)) {
	die( "ERROR: gb-tests-config.php is missing! Please use gb-tests-config-sample.php to create a config file.\n" );
}
require_once $config_file_path;

define('DIR_TESTDATA', dirname( __FILE__ ) . '/../data');

if ( ! defined( 'GB_TESTS_FORCE_KNOWN_BUGS' ) )
	define( 'GB_TESTS_FORCE_KNOWN_BUGS', false );

define( 'GB_MEMORY_LIMIT', -1 );
define( 'GB_MAX_MEMORY_LIMIT', -1 );

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = GB_TESTS_DOMAIN;
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

// require_once dirname( __FILE__ ) . '/functions.php';

// Preset GeniBase options defined in bootstrap file.
// Used to activate themes, plugins, as well as  other settings.
// if(isset($GLOBALS['gb_tests_options'])) {
// 	function gb_tests_options( $value ) {
// 		$key = substr( current_filter(), strlen( 'pre_option_' ) );
// 		return $GLOBALS['gb_tests_options'][$key];
// 	}

// 	foreach ( array_keys( $GLOBALS['gb_tests_options'] ) as $key ) {
// 		tests_add_filter( 'pre_option_'.$key, 'gb_tests_options' );
// 	}
// }

// Load GeniBase
require_once ABSPATH . '/gb/settings.php';

require dirname( __FILE__ ) . '/testcase.php';
// require dirname( __FILE__ ) . '/testcase-xmlrpc.php';
// require dirname( __FILE__ ) . '/testcase-ajax.php';
// require dirname( __FILE__ ) . '/exceptions.php';
// require dirname( __FILE__ ) . '/utils.php';

/**
 * A child class of the PHP test runner.
 *
 * Not actually used as a runner. Rather, used to access the protected
 * longOptions property, to parse the arguments passed to the script.
 *
 * If it is determined that phpunit was called with a --group that corresponds
 * to an @ticket annotation (such as `phpunit --group 12345` for bugs marked
 * as #WP12345), then it is assumed that known bugs should not be skipped.
 *
 * If GB_TESTS_FORCE_KNOWN_BUGS is already set in gb-tests-config.php, then
 * how you call phpunit has no effect.
 */
class GB_PHPUnit_TextUI_Command extends PHPUnit_TextUI_Command {
	function __construct( $argv ) {
		$options = PHPUnit_Util_Getopt::getopt(
			$argv,
			'd:c:hv',
			array_keys( $this->longOptions )
		);
		$ajax_message = true;
		foreach ( $options[0] as $option ) {
			switch ( $option[0] ) {
				case '--exclude-group' :
					$ajax_message = false;
					continue 2;
				case '--group' :
					$groups = explode( ',', $option[1] );
					foreach ( $groups as $group ) {
						if ( is_numeric( $group ) )
							GB_UnitTestCase::forceTicket( $group );
					}
					$ajax_message = ! in_array( 'ajax', $groups );
					continue 2;
			}
		}
		if ( $ajax_message )
			echo "Not running ajax tests... To execute these, use --group ajax." . PHP_EOL;
    }
}
new GB_PHPUnit_TextUI_Command( $_SERVER['argv'] );
