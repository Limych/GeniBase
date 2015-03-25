<?php

/**
 * Test GB_Hooks::do_action() and related functions
 *
 * @group hooks
 */
class Tests_Actions extends GB_UnitTestCase {

	function test_simple_action() {
		$a = new MockAction();
		$tag = 'test_action';

		GB_Hooks::add_action($tag, array(&$a, 'action'));
		GB_Hooks::do_action($tag);

		// only one event occurred for the hook, with empty args
		$this->assertEquals(1, $a->get_call_count());
		// only our hook was called
		$this->assertEquals(array($tag), $a->get_tags());

		$argsvar = $a->get_args();
		$args = array_pop( $argsvar );
		$this->assertEquals(array(''), $args);
	}

	function test_remove_action() {
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action($tag, array(&$a, 'action'));
		GB_Hooks::do_action($tag);

		// make sure our hook was called correctly
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

		// now remove the action, do it again, and make sure it's not called this time
		GB_Hooks::remove_action($tag, array(&$a, 'action'));
		GB_Hooks::do_action($tag);
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

	}

	function test_has_action() {
		$tag = rand_str();
		$func = rand_str();

		$this->assertFalse( GB_Hooks::has_action($tag, $func) );
		$this->assertFalse( GB_Hooks::has_action($tag) );
		GB_Hooks::add_action($tag, $func);
		$this->assertEquals( 10, GB_Hooks::has_action($tag, $func) );
		$this->assertTrue( GB_Hooks::has_action($tag) );
		GB_Hooks::remove_action($tag, $func);
		$this->assertFalse( GB_Hooks::has_action($tag, $func) );
		$this->assertFalse( GB_Hooks::has_action($tag) );
	}

	// one tag with multiple actions
	function test_multiple_actions() {
		$a1 = new MockAction();
		$a2 = new MockAction();
		$tag = rand_str();

		// add both actions to the hook
		GB_Hooks::add_action($tag, array(&$a1, 'action'));
		GB_Hooks::add_action($tag, array(&$a2, 'action'));

		GB_Hooks::do_action($tag);

		// both actions called once each
		$this->assertEquals(1, $a1->get_call_count());
		$this->assertEquals(1, $a2->get_call_count());
	}

	function test_action_args_1() {
		$a = new MockAction();
		$tag = rand_str();
		$val = rand_str();

		GB_Hooks::add_action($tag, array(&$a, 'action'));
		// call the action with a single argument
		GB_Hooks::do_action($tag, $val);

		$call_count = $a->get_call_count();
		$this->assertEquals(1, $call_count);
		$argsvar = $a->get_args();
		$this->assertEquals( array( $val ), array_pop( $argsvar ) );
	}

	function test_action_args_2() {
		$a1 = new MockAction();
		$a2 = new MockAction();
		$tag = rand_str();
		$val1 = rand_str();
		$val2 = rand_str();

		// a1 accepts two arguments, a2 doesn't
		GB_Hooks::add_action($tag, array(&$a1, 'action'), 10, 2);
		GB_Hooks::add_action($tag, array(&$a2, 'action'));
		// call the action with two arguments
		GB_Hooks::do_action($tag, $val1, $val2);

		$call_count = $a1->get_call_count();
		// a1 should be called with both args
		$this->assertEquals(1, $call_count);
		$argsvar1 = $a1->get_args();
		$this->assertEquals( array( $val1, $val2 ), array_pop( $argsvar1 ) );

		// a2 should be called with one only
		$this->assertEquals(1, $a2->get_call_count());
		$argsvar2 = $a2->get_args();
		$this->assertEquals( array( $val1 ), array_pop( $argsvar2 ) );
	}

	function test_action_priority() {
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action($tag, array(&$a, 'action'), 10);
		GB_Hooks::add_action($tag, array(&$a, 'action2'), 9);
		GB_Hooks::do_action($tag);

		// two events, one per action
		$this->assertEquals(2, $a->get_call_count());

		$expected = array (
			// action2 is called first because it has priority 9
			array (
				'action' => 'action2',
				'tag' => $tag,
				'args' => array('')
			),
			// action 1 is called second
			array (
				'action' => 'action',
				'tag' => $tag,
				'args' => array('')
			),
		);

		$this->assertEquals($expected, $a->get_events());
	}

	function test_did_action() {
		$tag1 = rand_str();
		$tag2 = rand_str();

		// do action tag1 but not tag2
		GB_Hooks::do_action($tag1);
		$this->assertEquals(1, GB_Hooks::did_action($tag1));
		$this->assertEquals(0, GB_Hooks::did_action($tag2));

		// do action tag2 a random number of times
		$count = rand(0, 10);
		for ($i=0; $i<$count; $i++)
			GB_Hooks::do_action($tag2);

		// tag1's count hasn't changed, tag2 should be correct
		$this->assertEquals(1, GB_Hooks::did_action($tag1));
		$this->assertEquals($count, GB_Hooks::did_action($tag2));

	}

	function test_all_action() {
		$a = new MockAction();
		$tag1 = rand_str();
		$tag2 = rand_str();

		// add an 'all' action
		GB_Hooks::add_action('all', array(&$a, 'action'));
		$this->assertEquals(10, GB_Hooks::has_filter('all', array(&$a, 'action')));
		// do some actions
		GB_Hooks::do_action($tag1);
		GB_Hooks::do_action($tag2);
		GB_Hooks::do_action($tag1);
		GB_Hooks::do_action($tag1);

		// our action should have been called once for each tag
		$this->assertEquals(4, $a->get_call_count());
		// only our hook was called
		$this->assertEquals(array($tag1, $tag2, $tag1, $tag1), $a->get_tags());

		GB_Hooks::remove_action('all', array(&$a, 'action'));
		$this->assertFalse(GB_Hooks::has_filter('all', array(&$a, 'action')));

	}

	function test_remove_all_action() {
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action('all', array(&$a, 'action'));
		$this->assertEquals(10, GB_Hooks::has_filter('all', array(&$a, 'action')));
		GB_Hooks::do_action($tag);

		// make sure our hook was called correctly
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());

		// now remove the action, do it again, and make sure it's not called this time
		GB_Hooks::remove_action('all', array(&$a, 'action'));
		$this->assertFalse(GB_Hooks::has_filter('all', array(&$a, 'action')));
		GB_Hooks::do_action($tag);
		$this->assertEquals(1, $a->get_call_count());
		$this->assertEquals(array($tag), $a->get_tags());
	}

	function test_action_ref_array() {
		$obj = new stdClass();
		$a = new MockAction();
		$tag = rand_str();

		GB_Hooks::add_action($tag, array(&$a, 'action'));

		GB_Hooks::do_action_ref_array($tag, array(&$obj));

		$args = $a->get_args();
		$this->assertSame($args[0][0], $obj);
		// just in case we don't trust assertSame
		$obj->foo = true;
		$this->assertFalse( empty($args[0][0]->foo) );
	}

	function test_action_keyed_array() {
		$a = new MockAction();

		$tag = rand_str();

		GB_Hooks::add_action($tag, array(&$a, 'action'));

		$context = array( rand_str() => rand_str() );
		GB_Hooks::do_action($tag, $context);

		$args = $a->get_args();
		$this->assertSame($args[0][0], $context);

		$context2 = array( rand_str() => rand_str(), rand_str() => rand_str() );
		GB_Hooks::do_action($tag, $context2);

		$args = $a->get_args();
		$this->assertSame($args[1][0], $context2);

	}

	function test_action_self_removal() {
		GB_Hooks::add_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
		GB_Hooks::do_action( 'test_action_self_removal' );
		$this->assertEquals( 1, GB_Hooks::did_action( 'test_action_self_removal' ) );
	}

	function action_self_removal() {
		GB_Hooks::remove_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
	}

	/**
	 * Make sure GB_Hooks::current_action() behaves as GB_Hooks::current_filter()
	 */
	function test_current_action() {
		GB_Hooks::$current_filter[] = 'first';
		GB_Hooks::$current_filter[] = 'second'; // Let's say a second action was invoked.

		$this->assertEquals( 'second', GB_Hooks::current_action() );
	}

	function test_doing_filter() {
		GB_Hooks::$current_filter = array(); // Set to an empty array first

		$this->assertFalse( GB_Hooks::doing_filter() ); // No filter is passed in, and no filter is being processed
		$this->assertFalse( GB_Hooks::doing_filter( 'testing' ) ); // Filter is passed in but not being processed

		GB_Hooks::$current_filter[] = 'testing';

		$this->assertTrue( GB_Hooks::doing_filter() ); // No action is passed in, and a filter is being processed
		$this->assertTrue( GB_Hooks::doing_filter( 'testing') ); // Filter is passed in and is being processed
		$this->assertFalse( GB_Hooks::doing_filter( 'something_else' ) ); // Filter is passed in but not being processed

		GB_Hooks::$current_filter = array();
	}

	function test_doing_action() {
		GB_Hooks::$current_filter = array(); // Set to an empty array first

		$this->assertFalse( GB_Hooks::doing_action() ); // No action is passed in, and no filter is being processed
		$this->assertFalse( GB_Hooks::doing_action( 'testing' ) ); // Action is passed in but not being processed

		GB_Hooks::$current_filter[] = 'testing';

		$this->assertTrue( GB_Hooks::doing_action() ); // No action is passed in, and a filter is being processed
		$this->assertTrue( GB_Hooks::doing_action( 'testing') ); // Action is passed in and is being processed
		$this->assertFalse( GB_Hooks::doing_action( 'something_else' ) ); // Action is passed in but not being processed

		GB_Hooks::$current_filter = array();
	}

	function test_doing_filter_real() {
		$this->assertFalse( GB_Hooks::doing_filter() ); // No filter is passed in, and no filter is being processed
		$this->assertFalse( GB_Hooks::doing_filter( 'testing' ) ); // Filter is passed in but not being processed

		GB_Hooks::add_filter( 'testing', array( $this, 'apply_testing_filter' ) );
		$this->assertTrue( GB_Hooks::has_action( 'testing' ) );
		$this->assertEquals( 10, GB_Hooks::has_action( 'testing', array( $this, 'apply_testing_filter' ) ) );

		GB_Hooks::apply_filters( 'testing', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_filter );

		$this->assertFalse( GB_Hooks::doing_filter() ); // No longer doing any filters
		$this->assertFalse( GB_Hooks::doing_filter( 'testing' ) ); // No longer doing this filter
	}

	function apply_testing_filter() {
		$this->apply_testing_filter = true;

		$this->assertTrue( GB_Hooks::doing_filter() );
		$this->assertTrue( GB_Hooks::doing_filter( 'testing' ) );
		$this->assertFalse( GB_Hooks::doing_filter( 'something_else' ) );
		$this->assertFalse( GB_Hooks::doing_filter( 'testing_nested' ) );

		GB_Hooks::add_filter( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) );
		$this->assertTrue( GB_Hooks::has_action( 'testing_nested' ) );
		$this->assertEquals( 10, GB_Hooks::has_action( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) ) );

		GB_Hooks::apply_filters( 'testing_nested', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_nested_filter );

		$this->assertFalse( GB_Hooks::doing_filter( 'testing_nested' ) );
		$this->assertFalse( GB_Hooks::doing_filter( 'testing_nested' ) );
	}

	function apply_testing_nested_filter() {
		$this->apply_testing_nested_filter = true;
		$this->assertTrue( GB_Hooks::doing_filter() );
		$this->assertTrue( GB_Hooks::doing_filter( 'testing' ) );
		$this->assertTrue( GB_Hooks::doing_filter( 'testing_nested' ) );
		$this->assertFalse( GB_Hooks::doing_filter( 'something_else' ) );
	}
}
