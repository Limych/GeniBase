<?php

/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_Scripts extends GB_UnitTestCase
{

    var $old_gb_scripts;

    function setUp()
    {
        parent::setUp();
        $this->old_gb_scripts = isset($GLOBALS['gb_scripts']) ? $GLOBALS['gb_scripts'] : null;
        GB_Hooks::remove_action('gb_default_scripts', 'gb_default_scripts');
        $GLOBALS['gb_scripts'] = new GB_Scripts();
        _gb_scripts()->default_version = get_siteinfo('version');
    }

    function tearDown()
    {
        $GLOBALS['gb_scripts'] = $this->old_gb_scripts;
        GB_Hooks::add_action('gb_default_scripts', 'gb_default_scripts');
        parent::tearDown();
    }

    /**
     * Test versioning
     */
    function test_gb_enqueue_script()
    {
        gb_enqueue_script('no-deps-no-version', 'example.com', array());
        gb_enqueue_script('empty-deps-no-version', 'example.com');
        gb_enqueue_script('empty-deps-version', 'example.com', array(), 1.2);
        gb_enqueue_script('empty-deps-null-version', 'example.com', array(), null);
        $ver = get_siteinfo('version');
        $expected = "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
        $expected .= "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
        $expected .= "<script type='text/javascript' src='http://example.com?ver=1.2'></script>\n";
        $expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
        
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals("", get_echo('gb_print_scripts'));
    }

    /**
     * Test the different protocol references in gb_enqueue_script
     */
    public function test_protocols()
    {
        // Init
        $base_url_backup = _gb_scripts()->base_url;
        _gb_scripts()->base_url = 'http://example.com/genibase';
        $expected = '';
        $ver = get_siteinfo('version');
        
        // Try with an HTTP reference
        gb_enqueue_script('jquery-http', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js');
        $expected .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";
        
        // Try with an HTTPS reference
        gb_enqueue_script('jquery-https', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js');
        $expected .= "<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";
        
        // Try with an automatic protocol reference (//)
        gb_enqueue_script('jquery-doubleslash', '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js');
        $expected .= "<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";
        
        // Try with a local resource and an automatic protocol reference (//)
        $url = '//my_plugin/script.js';
        gb_enqueue_script('plugin-script', $url);
        $expected .= "<script type='text/javascript' src='$url?ver=$ver'></script>\n";
        
        // Try with a bad protocol
        gb_enqueue_script('jquery-ftp', 'ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js');
        $expected .= "<script type='text/javascript' src='" . _gb_scripts()->base_url . "ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals('', get_echo('gb_print_scripts'));
        
        // Cleanup
        _gb_scripts()->base_url = $base_url_backup;
    }

    /**
     * Testing `gb_script_add_data` with the data key.
     */
    function test_gb_script_add_data_with_data_key()
    {
        // Enqueue & add data
        gb_enqueue_script('test-only-data', 'example.com', array(), null);
        gb_script_add_data('test-only-data', 'data', 'testing');
        $expected = "<script type='text/javascript'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n";
        $expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals('', get_echo('gb_print_scripts'));
    }

    /**
     * Testing `gb_script_add_data` with the conditional key.
     */
    function test_gb_script_add_data_with_conditional_key()
    {
        // Enqueue & add conditional comments
        gb_enqueue_script('test-only-conditional', 'example.com', array(), null);
        gb_script_add_data('test-only-conditional', 'conditional', 'gt IE 7');
        $expected = "<!--[if gt IE 7]>\n<script type='text/javascript' src='http://example.com'></script>\n<![endif]-->\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals('', get_echo('gb_print_scripts'));
    }

    /**
     * Testing `gb_script_add_data` with both the data & conditional keys.
     */
    function test_gb_script_add_data_with_data_and_conditional_keys()
    {
        // Enqueue & add data plus conditional comments for both
        gb_enqueue_script('test-conditional-with-data', 'example.com', array(), null);
        gb_script_add_data('test-conditional-with-data', 'data', 'testing');
        gb_script_add_data('test-conditional-with-data', 'conditional', 'lt IE 9');
        $expected = "<!--[if lt IE 9]>\n<script type='text/javascript'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n<![endif]-->\n";
        $expected .= "<!--[if lt IE 9]>\n<script type='text/javascript' src='http://example.com'></script>\n<![endif]-->\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals('', get_echo('gb_print_scripts'));
    }

    /**
     * Testing `gb_script_add_data` with an anvalid key.
     */
    function test_gb_script_add_data_with_invalid_key()
    {
        // Enqueue & add an invalid key
        gb_enqueue_script('test-invalid', 'example.com', array(), null);
        gb_script_add_data('test-invalid', 'invalid', 'testing');
        $expected = "<script type='text/javascript' src='http://example.com'></script>\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_scripts'));
        
        // No scripts left to print
        $this->assertEquals('', get_echo('gb_print_scripts'));
    }
}
