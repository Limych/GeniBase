<?php

/**
 * @group	class.GB_DBase.php
 */
class Tests_GB_DBase extends GB_UnitTestCase {
	function test_escaping() {
		// Check for unescaping
		// gbdb()->table_unescape are skipped — it's synonim for gbdb()->field_unescape
		$this->assertEquals('`field1`', gbdb()->field_unescape('```field1```'));
		// arrays:
		$this->assertEquals(array('field1', '`field2`'),
				gbdb()->field_unescape(array('`field1`', '```field2```')));

		// Check for adding table prefix
		$this->assertEquals('`' . DB_PREFIX . 'table1`', gbdb()->table_escape('table1'));		
		$this->assertEquals('`' . DB_PREFIX . '``table2```', gbdb()->table_escape('```table2```'));		
		$this->assertEquals('`' . DB_PREFIX . '``table3`````', gbdb()->table_escape('```table3`````'));
		// arrays:
		$this->assertEquals('`' . DB_PREFIX . 'table1`, `' . DB_PREFIX . 'table2`',
				gbdb()->table_escape(array('table1', 'table2')));
		$this->assertEquals(array('`' . DB_PREFIX . 'table1`', '`' . DB_PREFIX . 'table2`'),
				gbdb()->table_escape(array('table1', 'table2'), true));
		
		// Check for table and field name escaping
		$this->assertEquals('`field``name`', gbdb()->field_escape('field`name'));
		// arrays:
		$this->assertEquals('`field``name```, `field````2`',
				gbdb()->field_escape(array('field`name`', 'field``2')));
		$this->assertEquals(array('`field``name```', '`field````2`'),
				gbdb()->field_escape(array('field`name`', 'field``2'), true));
		
		// Check for data escaping
		// scalars:
		$this->assertEquals('"text"', gbdb()->data_escape('text'));
		$this->assertEquals('"text\\0\\n\\r\\\'\\"\\Ztext"', gbdb()->data_escape("text\0\n\r'\"\x1Atext"));
		$this->assertEquals(123, gbdb()->data_escape(123));
		$this->assertEquals('NULL', gbdb()->data_escape(NULL));
		// arrays:
		$this->assertEquals('1, "text", NULL', gbdb()->data_escape(array(1, 'text', NULL)));
		$this->assertEquals(array(1, '"text"', 'NULL'), gbdb()->data_escape(array(1, 'text', NULL), TRUE));
		
		// Check for making search conditions
		// make_regex()
		$this->assertEquals('[[:<:]](Е|Ё)(..){2}ч(..)к(..)+\\\\.\\\\+[[:>:]]', GB_DBase::make_regex('Ё??ч?к*.+', true));
		$this->assertEquals('(Е|Ё)(..){2}ч(..)к(..)+\\\\.\\\\+', GB_DBase::make_regex('Ё??ч?к*.+', false));
		// make_condition()
		$this->assertEquals('%Ё__ч_к_%\\_\\%%', GB_DBase::make_condition('Ё??ч?к*_%', true));
		$this->assertEquals('Ё__ч_к_%\\_\\%', GB_DBase::make_condition('Ё??ч?к*_%', false));
	}

	function test_substitutions() {
		$sub = array(
			'@table'	=> 'table',
			'#field'	=> 'field',
			'data'		=> 'data',
			'data2'		=> array('data2-1', 'data2-2', null, 4),
		);
		$this->assertEquals(
			'SELECT `field` FROM `' . DB_PREFIX . 'table_$1`, `' . DB_PREFIX . 'table` WHERE `field` = "data" OR `field` IN ("data2-1", "data2-2", NULL, 4)',
			gbdb()->prepare_query('SELECT ?#field FROM ?_table_$1, ?@table WHERE ?#field = ?data OR ?#field IN (?data2)', $sub)
		);
	}
}
