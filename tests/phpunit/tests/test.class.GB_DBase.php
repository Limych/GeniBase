<?php

/**
 * @group	class.GB_DBase.php
 */
class Tests_GB_DBase extends GB_UnitTestCase {
	function test_escaping() {
		// Check for making table prefix
		$this->assertEquals("`" . DB_PREFIX . "table`", gbdb()->table_escape('table'));		
		$this->assertEquals("`" . DB_PREFIX . "``table2```", gbdb()->table_escape('```table2```'));		
		$this->assertEquals("`" . DB_PREFIX . "``table2`````", gbdb()->table_escape('```table2`````'));		
		
		// Check for table and field name escaping
		$this->assertEquals("`field``name`", gbdb()->field_escape("field`name"));
		
		// Check for data escaping
		// scalars:
		$this->assertEquals('"text"', gbdb()->data_escape('text'));
		$this->assertEquals('"text\\0\\n\\r\\\'\\"\\Ztext"', gbdb()->data_escape("text\0\n\r'\"\x1Atext"));
		$this->assertEquals(123, gbdb()->data_escape(123));
		$this->assertEquals('NULL', gbdb()->data_escape(NULL));
		// arrays:
		$this->assertEquals('1,"text",NULL', gbdb()->data_escape(array(1, 'text', NULL)));
		$this->assertEquals(array(1, '"text"', 'NULL'), gbdb()->data_escape(array(1, 'text', NULL), TRUE));
		
		// Check for making regex
		$this->assertEquals('[[:<:]](Е|Ё)(..){2}ч(..)к(..)+[[:>:]]', GB_DBase::make_regex('Ё??ч?к*'));
	}
	
	function test_substitutions() {
		$sub = array(
			'@table'	=> 'table',
			'#field'	=> 'field',
			'data'		=> 'data',
		);
		$this->assertEquals(
			'SELECT `field` FROM `' . DB_PREFIX . 'table_$1`, `' . DB_PREFIX . 'table` WHERE `field` = "data"',
			gbdb()->prepare_query('SELECT ?#field FROM ?_table_$1, ?@table WHERE ?#field = ?data', $sub)
		);
	}
}
