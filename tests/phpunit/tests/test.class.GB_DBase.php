<?php

/**
 * @group	class.GB_DBase.php
 */
class Tests_GB_DBase extends GB_UnitTestCase {
	function test_common() {
		// Check for table and field name escaping
		$this->assertEquals("`field``name`", GB_DBase::field_escape("field`name"));
		
		// Check for data escaping
		// scalars:
		$this->assertEquals('"text"', GB_DBase::data_escape('text'));
		$this->assertEquals("\"text\"", GB_DBase::data_escape("text\0\n\r'\"\x1Atext"));
		$this->assertEquals(123, GB_DBase::data_escape(123));
		$this->assertEquals('NULL', GB_DBase::data_escape(NULL));
		// arrays:
		$this->assertEquals('1,"text",NULL', GB_DBase::data_escape(array(1, 'text', NULL)));
		$this->assertEquals(array(1, '"text"', 'NULL'), GB_DBase::data_escape(array(1, 'text', NULL), TRUE));
	}
}
