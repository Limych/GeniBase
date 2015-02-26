<?php

/**
 * @group	gb.php
 */
class Tests_GB extends GB_UnitTestCase {
	function test_common() {
		// get_request_attr()
		$_REQUEST['test'] = 'testvalue';
		$this->assertEquals($_REQUEST['test'], get_request_attr('test', 'default'));
		$this->assertEquals('default', get_request_attr('test2', 'default'));
	}
}
