<?php

/**
 * @group formatting
 */
class Tests_Formatting_GBSpecialchars extends GB_UnitTestCase {
	function test_gb_specialchars_basics() {
		$html =  "&amp;&lt;hello world&gt;";
		$this->assertEquals( $html, _gb_specialchars( $html ) );

		$double = "&amp;amp;&amp;lt;hello world&amp;gt;";
		$this->assertEquals( $double, _gb_specialchars( $html, ENT_NOQUOTES, false, true ) );
	}

	function test_allowed_entity_names() {
		global $allowedentitynames;

		// Allowed entities should be unchanged
		foreach ( $allowedentitynames as $ent ) {
			$ent = '&' . $ent . ';';
			$this->assertEquals( $ent, _gb_specialchars( $ent ) );
		}
	}

	function test_not_allowed_entity_names() {
		$ents = array( 'iacut', 'aposs', 'pos', 'apo', 'apo?', 'apo.*', '.*apo.*', 'apos ', ' apos', ' apos ' );

		foreach ( $ents as $ent ) {
			$escaped = '&amp;' . $ent . ';';
			$ent = '&' . $ent . ';';
			$this->assertEquals( $escaped, _gb_specialchars( $ent ) );
		}
	}

	function test_optionally_escapes_quotes() {
		$source = "\"'hello!'\"";
		$this->assertEquals( '"&#039;hello!&#039;"', _gb_specialchars($source, 'single') );
		$this->assertEquals( "&quot;'hello!'&quot;", _gb_specialchars($source, 'double') );
		$this->assertEquals( '&quot;&#039;hello!&#039;&quot;', _gb_specialchars($source, true) );
		$this->assertEquals( $source, _gb_specialchars($source) );
	}
}
