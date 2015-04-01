<?php

/**
 * @group options
 */
class Tests_Options extends GB_UnitTestCase {
	function __return_foo() {
		return 'foo';
	}

	function test_the_basics() {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		$section = rand_str();
		$key = rand_str();
		$key2 = rand_str();
		$value = rand_str();
		$value2 = rand_str();

		$this->assertFalse(GB_Options::get('doesnotexist', $section));
		$this->assertTrue(GB_Options::add($key, $value, $section));
		$this->assertEquals($value, GB_Options::get($key, $section));
		$this->assertFalse(GB_Options::add($key, $value, $section));  // Already exists
		$this->assertFalse(GB_Options::update($key, $value, $section));  // Value is the same
		$this->assertTrue(GB_Options::update($key, $value2, $section));
		$this->assertEquals($value2, GB_Options::get($key, $section));
		$this->assertFalse(GB_Options::add($key, $value, $section));
		$this->assertEquals($value2, GB_Options::get($key, $section));
		$this->assertTrue(GB_Options::delete($key, $section));
		$this->assertFalse(GB_Options::get($key, $section));
		$this->assertFalse(GB_Options::delete($key, $section));

		$this->assertTrue(GB_Options::update($key2, $value2, $section));
		$this->assertEquals($value2, GB_Options::get($key2, $section));
		$this->assertTrue(GB_Options::delete($key2, $section));
		$this->assertFalse(GB_Options::get($key2, $section));
	}

	function test_default_filter() {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		if( !class_exists('GB_Hooks') ){
			$this->markTestSkipped('The GB_Hooks is not available.');
			return;
		}

		$section = rand_str();
		$random = rand_str();
		$option_hash = GB_Options::build_hash('doesnotexist', $section);
		$filter = "default_option_{$option_hash}";

		$this->assertFalse(GB_Options::get('doesnotexist', $section));

		// Default filter overrides $default arg.
		GB_Hooks::add_filter($filter, array($this, '__return_foo'));
		$this->assertEquals('foo', GB_Options::get('doesnotexist', $section, 'bar'));

		// Remove the filter and the $default arg is honored.
		GB_Hooks::remove_filter($filter, array($this, '__return_foo'));
		$this->assertEquals('bar', GB_Options::get('doesnotexist', $section, 'bar'));

		// Once the option exists, the $default arg and the default filter are ignored.
		GB_Options::add('doesnotexist', $random, $section);
		$this->assertEquals($random, GB_Options::get('doesnotexist', $section, 'foo'));
		GB_Hooks::add_filter($filter, array($this, '__return_foo'));
		$this->assertEquals($random, GB_Options::get('doesnotexist', $section, 'foo'));
		GB_Hooks::remove_filter($filter, array($this, '__return_foo'));

		// Cleanup
		$this->assertTrue(GB_Options::delete('doesnotexist', $section));
		$this->assertFalse(GB_Options::get('doesnotexist', $section));
	}

	public function test_add_option_should_respect_default_option_filter() {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		$section = rand_str();
		$option_hash = GB_Options::build_hash('doesnotexist', $section);
		$filter = "default_option_{$option_hash}";

		GB_Hooks::add_filter($filter, array($this, '__return_foo'));
		$added = GB_Options::add('doesnotexist', 'bar', $section);
		GB_Hooks::remove_filter($filter, array($this, '__return_foo'));

		$this->assertTrue($added);
		$this->assertEquals('bar', GB_Options::get('doesnotexist', $section));
	}

	function test_serialized_data() {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		$section = rand_str();
		$key = rand_str();
		$value = array( 'foo' => true, 'bar' => true );

		$this->assertTrue(GB_Options::add( $key, $value, $section) );
		$this->assertEquals($value, GB_Options::get( $key, $section ) );

		$value = (object) $value;
		$this->assertTrue(GB_Options::update( $key, $value, $section ) );
		$this->assertEquals($value, GB_Options::get( $key, $section ) );
		$this->assertTrue(GB_Options::delete( $key, $section ) );
	}

	function test_bad_option_names() {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		$section = rand_str();

		foreach ( array( '', '0', ' ', 0, false, null ) as $empty ) {
			$this->assertFalse(GB_Options::get( $empty, $section ) );
			$this->assertFalse(GB_Options::add( $empty, '', $section ) );
			$this->assertFalse(GB_Options::update( $empty, '', $section ) );
			$this->assertFalse(GB_Options::delete( $empty, $section ) );
		}
	}

	function data_option_autoloading() {
		$section = rand_str();
		return array(
			array($section, 'autoload_yes',    'yes',   'yes'),
			array($section, 'autoload_true',   true,    'yes'),
			array($section, 'autoload_string', 'foo',   'yes'),
			array($section, 'autoload_int',    123456,  'yes'),
			array($section, 'autoload_array',  array(), 'yes'),
			array($section, 'autoload_no',     'no',    'no' ),
			array($section, 'autoload_false',  false,   'no' ),
		);
	}
	/**
	 * Options should be autoloaded unless they were added with "no" or `false`.
	 *
	 * @dataProvider data_option_autoloading
	 */
	function test_option_autoloading($section, $name, $autoload_value, $expected) {
		$this->markTestSkipped('Installer needed.');	// TODO: installer

		$added = GB_Options::add($name, 'Autoload test', $section, $autoload_value);
		$this->assertTrue($added);

		$actual = gbdb()->get_cell('SELECT autoload FROM ?_options WHERE section = ?section AND option_name = ?key LIMIT 1', array(
				'section'	=> $section,
				'key'		=> $name,
		));
		$this->assertEquals($expected, $actual['autoload']);
	}
}
