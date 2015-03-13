<?php
/**
 * Installs GeniBase for running the tests and loads GeniBase and the test libraries
 */



$config_file_path = dirname( dirname( __FILE__ ) );
if( ! file_exists( $config_file_path . '/gb-tests-config.php' ) ) {
	// Support the config file from the root of the develop repository.
	if( basename( $config_file_path ) === 'phpunit' && basename( dirname( $config_file_path ) ) === 'tests' )
		$config_file_path = dirname( dirname( $config_file_path ) );
}
$config_file_path .= '/gb-tests-config.php';

/*
 * Globalize some GeniBase variables, because PHPUnit loads this file inside a function
 * See: https://github.com/sebastianbergmann/phpunit/issues/325
 */
global $gbdb, $current_site, $current_blog, $gb_rewrite, $shortcode_tags, $wp, $phpmailer;

if( !is_readable( $config_file_path ) ) {
	die( "ERROR: gb-tests-config.php is missing! Please use gb-tests-config-sample.php to create a config file.\n" );
}
define('GB_TESTING_MODE', TRUE);
require_once $config_file_path;

define( 'DIR_TESTDATA', dirname( __FILE__ ) . '/../data' );

if( ! defined( 'GB_TESTS_FORCE_KNOWN_BUGS' ) )
	define( 'GB_TESTS_FORCE_KNOWN_BUGS', false );

// Cron tries to make an HTTP request to the blog, which always fails, because tests are run in CLI mode only
define( 'DISABLE_GB_CRON', true );

define( 'GB_MEMORY_LIMIT', -1 );
define( 'GB_MAX_MEMORY_LIMIT', -1 );

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = GB_TESTS_DOMAIN;
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

// Override the PHPMailer
// require_once( dirname( __FILE__ ) . '/mock-mailer.php' );
// $phpmailer = new MockPHPMailer();

// system( GB_PHP_BINARY . ' ' . escapeshellarg( dirname( __FILE__ ) . '/install.php' ) . ' ' . escapeshellarg( $config_file_path ) );

/*require_once dirname( __FILE__ ) . '/functions.php';

$GLOBALS['_gb_die_disabled'] = false;
// Allow tests to override gb_die
tests_add_filter( 'gb_die_handler', '_gb_die_handler_filter' );

// Preset GeniBase options defined in bootstrap file.
// Used to activate themes, plugins, as well as  other settings.
if( isset($GLOBALS['gb_tests_options'])) {
	function gb_tests_options( $value ) {
		$key = substr( current_filter(), strlen( 'pre_option_' ) );
		return $GLOBALS['gb_tests_options'][$key];
	}

	foreach ( array_keys( $GLOBALS['gb_tests_options'] ) as $key ) {
		tests_add_filter( 'pre_option_'.$key, 'gb_tests_options' );
	}
}/**/

// Load GeniBase
require_once BASE_DIR . '/gb/gb.php';

// Delete any default posts & related data
// _delete_all_posts();

require dirname( __FILE__ ) . '/testcase.php';
// require dirname( __FILE__ ) . '/testcase-xmlrpc.php';
// require dirname( __FILE__ ) . '/testcase-ajax.php';
require dirname( __FILE__ ) . '/exceptions.php';
require dirname( __FILE__ ) . '/utils.php';

/**
 * A child class of the PHP test runner.
 *
 * Used to access the protected longOptions property, to parse the arguments
 * passed to the script.
 *
 * If it is determined that phpunit was called with a --group that corresponds
 * to an @ticket annotation (such as `phpunit --group 12345` for bugs marked
 * as #WP12345), then it is assumed that known bugs should not be skipped.
 *
 * If GB_TESTS_FORCE_KNOWN_BUGS is already set in gb-tests-config.php, then
 * how you call phpunit has no effect.
 */
class GB_PHPUnit_Util_Getopt extends PHPUnit_Util_Getopt {
	protected $longOptions = array(
	  'exclude-group=',
	  'group=',
	);
	function __construct( $argv ) {
		array_shift( $argv );
		$options = array();
		while ( list( $i, $arg ) = each( $argv ) ) {
			try {
				if( strlen( $arg ) > 1 && $arg[0] === '-' && $arg[1] === '-' ) {
					PHPUnit_Util_Getopt::parseLongOption( substr( $arg, 2 ), $this->longOptions, $options, $argv );
				}
			}
			catch ( PHPUnit_Framework_Exception $e ) {
				// Enforcing recognized arguments or correctly formed arguments is
				// not really the concern here.
				continue;
			}
		}

		$ajax_message = true;
		foreach ( $options as $option ) {
			switch ( $option[0] ) {
				case '--exclude-group' :
					$ajax_message = false;
					continue 2;
				case '--group' :
					$groups = explode( ',', $option[1] );
					foreach ( $groups as $group ) {
						if( is_numeric( $group ) || preg_match( '/^(UT|Plugin)\d+$/', $group ) ) {
							GB_UnitTestCase::forceTicket( $group );
						}
					}
					$ajax_message = ! in_array( 'ajax', $groups );
					continue 2;
			}
		}
		if( $ajax_message ) {
			echo "Not running ajax tests... To execute these, use --group ajax." . PHP_EOL;
		}
    }
}
new GB_PHPUnit_Util_Getopt( $_SERVER['argv'] );
