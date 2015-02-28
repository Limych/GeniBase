<?php

/**
 * @group	gb.php
 */
class Tests_GB extends GB_UnitTestCase {
	function test_get_request_attr() {
		$_REQUEST['test'] = 'testvalue';

		$res = $_REQUEST['test'];
		$this->assertEquals($res, get_request_attr('test', 'default'));
		$res = 'default';
		$this->assertEquals($res, get_request_attr('test2', 'default'));
	}
}
