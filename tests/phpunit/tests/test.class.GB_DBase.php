<?php

/**
 * @group	class.GB_DBase.php
 */
class Tests_GB_DBase extends GB_UnitTestCase {
	function test_common() {
		// Check for making table prefix
		$this->assertEquals("`" . DB_PREFIX . "table`", gbdb()->table_escape('table'));		
		
		// Check for table and field name escaping
		$this->assertEquals("`field``name`", gbdb()->field_escape("field`name"));
		
		// Check for data escaping
		// scalars:
		$this->assertEquals('"text"', gbdb()->data_escape('text'));
		$this->assertEquals("\"text\"", gbdb()->data_escape("text\0\n\r'\"\x1Atext"));
		$this->assertEquals(123, gbdb()->data_escape(123));
		$this->assertEquals('NULL', gbdb()->data_escape(NULL));
		// arrays:
		$this->assertEquals('1,"text",NULL', gbdb()->data_escape(array(1, 'text', NULL)));
		$this->assertEquals(array(1, '"text"', 'NULL'), gbdb()->data_escape(array(1, 'text', NULL), TRUE));
	}
}
