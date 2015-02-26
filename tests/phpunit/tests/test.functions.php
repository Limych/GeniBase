<?php

/**
 * @group	functions.php
 */
class Tests_functions extends GB_UnitTestCase {
	function test_functions(){
		// absint()
		$this->assertEquals(1, absint(1));
		$this->assertEquals(1, absint(-1));
		$this->assertEquals(1, absint(-1.25));
		$this->assertEquals(1, absint(true));
	}
}
