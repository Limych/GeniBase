<?php

/**
 * @group actions
 */
class Tests_Actions_Callbacks extends GB_UnitTestCase {
	function test_callback_representations() {
		$tag = __FUNCTION__;

		$this->assertFalse( GB_Hooks::has_action( $tag ) );

		GB_Hooks::add_action( $tag, array( 'Class', 'method' ) );

		$this->assertEquals( 10, GB_Hooks::has_action( $tag, array( 'Class', 'method' ) ) );

		$this->assertEquals( 10, GB_Hooks::has_action( $tag, 'Class::method' ) );
	}
}
