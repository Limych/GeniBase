<?php

/**
 * Test GB_Hooks::apply_filters() and related functions
 *
 * @group hooks
 */
class Tests_Filters extends GB_UnitTestCase {

	function test_simple_filter() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		GB_Hooks::add_filter($tag, array($a, 'filter'));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));

		// only one event occurred for the hook, with empty args
		$this->assertEquals(1, $a->get_call_count());
		// only our hook was called
		$this->assertEquals(array($tag), $a->get_tags());

		$argsvar = $a->get_args();
		$args = array_pop( $argsvar );
		$this->assertEquals(array($val), $args);
	}

	function test_remove_filter() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		GB_Hooks::add_filter($tag, array($a, 'filter'));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));

		// make sure our hook was called correctly
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

		// now remove the filter, do it again, and make sure it's not called this time
		GB_Hooks::remove_filter($tag, array($a, 'filter'));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

	}

	function test_has_filter() {
		$tag = rand_str();
		$func = rand_str();

		$this->assertFalse( GB_Hooks::has_filter($tag, $func) );
		$this->assertFalse( GB_Hooks::has_filter($tag) );
		GB_Hooks::add_filter($tag, $func);
		$this->assertEquals( 10, GB_Hooks::has_filter($tag, $func) );
		$this->assertTrue( GB_Hooks::has_filter($tag) );
		GB_Hooks::remove_filter($tag, $func);
		$this->assertFalse( GB_Hooks::has_filter($tag, $func) );
		$this->assertFalse( GB_Hooks::has_filter($tag) );
	}

	// one tag with multiple filters
	function test_multiple_filters() {
		$a1 = new MockAction();
		$a2 = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		// add both filters to the hook
		GB_Hooks::add_filter($tag, array($a1, 'filter'));
		GB_Hooks::add_filter($tag, array($a2, 'filter'));

		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));

		// both filters called once each
		$this->assertEquals(1, $a1->get_call_count());
		$this->assertEquals(1, $a2->get_call_count());
	}

	function test_filter_args_1() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();
		$arg1 = rand_str();

		GB_Hooks::add_filter($tag, array($a, 'filter'), 10, 2);
		// call the filter with a single argument
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val, $arg1));

		$this->assertEquals(1, $a->get_call_count());
		$argsvar = $a->get_args();
		$this->assertEquals( array( $val, $arg1 ), array_pop( $argsvar ) );
	}

	function test_filter_args_2() {
		$a1 = new MockAction();
		$a2 = new MockAction();
		$tag = rand_str();
		$val = rand_str();
		$arg1 = rand_str();
		$arg2 = rand_str();

		// a1 accepts two arguments, a2 doesn't
		GB_Hooks::add_filter($tag, array($a1, 'filter'), 10, 3);
		GB_Hooks::add_filter($tag, array($a2, 'filter'));
		// call the filter with two arguments
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val, $arg1, $arg2));

		// a1 should be called with both args
		$this->assertEquals(1, $a1->get_call_count());
		$argsvar1 = $a1->get_args();
		$this->assertEquals( array( $val, $arg1, $arg2 ), array_pop( $argsvar1 ) );

		// a2 should be called with one only
		$this->assertEquals(1, $a2->get_call_count());
		$argsvar2 = $a2->get_args();
		$this->assertEquals( array( $val ), array_pop( $argsvar2 ) );
	}

	function test_filter_priority() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		// make two filters with different priorities
		GB_Hooks::add_filter($tag, array($a, 'filter'), 10);
		GB_Hooks::add_filter($tag, array($a, 'filter2'), 9);
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));

		// there should be two events, one per filter
		$this->assertEquals(2, $a->get_call_count());

		$expected = array (
			// filter2 is called first because it has priority 9
			array (
				'filter' => 'filter2',
				'tag' => $tag,
				'args' => array($val)
			),
			// filter 1 is called second
			array (
				'filter' => 'filter',
				'tag' => $tag,
				'args' => array($val)
			),
		);

		$this->assertEquals($expected, $a->get_events());
	}

	function test_all_filter() {
		$a = new MockAction();
		$tag1 = rand_str();
		$tag2 = rand_str();
		$val = rand_str();

		// add an 'all' filter
		GB_Hooks::add_filter('all', array($a, 'filterall'));
		// do some filters
		$this->assertEquals($val, GB_Hooks::apply_filters($tag1, $val));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag2, $val));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag1, $val));
		$this->assertEquals($val, GB_Hooks::apply_filters($tag1, $val));

		// our filter should have been called once for each GB_Hooks::apply_filters call
		$this->assertEquals(4, $a->get_call_count());
		// the right hooks should have been called in order
		$this->assertEquals(array($tag1, $tag2, $tag1, $tag1), $a->get_tags());

		GB_Hooks::remove_filter('all', array($a, 'filterall'));
		$this->assertFalse( GB_Hooks::has_filter('all', array($a, 'filterall')) );

	}

	function test_remove_all_filter() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		GB_Hooks::add_filter('all', array($a, 'filterall'));
		$this->assertTrue( GB_Hooks::has_filter('all') );
		$this->assertEquals( 10, GB_Hooks::has_filter('all', array($a, 'filterall')) );
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));

		// make sure our hook was called correctly
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

		// now remove the filter, do it again, and make sure it's not called this time
		GB_Hooks::remove_filter('all', array($a, 'filterall'));
		$this->assertFalse( GB_Hooks::has_filter('all', array($a, 'filterall')) );
		$this->assertFalse( GB_Hooks::has_filter('all') );
		$this->assertEquals($val, GB_Hooks::apply_filters($tag, $val));
		// call cound should remain at 1
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());
	}

	function test_remove_all_filters_should_respect_the_priority_argument() {
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_filter($tag, array($a, 'filter'), 12);
		$this->assertTrue(GB_Hooks::has_filter($tag));

		// Should not be removed.
		GB_Hooks::remove_all_filters($tag, 11);
		$this->assertTrue(GB_Hooks::has_filter($tag));

		GB_Hooks::remove_all_filters($tag, 12);
		$this->assertFalse(GB_Hooks::has_filter($tag));
	}

	function test_filter_ref_array() {
		$obj = new stdClass();
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action($tag, array($a, 'filter'));

		GB_Hooks::apply_filters_ref_array($tag, array(&$obj));

		$args = $a->get_args();
		$this->assertSame($args[0][0], $obj);
		// just in case we don't trust assertSame
		$obj->foo = true;
		$this->assertFalse( empty($args[0][0]->foo) );
	}

	function test_filter_ref_array_result() {
		$obj = new stdClass();
		$a = new MockAction();
		$b = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action($tag, array($a, 'filter_append'), 10, 2);
		GB_Hooks::add_action($tag, array($b, 'filter_append'), 10, 2);

		$result = GB_Hooks::apply_filters_ref_array($tag, array('string', &$obj));

		$this->assertEquals($result, 'string_append_append');

		$args = $a->get_args();
		$this->assertSame($args[0][1], $obj);
		// just in case we don't trust assertSame
		$obj->foo = true;
		$this->assertFalse(empty($args[0][1]->foo));

		$args = $b->get_args();
		$this->assertSame($args[0][1], $obj);
		// just in case we don't trust assertSame
		$obj->foo = true;
		$this->assertFalse(empty($args[0][1]->foo));

	}

	function _self_removal($tag) {
		GB_Hooks::remove_action($tag, array($this, '_self_removal'), 10, 1);
		return $tag;
	}

	function test_has_filter_after_remove_all_filters() {
		$a = new MockAction();
		$tag = rand_str();

		// No priority
		GB_Hooks::add_filter($tag, array($a, 'filter'), 11);
		GB_Hooks::add_filter($tag, array($a, 'filter'), 12);
		$this->assertTrue(GB_Hooks::has_filter($tag));

		GB_Hooks::remove_all_filters($tag);
		$this->assertFalse(GB_Hooks::has_filter($tag));

		// Remove priorities one at a time
		GB_Hooks::add_filter($tag, array($a, 'filter'), 11);
		GB_Hooks::add_filter($tag, array($a, 'filter'), 12);
		$this->assertTrue(GB_Hooks::has_filter($tag));

		GB_Hooks::remove_all_filters($tag, 11);
		GB_Hooks::remove_all_filters($tag, 12);
		$this->assertFalse(GB_Hooks::has_filter($tag));
	}

	function test_has_filter_doesnt_reset_gb_filter() {
		GB_Hooks::add_action('action_test_has_filter_doesnt_reset_gb_filter', '__return_null', 1);
		GB_Hooks::add_action('action_test_has_filter_doesnt_reset_gb_filter', '__return_null', 2);
		GB_Hooks::add_action('action_test_has_filter_doesnt_reset_gb_filter', '__return_null', 3);
		GB_Hooks::add_action('action_test_has_filter_doesnt_reset_gb_filter', array($this, '_action_test_has_filter_doesnt_reset_gb_filter'), 4);

		GB_Hooks::do_action( 'action_test_has_filter_doesnt_reset_gb_filter' );
	}
	function _action_test_has_filter_doesnt_reset_gb_filter() {
		GB_Hooks::has_action('action_test_has_filter_doesnt_reset_gb_filter', '_function_that_doesnt_exist');

		$filters = current(GB_Hooks::$filters['action_test_has_filter_doesnt_reset_gb_filter']);
		$the_ = current($filters);
		$this->assertEquals($the_['function'], array($this, '_action_test_has_filter_doesnt_reset_gb_filter'));
	}
}
