<?php

// tests for link-template.php and related URL functions
/**
 * @group url
 */
class Tests_URL extends GB_UnitTestCase {
	var $_old_server;

	function setUp() {
		parent::setUp();
		$this->_old_server = $_SERVER;
		$GLOBALS['pagenow'] = '';
	}

	function tearDown() {
		$_SERVER = $this->_old_server;
		parent::tearDown();
	}

	function test_is_ssl_positive() {
		$_SERVER['HTTPS'] = 'on';
		$this->assertTrue( is_ssl() );

		$_SERVER['HTTPS'] = 'ON';
		$this->assertTrue( is_ssl() );

		$_SERVER['HTTPS'] = '1';
		$this->assertTrue( is_ssl() );

		unset( $_SERVER['HTTPS'] );
		$_SERVER['SERVER_PORT'] = '443';
		$this->assertTrue( is_ssl() );
	}

	function test_is_ssl_negative() {
		$_SERVER['HTTPS'] = 'off';
		$this->assertFalse( is_ssl() );

		$_SERVER['HTTPS'] = 'OFF';
		$this->assertFalse( is_ssl() );

		unset($_SERVER['HTTPS']);
		$this->assertFalse( is_ssl() );
	}

	function test_admin_url_valid() {
		$paths = array(
			'' => "/gb-admin/",
			'foo' => "/gb-admin/foo",
			'/foo' => "/gb-admin/foo",
			'/foo/' => "/gb-admin/foo/",
			'foo.php' => "/gb-admin/foo.php",
			'/foo.php' => "/gb-admin/foo.php",
			'/foo.php?bar=1' => "/gb-admin/foo.php?bar=1",
		);
		$https = array('on', 'off');

		foreach ($https as $val) {
			$_SERVER['HTTPS'] = $val;
			$siteurl = 'http:'.BASE_URL;	// TODO: options
// 			$siteurl = get_option('siteurl');
			if ( $val == 'on' )
				$siteurl = str_replace('http://', 'https://', $siteurl);

			foreach ($paths as $in => $out) {
				$this->assertEquals( $siteurl.$out, admin_url($in), "admin_url('{$in}') should equal '{$siteurl}{$out}'");
			}
		}
	}

	function test_admin_url_invalid() {
		$paths = array(
			null => "/gb-admin/",
			0 => "/gb-admin/",
			-1 => "/gb-admin/",
			'///' => "/gb-admin/",
		);
		$https = array('on', 'off');

		foreach ($https as $val) {
			$_SERVER['HTTPS'] = $val;
			$siteurl = 'http:'.BASE_URL;	// TODO: options
//			$siteurl = get_option('siteurl');
			if ( $val == 'on' )
				$siteurl = str_replace('http://', 'https://', $siteurl);

			foreach ($paths as $in => $out) {
				$this->assertEquals( $siteurl.$out, admin_url($in), "admin_url('{$in}') should equal '{$siteurl}{$out}'");
			}
		}
	}

	function test_home_url_valid() {
		$paths = array(
			'' => "",
			'foo' => "/foo",
			'/foo' => "/foo",
			'/foo/' => "/foo/",
			'foo.php' => "/foo.php",
			'/foo.php' => "/foo.php",
			'/foo.php?bar=1' => "/foo.php?bar=1",
		);
		$https = array('on', 'off');

		foreach ($https as $val) {
			$_SERVER['HTTPS'] = $val;
			$home = 'http:'.BASE_URL;	// TODO: options
//			$home = get_option('home');
			if ( $val == 'on' )
				$home = str_replace('http://', 'https://', $home);

			foreach ($paths as $in => $out) {
				$this->assertEquals( $home.$out, home_url($in), "home_url('{$in}') should equal '{$home}{$out}'");
			}
		}
	}

	function test_home_url_invalid() {
		$paths = array(
			null => "",
			0 => "",
			-1 => "",
			'///' => "/",
		);
		$https = array('on', 'off');

		foreach ($https as $val) {
			$_SERVER['HTTPS'] = $val;
			$home = 'http:'.BASE_URL;	// TODO: options
//			$home = get_option('home');
			if ( $val == 'on' )
				$home = str_replace('http://', 'https://', $home);

			foreach ($paths as $in => $out) {
				$this->assertEquals( $home.$out, home_url($in), "home_url('{$in}') should equal '{$home}{$out}'");
			}
		}
	}

	// TODO: admin
/*	function test_home_url_from_admin() {
		// TODO: screen
// 		$screen = get_current_screen();

		// Pretend to be in the site admin
// 		set_current_screen( 'dashboard' );
		$home = 'http:'.BASE_URL;	// TODO: options
//		$home = get_option('home');

		// home_url() should return http when in the admin
		$_SERVER['HTTPS'] = 'on';
		$this->assertEquals( $home, home_url() );

		$_SERVER['HTTPS'] = 'off';
		$this->assertEquals( $home, home_url() );

		// If not in the admin, is_ssl() should determine the scheme
		set_current_screen( 'front' );
		$this->assertEquals( $home, home_url() );
		$_SERVER['HTTPS'] = 'on';
		$home = str_replace('http://', 'https://', $home);
		$this->assertEquals( $home, home_url() );


		// Test with https in home
		// TODO: options
//		update_option( 'home', set_url_scheme( $home, 'https' ) );

		// Pretend to be in the site admin
		// TODO: screen
// 		set_current_screen( 'dashboard' );
		$home = 'http:'.BASE_URL;	// TODO: options
//		$home = get_option('home');

		// home_url() should return whatever scheme is set in the home option when in the admin
		$_SERVER['HTTPS'] = 'on';
		$this->assertEquals( $home, home_url() );

		$_SERVER['HTTPS'] = 'off';
		$this->assertEquals( $home, home_url() );

		// If not in the admin, is_ssl() should determine the scheme unless https hard-coded in home
		set_current_screen( 'front' );
		$this->assertEquals( $home, home_url() );
		$_SERVER['HTTPS'] = 'on';
		$this->assertEquals( $home, home_url() );
		$_SERVER['HTTPS'] = 'off';
		$this->assertEquals( $home, home_url() );

		// TODO: options
//		update_option( 'home', set_url_scheme( $home, 'http' ) );

		// TODO: screen
// 		$GLOBALS['current_screen'] = $screen;
	}/**/

	function test_set_url_scheme() {
		if ( ! function_exists( 'set_url_scheme' ) )
			return;

		$links = array(
			'http://somesite.org/',
			'https://somesite.org/',
			'http://somesite.org/news/',
			'http://somesite.org',
		);

		$https_links = array(
			'https://somesite.org/',
			'https://somesite.org/',
			'https://somesite.org/news/',
			'https://somesite.org',
		);

		$http_links = array(
			'http://somesite.org/',
			'http://somesite.org/',
			'http://somesite.org/news/',
			'http://somesite.org',
		);

		$relative_links = array(
			'/',
			'/',
			'/news/',
			''
		);

		$forced_admin = force_ssl_admin();
		$forced_login = force_ssl_login();
		$i = 0;
		foreach ( $links as $link ) {
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'https' ) );
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link, 'http' ) );
			$this->assertEquals( $relative_links[ $i ], set_url_scheme( $link, 'relative' ) );

			$_SERVER['HTTPS'] = 'on';
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link ) );

			$_SERVER['HTTPS'] = 'off';
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link ) );

			force_ssl_login( false );
			force_ssl_admin( true );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'admin' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'login_post' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'login' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'rpc' ) );

			force_ssl_admin( false );
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link, 'admin' ) );
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link, 'login_post' ) );
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link, 'login' ) );
			$this->assertEquals( $http_links[ $i ], set_url_scheme( $link, 'rpc' ) );

			force_ssl_login( true );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'admin' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'login_post' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'login' ) );
			$this->assertEquals( $https_links[ $i ], set_url_scheme( $link, 'rpc' ) );

			$i++;
		}

		force_ssl_admin( $forced_admin );
		force_ssl_login( $forced_login );
	}

	/**
	 * Test that *_url functions handle paths with ".."
	 */
	public function test_url_functions_for_dots_in_paths() {
		$functions = array(
			'site_url',
			'home_url',
			'admin_url',
// 			'user_admin_url',
// 			'includes_url',
// 			'content_url',
// 			'plugins_url',
		);

		foreach ( $functions as $function ) {
			$this->assertEquals( call_user_func( $function, '/' ) . '../',
				call_user_func( $function, '../' ) );
			$this->assertEquals( call_user_func( $function, '/' ) . 'something...here',
				call_user_func( $function, 'something...here' ) );
		}
	}
}
