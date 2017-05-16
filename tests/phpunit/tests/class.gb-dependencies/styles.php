<?php

/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_Styles extends GB_UnitTestCase
{

    var $old_gb_styles;

    function setUp()
    {
        parent::setUp();
        if (empty($GLOBALS['gb_styles']))
            $GLOBALS['gb_styles'] = null;
        $this->old_gb_styles = $GLOBALS['gb_styles'];
        GB_Hooks::remove_action('gb_default_styles', 'gb_default_styles');
        $GLOBALS['gb_styles'] = new GB_Styles();
        gb_styles()->default_version = get_siteinfo('version');
    }

    function tearDown()
    {
        $GLOBALS['gb_styles'] = $this->old_gb_styles;
        GB_Hooks::add_action('gb_default_styles', 'gb_default_styles');
        parent::tearDown();
    }

    /**
     * Test versioning
     */
    function test_gb_enqueue_style()
    {
        gb_enqueue_style('no-deps-no-version', 'example.com');
        gb_enqueue_style('no-deps-version', 'example.com', array(), 1.2);
        gb_enqueue_style('no-deps-null-version', 'example.com', array(), null);
        gb_enqueue_style('no-deps-null-version-print-media', 'example.com', array(), null, 'print');
        $ver = get_siteinfo('version');
        $expected = "<link rel='stylesheet' id='no-deps-no-version-css' href='http://example.com?ver=$ver' type='text/css' media='all' />\n";
        $expected .= "<link rel='stylesheet' id='no-deps-version-css' href='http://example.com?ver=1.2' type='text/css' media='all' />\n";
        $expected .= "<link rel='stylesheet' id='no-deps-null-version-css' href='http://example.com' type='text/css' media='all' />\n";
        $expected .= "<link rel='stylesheet' id='no-deps-null-version-print-media-css' href='http://example.com' type='text/css' media='print' />\n";
        
        $this->assertEquals($expected, get_echo('gb_print_styles'));
        
        // No styles left to print
        $this->assertEquals("", get_echo('gb_print_styles'));
    }

    /**
     * Test the different protocol references in gb_enqueue_style
     */
    public function test_protocols()
    {
        // Init
        $base_url_backup = gb_styles()->base_url;
        gb_styles()->base_url = 'http://example.com/genibase';
        $expected = '';
        $ver = get_siteinfo('version');
        
        // Try with an HTTP reference
        gb_enqueue_style('reset-css-http', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css');
        $expected .= "<link rel='stylesheet' id='reset-css-http-css' href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";
        
        // Try with an HTTPS reference
        gb_enqueue_style('reset-css-https', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css');
        $expected .= "<link rel='stylesheet' id='reset-css-https-css' href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";
        
        // Try with an automatic protocol reference (//)
        gb_enqueue_style('reset-css-doubleslash', '//yui.yahooapis.com/2.8.1/build/reset/reset-min.css');
        $expected .= "<link rel='stylesheet' id='reset-css-doubleslash-css' href='//yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";
        
        // Try with a local resource and an automatic protocol reference (//)
        $url = '//my_plugin/style.css';
        gb_enqueue_style('plugin-style', $url);
        $expected .= "<link rel='stylesheet' id='plugin-style-css' href='$url?ver=$ver' type='text/css' media='all' />\n";
        
        // Try with a bad protocol
        gb_enqueue_style('reset-css-ftp', 'ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css');
        $expected .= "<link rel='stylesheet' id='reset-css-ftp-css' href='" . gb_styles()->base_url . "ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' type='text/css' media='all' />\n";
        
        // Go!
        $this->assertEquals($expected, get_echo('gb_print_styles'));
        
        // No styles left to print
        $this->assertEquals('', get_echo('gb_print_styles'));
        
        // Cleanup
        gb_styles()->base_url = $base_url_backup;
    }

    /**
     * Test if inline styles work
     */
    public function test_inline_styles()
    {
        $style = ".thing {\n";
        $style .= "\tbackground: red;\n";
        $style .= "}";
        
        $expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />\n";
        $expected .= "<style id='handle-inline-css' type='text/css'>\n";
        $expected .= "$style\n";
        $expected .= "</style>\n";
        
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        gb_add_inline_style('handle', $style);
        
        // No styles left to print
        $this->assertEquals($expected, get_echo('gb_print_styles'));
    }

    /**
     * Test if inline styles work with concatination
     */
    public function test_inline_styles_concat()
    {
        gb_styles()->do_concat = true;
        gb_styles()->default_dirs = array(
            '/gb-admin/',
            '/gb/css/'
        ); // Default dirs as in gb/script-loader.php
        
        $style = ".thing {\n";
        $style .= "\tbackground: red;\n";
        $style .= "}";
        
        $expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />\n";
        $expected .= "<style id='handle-inline-css' type='text/css'>\n";
        $expected .= "$style\n";
        $expected .= "</style>\n";
        
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        gb_add_inline_style('handle', $style);
        
        gb_print_styles();
        $this->assertEquals($expected, gb_styles()->print_html);
    }

    /**
     * Test if multiple inline styles work
     */
    public function test_multiple_inline_styles()
    {
        $style1 = ".thing1 {\n";
        $style1 .= "\tbackground: red;\n";
        $style1 .= "}";
        
        $style2 = ".thing2 {\n";
        $style2 .= "\tbackground: blue;\n";
        $style2 .= "}";
        
        $expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />\n";
        $expected .= "<style id='handle-inline-css' type='text/css'>\n";
        $expected .= "$style1\n";
        $expected .= "$style2\n";
        $expected .= "</style>\n";
        
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        gb_add_inline_style('handle', $style1);
        gb_add_inline_style('handle', $style2);
        
        // No styles left to print
        $this->assertEquals($expected, get_echo('gb_print_styles'));
    }

    /**
     * Test if a plugin doing it the wrong way still works
     *
     * @expectedIncorrectUsage gb_add_inline_style
     */
    public function test_plugin_doing_inline_styles_wrong()
    {
        $style = "<style id='handle-inline-css' type='text/css'>\n";
        $style .= ".thing {\n";
        $style .= "\tbackground: red;\n";
        $style .= "}\n";
        $style .= "</style>";
        
        $expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />\n";
        $expected .= "$style\n";
        
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        
        gb_add_inline_style('handle', $style);
        
        $this->assertEquals($expected, get_echo('gb_print_styles'));
    }

    /**
     * Test to make sure <style> tags aren't output if there are no inline styles.
     */
    public function test_unnecessary_style_tags()
    {
        $expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />\n";
        
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        
        $this->assertEquals($expected, get_echo('gb_print_styles'));
    }

    /**
     * Test to make sure that inline styles attached to conditional
     * stylesheets are also conditional.
     */
    public function test_conditional_inline_styles_are_also_conditional()
    {
        $expected = <<<CSS
<!--[if IE]>
<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' type='text/css' media='all' />
<style id='handle-inline-css' type='text/css'>
a { color: blue; }
</style>
<![endif]-->

CSS;
        gb_enqueue_style('handle', 'http://example.com', array(), 1);
        gb_style_add_data('handle', 'conditional', 'IE');
        gb_add_inline_style('handle', 'a { color: blue; }');
        
        $this->assertEquals($expected, get_echo('gb_print_styles'));
    }
}
