<?php

/**
 * @group functions.php
 */
class Tests_Functions extends GB_UnitTestCase {
	function test_absint(){
		$this->assertEquals(1, absint(1));
		$this->assertEquals(1, absint(-1));
		$this->assertEquals(1, absint(-1.25));
		$this->assertEquals(1, absint(true));
	}

	function test_gb_parse_args_object() {
		$x = new MockClass;
		$x->_baba = 5;
		$x->yZ = "baba";
		$x->a = array(5, 111, 'x');
		$this->assertEquals(array('_baba' => 5, 'yZ' => 'baba', 'a' => array(5, 111, 'x')), gb_parse_args($x));
		$y = new MockClass;
		$this->assertEquals(array(), gb_parse_args($y));
	}

	function test_gb_parse_args_array()  {
		// arrays
		$a = array();
		$this->assertEquals(array(), gb_parse_args($a));
		$b = array('_baba' => 5, 'yZ' => 'baba', 'a' => array(5, 111, 'x'));
		$this->assertEquals(array('_baba' => 5, 'yZ' => 'baba', 'a' => array(5, 111, 'x')), gb_parse_args($b));
	}

	function test_gb_parse_args_defaults() {
		$x = new MockClass;
		$x->_baba = 5;
		$x->yZ = "baba";
		$x->a = array(5, 111, 'x');
		$d = array('pu' => 'bu');
		$this->assertEquals(array('pu' => 'bu', '_baba' => 5, 'yZ' => 'baba', 'a' => array(5, 111, 'x')), gb_parse_args($x, $d));
		$e = array('_baba' => 6);
		$this->assertEquals(array('_baba' => 5, 'yZ' => 'baba', 'a' => array(5, 111, 'x')), gb_parse_args($x, $e));
	}

	function test_gb_parse_args_other() {
		$b = true;
		gb_parse_str($b, $s);
		$this->assertEquals($s, gb_parse_args($b));
		$q = 'x=5&_baba=dudu&';
		gb_parse_str($q, $ss);
		$this->assertEquals($ss, gb_parse_args($q));
	}

	function test_gb_parse_args_boolean_strings() {
		$args = gb_parse_args( 'foo=false&bar=true' );
		$this->assertInternalType( 'string', $args['foo'] );
		$this->assertInternalType( 'string', $args['bar'] );
	}

	function test_is_serialized() {
		$cases = array(
			serialize(null),
			serialize(true),
			serialize(false),
			serialize(-25),
			serialize(25),
			serialize(1.1),
			serialize('this string will be serialized'),
			serialize("a\nb"),
			serialize(array()),
			serialize(array(1,1,2,3,5,8,13)),
			serialize( (object)array('test' => true, '3', 4) )
		);
		foreach ( $cases as $case )
			$this->assertTrue( is_serialized($case), "Serialized data: $case" );

		$not_serialized = array(
			'a string',
			'garbage:a:0:garbage;',
			's:4:test;'
		);
		foreach ( $not_serialized as $case )
			$this->assertFalse( is_serialized($case), "Test data: $case" );
	}
}
