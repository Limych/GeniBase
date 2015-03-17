<?php

require_once dirname ( __FILE__ ) . '/trac.php';

class GB_UnitTestCase extends PHPUnit_Framework_TestCase {
	protected static $forced_tickets = array ();
	
	/**
	 * @var GB_UnitTest_Factory
	 */
	protected $factory;

	function setUp() {
		set_time_limit(0);
		
		gbdb()->suppress_errors = false;
		gbdb()->show_errors = true;
		ini_set('display_errors', 1);
// 		$this->factory = new GB_UnitTest_Factory();
		$this->clean_up_global_scope();
		$this->start_transaction();
// 		add_filter('gb_die_handler', array($this, 'get_gb_die_handler'));
	}

	function tearDown() {
// 		gbdb()->query('ROLLBACK');
// 		remove_filter('dbdelta_create_queries', array($this, '_create_temporary_tables'));
// 		remove_filter('query', array($this, '_drop_temporary_tables'));
// 		remove_filter('gb_die_handler', array($this, 'get_gb_die_handler'));
	}
	
	function clean_up_global_scope() {
		$_GET = $_POST = array();
	}
	
	function start_transaction() {
// 		gbdb()->query('SET autocommit = 0');
// 		gbdb()->query('START TRANSACTION');
// 		add_filter('dbdelta_create_queries', array($this, '_create_temporary_tables'));
// 		add_filter('query', array($this, '_drop_temporary_tables'));
	}
	
	function _create_temporary_tables($queries) {
		return str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $queries);
	}
	
	function _drop_temporary_tables($query) {
		if( 'DROP TABLE' === substr($query, 0, 10))
			return 'DROP TEMPORARY TABLE ' . substr($query, 10);
		return $query;
	}
	
	function get_gb_die_handler($handler) {
		return array($this, 'gb_die_handler');
	}
	
	function gb_die_handler($message) {
		throw new GBDieException($message);
	}

	function assertGBError( $actual, $message = '' ) {
		$this->assertInstanceOf( 'GB_Error', $actual, $message );
	}
	
	function assertEqualFields( $object, $fields ) {
		foreach( $fields as $field_name => $field_value ) {
			if( $object->$field_name != $field_value ) {
				$this->fail();
			}
		}
	}

	function assertEqualSets( $expected, $actual ) {
		$this->assertEquals( array(), array_diff( $expected, $actual ) );
		$this->assertEquals( array(), array_diff( $actual, $expected ) );
	}

	protected function checkRequirements() {
		parent::checkRequirements();
		if( GB_TESTS_FORCE_KNOWN_BUGS )
			return;
		$tickets = PHPUnit_Util_Test::getTickets( get_class( $this ), $this->getName( false ) );
		foreach ( $tickets as $ticket ) {
			if( is_numeric( $ticket ) ) {
				$this->knownGBBug( $ticket );
			}
		}
	}

	/**
	 * Skips the current test if there is an open GeniBase ticket with id $ticket_id
	 */
	function knownGBBug( $ticket_id ) {
		if( GB_TESTS_FORCE_KNOWN_BUGS || in_array( $ticket_id, self::$forced_tickets ) )
			return;
		if( !TracGitHubIssues::isTicketClosed( GB_GITHUB_REPOS, $ticket_id ) )
			$this->markTestSkipped( sprintf( 'GeniBase Issue #%d is not fixed', $ticket_id ) );
	}

	public static function forceTicket( $ticket ) {
		self::$forced_tickets[] = $ticket;
	}

	/**
	 * Returns the name of a temporary file.
	 */
	function temp_filename() {
		$tmp_dir = '';
		$dirs = array( 'TMP', 'TMPDIR', 'TEMP' );
		foreach( $dirs as $dir )
			if( isset( $_ENV[$dir] ) && !empty( $_ENV[$dir] ) ) {
				$tmp_dir = $dir;
				break;
			}
		if( empty( $tmp_dir ) ) {
			$tmp_dir = '/tmp';
		}
		$tmp_dir = realpath( $dir );
		return tempnam( $tmp_dir, 'gbunit' );
	}
}
