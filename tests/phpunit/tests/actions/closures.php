<?php

/**
 * Test GB_Hooks::do_action() and related functions
 *
 * @group actions
 */
class Tests_Actions_Closures extends GB_UnitTestCase {
	function test_action_closure() {
		$tag = 'test_action_closure';
		$closure = function($a, $b) { $GLOBALS[$a] = $b;};
		GB_Hooks::add_action($tag, $closure, 10, 2);

		$this->assertSame( 10, GB_Hooks::has_action($tag, $closure) );

		$context = array( rand_str(), rand_str() );
		GB_Hooks::do_action($tag, $context[0], $context[1]);

		$this->assertSame($GLOBALS[$context[0]], $context[1]);

		$tag2 = 'test_action_closure_2';
		$closure2 = function() { $GLOBALS['closure_no_args'] = true;};
		GB_Hooks::add_action($tag2, $closure2);

		$this->assertSame( 10, GB_Hooks::has_action($tag2, $closure2) );

		GB_Hooks::do_action($tag2);

		$this->assertTrue($GLOBALS['closure_no_args']);

		GB_Hooks::remove_action( $tag, $closure );
		GB_Hooks::remove_action( $tag2, $closure2 );
	}
}
