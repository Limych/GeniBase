<?php

/**
 * @group Cache
 */
class Tests_Object_Cache extends GB_UnitTestCase {
	function setUp() {
		parent::setUp();
		$this->init_cache();
	}

	function init_cache() {
		gb_cache_add_global_groups(array('global-cache-test', 'users', 'userlogins', 'usermeta', 'user_meta', 'site-transient', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss', 'global-posts', 'blog-id-cache'));
	}

	function test_miss() {
		$this->assertEquals(NULL, gb_cache_get(rand_str()));
	}

	function test_add_get() {
		$key = rand_str();
		$val = rand_str();

		gb_cache_add($key, $val);
		$this->assertEquals($val, gb_cache_get($key));
	}

	function test_add_get_0() {
		$key = rand_str();
		$val = 0;

		// you can store zero in the cache
		gb_cache_add($key, $val);
		$this->assertEquals($val, gb_cache_get($key));
	}

	function test_add_get_null() {
		$key = rand_str();
		$val = null;

		$this->assertTrue(gb_cache_add($key, $val));
		// null is converted to empty string
		$this->assertEquals('', gb_cache_get($key));
	}

	function test_add() {
		$key = rand_str();
		$val1 = rand_str();
		$val2 = rand_str();

		// add $key to the cache
		$this->assertTrue(gb_cache_add($key, $val1));
		$this->assertEquals($val1, gb_cache_get($key));
		// $key is in the cache, so reject new calls to add()
		$this->assertFalse(gb_cache_add($key, $val2));
		$this->assertEquals($val1, gb_cache_get($key));
	}

	function test_replace() {
		$key = rand_str();
		$val = rand_str();
		$val2 = rand_str();

		// memcached rejects replace() if the key does not exist
		$this->assertFalse(gb_cache_replace($key, $val));
		$this->assertFalse(gb_cache_get($key));
		$this->assertTrue(gb_cache_add($key, $val));
		$this->assertEquals($val, gb_cache_get($key));
		$this->assertTrue(gb_cache_replace($key, $val2));
		$this->assertEquals($val2, gb_cache_get($key));
	}

	function test_set() {
		$key = rand_str();
		$val1 = rand_str();
		$val2 = rand_str();

		// memcached accepts set() if the key does not exist
		$this->assertTrue(gb_cache_set($key, $val1));
		$this->assertEquals($val1, gb_cache_get($key));
		// Second set() with same key should be allowed
		$this->assertTrue(gb_cache_set($key, $val2));
		$this->assertEquals($val2, gb_cache_get($key));
	}

	function test_flush() {
		global $_gb_using_ext_object_cache;

		if( $_gb_using_ext_object_cache )
			return;

		$key = rand_str();
		$val = rand_str();

		gb_cache_add($key, $val);
		// item is visible to both cache objects
		$this->assertEquals($val, gb_cache_get($key));
		gb_cache_flush();
		// If there is no value get returns false.
		$this->assertFalse(gb_cache_get($key));
	}

	// Make sure objects are cloned going to and from the cache
	function test_object_refs() {
		$key = rand_str();
		$object_a = new stdClass;
		$object_a->foo = 'alpha';
		gb_cache_set( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b = gb_cache_get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );

		$key = rand_str();
		$object_a = new stdClass;
		$object_a->foo = 'alpha';
		gb_cache_add( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b = gb_cache_get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );
	}

	function test_incr() {
		$key = rand_str();

		$this->assertFalse( gb_cache_incr( $key ) );

		gb_cache_set( $key, 0 );
		gb_cache_incr( $key );
		$this->assertEquals( 1, gb_cache_get( $key ) );

		gb_cache_incr( $key, 2 );
		$this->assertEquals( 3, gb_cache_get( $key ) );
	}

	function test_decr() {
		$key = rand_str();

		$this->assertFalse( gb_cache_decr( $key ) );

		gb_cache_set( $key, 0 );
		gb_cache_decr( $key );
		$this->assertEquals( 0, gb_cache_get( $key ) );

		gb_cache_set( $key, 3 );
		gb_cache_decr( $key );
		$this->assertEquals( 2, gb_cache_get( $key ) );

		gb_cache_decr( $key, 2 );
		$this->assertEquals( 0, gb_cache_get( $key ) );
	}

	function test_delete() {
		$key = rand_str();
		$val = rand_str();

		// Verify set
		$this->assertTrue( gb_cache_set( $key, $val ) );
		$this->assertEquals( $val, gb_cache_get( $key ) );

		// Verify successful delete
		$this->assertTrue( gb_cache_delete( $key ) );
		$this->assertFalse( gb_cache_get( $key ) );

		$this->assertFalse( gb_cache_delete( $key, '') );
	}
}
