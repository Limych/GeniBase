<?php
/**
 * GeniBase scripts and styles default loader.
 *
 * Several constants are used to manage the loading, concatenating and compression of scripts and CSS:
 * define('GB_SCRIPT_DEBUG', true); loads the development (non-minified) versions of all scripts and CSS, and disables compression and concatenation,
 * define('CONCATENATE_SCRIPTS', false); disables compression and concatenation of scripts and CSS,
 * define('COMPRESS_SCRIPTS', false); disables compression of scripts,
 * define('COMPRESS_CSS', false); disables compression of CSS,
 * define('ENFORCE_GZIP', true); forces gzip for compression (default is deflate).
 *
 * The globals $concatenate_scripts, $compress_scripts and $compress_css can be set by plugins
 * to temporarily override the above settings. Also a compression test is run once and the result is saved
 * as option 'can_compress_scripts' (0/1). The test will run again if that option is deleted.
 *
 * @package GeniBase
 * @since	2.0.0
 *
 * @copyright	Copyright © WordPress Team
 * @copyright	Partially copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

require_once (GB_CORE_DIR . '/class.gb-dependencies.php');
require_once (GB_CORE_DIR . '/class.gb-scripts.php');
require_once (GB_CORE_DIR . '/functions.gb-scripts.php');
require_once (GB_CORE_DIR . '/class.gb-styles.php');
require_once (GB_CORE_DIR . '/functions.gb-styles.php');

/**
 * Register all GeniBase scripts.
 *
 * Localizes some of them.
 * args order: $scripts->add( 'handle', 'url', 'dependencies', 'query-string', 1 );
 * when last arg === 1 queues the script for the footer
 *
 * @since 2.0.0
 *       
 * @param GB_Scripts $scripts
 *            object.
 */
function gb_default_scripts(&$scripts)
{
    if (! defined('GB_SCRIPT_DEBUG'))
        define('GB_SCRIPT_DEBUG', GB_DEBUG);
    
    if (! $guessurl = site_url())
        $guessurl = gb_guess_url();
    
    $scripts->base_url = $guessurl;
    $scripts->content_url = defined('GB_CONTENT_URL') ? GB_CONTENT_URL : '';
    $scripts->default_version = get_siteinfo('version');
    $scripts->default_dirs = array(
        GB_CORE_URL . '/js/'
    );
    
    $suffix = GB_SCRIPT_DEBUG ? '' : '.min';
    
    // Vendor libraries and friends
    $scripts->add('jquery', false, array(
        'jquery-core'
    ), null);
    $scripts->add('jquery-core', GB_CORE_URL . "/js/vendor/jquery-2.1.3$suffix.js", array(), null);
    
    $scripts->add('zxcvbn-async', GB_ADMIN_URL . "/js/zxcvbn-async$suffix.js", array(), '1.0');
    GB_Hooks::did_action('init') && $scripts->localize('zxcvbn-async', '_zxcvbnSettings', array(
        'src' => GB_ADMIN_URL . '/js/zxcvbn.min.js'
    ));
    
    $scripts->add('password-strength-meter', GB_ADMIN_URL . "/js/password-strength-meter$suffix.js", array(
        'jquery',
        'zxcvbn-async'
    ), false, 1);
    GB_Hooks::did_action('init') && $scripts->localize('password-strength-meter', 'pwsL10n', array(
        'empty' => __('Strength indicator'),
        'short' => __('Very weak', 'password strength'),
        'bad' => __('Weak', 'password strength'),
        'good' => _x('Medium', 'password strength'),
        'strong' => __('Strong', 'password strength'),
        'mismatch' => __('Mismatch')
    ));
    
    // Admin mode scripts
    $scripts->add('user-profile', GB_ADMIN_URL . "/js/user-profile$suffix.js", array(
        'jquery',
        'password-strength-meter'/* , 'wp-util' */ ), false, 1);
}

/**
 * Assign default styles to $styles object.
 *
 * Nothing is returned, because the $styles parameter is passed by reference.
 * Meaning that whatever object is passed will be updated without having to
 * reassign the variable that was passed back to the same value. This saves
 * memory.
 *
 * Adding default styles is not the only task, it also assigns the base_url
 * property, the default version, and text direction for the object.
 *
 * @since 2.0.0
 *       
 * @param GB_Styles $styles
 *            object.
 */
function gb_default_styles(&$styles)
{
    if (! defined('GB_SCRIPT_DEBUG'))
        define('GB_SCRIPT_DEBUG', GB_DEBUG);
    
    if (! $guessurl = site_url())
        $guessurl = gb_guess_url();
    
    $styles->base_url = $guessurl;
    $styles->content_url = defined('GB_CONTENT_URL') ? GB_CONTENT_URL : '';
    $styles->default_version = get_siteinfo('version');
    $styles->text_direction = function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr';
    $styles->default_dirs = array(
        GB_CORE_URL . '/css/'
    );
    
    $open_sans_font_url = '';
    
    /*
     * translators: If there are characters in your language that are not supported
     * by Open Sans, translate this to 'off'. Do not translate into your own language.
     */
    if ('off' !== _x('on', 'Open Sans font: on or off')) {
        $subsets = 'latin,latin-ext';
        
        /*
         * translators: To add an additional Open Sans character subset specific to your language,
         * translate this to 'greek', 'cyrillic' or 'vietnamese'. Do not translate into your own language.
         */
        $subset = _x('no-subset', 'Open Sans font: add new subset (greek, cyrillic, vietnamese)');
        
        if ('cyrillic' == $subset) {
            $subsets .= ',cyrillic,cyrillic-ext';
        } elseif ('greek' == $subset) {
            $subsets .= ',greek,greek-ext';
        } elseif ('vietnamese' == $subset) {
            $subsets .= ',vietnamese';
        }
        
        // Hotlink Open Sans, for now
        $open_sans_font_url = "//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,300,400,600&subset=$subsets";
    }
    
    $suffix = GB_SCRIPT_DEBUG ? '' : '.min';
    
    // Common dependencies
    $styles->add('buttons', GB_CORE_URL . "/load-style.php/buttons$suffix");
    // $styles->add( 'dashicons', "/wp-includes/css/dashicons$suffix.css" );
    $styles->add('open-sans', $open_sans_font_url);
    
    // Common styles
    $styles->add('gb-core', GB_CORE_URL . "/load-style.php/core$suffix");
    $styles->add('responsive-tables', GB_CORE_URL . "/css/responsive-tables.css", array(
        'gb-core'
    ));
    $styles->add('forms', GB_CORE_URL . "/load-style.php/forms$suffix"/* , array('gb-core') */ );
    
    // Admin styles
    $styles->add('install', "/gb-admin/css/gb-install$suffix.css", array(
        'gb-core',
        'open-sans'
    ));
    $styles->add('ie', "/gb-admin/css/ie$suffix.css");
    
    $styles->add_data('ie', 'conditional', 'lte IE 7');
}

/**
 * Prints the script queue in the HTML head on admin pages.
 *
 * Postpones the scripts that were queued for the footer.
 * print_footer_scripts() is called in the footer to print these scripts.
 *
 * @since 2.0.0
 *       
 * @see gb_print_scripts()
 */
function print_head_scripts()
{
    global $concatenate_scripts;
    
    if (! GB_Hooks::did_action('gb_print_scripts')) {
        /**
         * This action is documented in gb/functions.gb-scripts.php
         */
        GB_Hooks::do_action('gb_print_scripts');
    }
    
    script_concat_settings();
    _gb_scripts()->do_concat = $concatenate_scripts;
    _gb_scripts()->do_concat = FALSE; // TODO: Remove for enable concatenate mode
    _gb_scripts()->do_head_items();
    
    /**
     * Filter whether to print the head scripts.
     *
     * @since 2.1.0
     *       
     * @param bool $print
     *            Whether to print the head scripts. Default true.
     */
    if (GB_Hooks::apply_filters('print_head_scripts', true))
        _print_scripts();
    
    _gb_scripts()->reset();
    return _gb_scripts()->done;
}

/**
 * Prints the scripts that were queued for the footer or too late for the HTML head.
 *
 * @since 2.0.0
 */
function print_footer_scripts()
{
    global $concatenate_scripts;
    
    script_concat_settings();
    _gb_scripts()->do_concat = $concatenate_scripts;
    _gb_scripts()->do_concat = FALSE; // TODO: Remove for enable concatenate mode
                                      // _gb_scripts()->do_concat = true; // TODO: Remove for enable concatenate mode
    _gb_scripts()->do_footer_items();
    
    /**
     * Filter whether to print the footer scripts.
     *
     * @since 2.1.0
     *       
     * @param bool $print
     *            Whether to print the footer scripts. Default true.
     */
    if (GB_Hooks::apply_filters('print_footer_scripts', true))
        _print_scripts();
    
    _gb_scripts()->reset();
    return _gb_scripts()->done;
}

/**
 *
 * @internal use
 */
function _print_scripts()
{
    global $compress_scripts;
    
    $zip = $compress_scripts ? 1 : 0;
    if ($zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP)
        $zip = 'gzip';
    
    if ($concat = trim(_gb_scripts()->concat, ', ')) {
        if (! empty(_gb_scripts()->print_code)) {
            echo "\n<script type='text/javascript'>\n";
            echo "/* <![CDATA[ */\n"; // not needed in HTML 5
            echo _gb_scripts()->print_code;
            echo "/* ]]> */\n";
            echo "</script>\n";
        }
        
        $src = _gb_scripts()->base_url . "/gb/load-scripts.php?t=scr&c={$zip}&ver=" . _gb_scripts()->default_version;
        echo "<script type='text/javascript' src='" . esc_attr($src) . "'></script>\n";
    }
    
    if (! empty(_gb_scripts()->print_html))
        echo _gb_scripts()->print_html;
}

/**
 * Prints the script queue in the HTML head on the front end.
 *
 * Postpones the scripts that were queued for the footer.
 * gb_print_footer_scripts() is called in the footer to print these scripts.
 *
 * @since 2.0.0
 */
function gb_print_head_scripts()
{
    if (! GB_Hooks::did_action('gb_print_scripts')) {
        /**
         * This action is documented in gb/functions.gb-scripts.php
         */
        GB_Hooks::do_action('gb_print_scripts');
    }
    
    return print_head_scripts();
}

/**
 * Private, for use in *_footer_scripts hooks
 *
 * @since 2.0.0
 */
function _gb_footer_scripts()
{
    print_late_styles();
    print_footer_scripts();
}

/**
 * Hooks to print the scripts and styles in the footer.
 *
 * @since 2.0.0
 */
function gb_print_footer_scripts()
{
    /**
     * Fires when footer scripts are printed.
     *
     * @since 2.1.0
     */
    GB_Hooks::do_action('gb_print_footer_scripts');
}

/**
 * Wrapper for GB_Hooks::do_action('gb_enqueue_scripts')
 *
 * Allows plugins to queue scripts for the front end using gb_enqueue_script().
 * Runs first in gb_head().
 *
 * @since 2.1.0
 */
function gb_enqueue_scripts()
{
    /**
     * Fires when scripts and styles are enqueued.
     *
     * @since 2.1.0
     */
    GB_Hooks::do_action('gb_enqueue_scripts');
}

/**
 * Prints the styles that were queued too late for the HTML head.
 *
 * @since 2.0.0
 */
function print_late_styles()
{
    global $concatenate_scripts;
    
    gb_styles()->do_concat = $concatenate_scripts;
    gb_styles()->do_concat = FALSE; // TODO: Remove for enable concatenate mode
    gb_styles()->do_footer_items();
    
    /**
     * Filter whether to print the styles queued too late for the HTML head.
     *
     * @since 2.0.0
     *       
     * @param bool $print
     *            Whether to print the 'late' styles. Default true.
     */
    if (GB_Hooks::apply_filters('print_late_styles', true))
        _print_styles();
    
    gb_styles()->reset();
    return gb_styles()->done;
}

/**
 *
 * @internal use
 */
function _print_styles()
{
    global $compress_css;
    
    $zip = $compress_css ? 1 : 0;
    if ($zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP)
        $zip = 'gzip';
    
    if (! empty(gb_styles()->concat)) {
        $dir = gb_styles()->text_direction;
        $ver = gb_styles()->default_version;
        $href = gb_styles()->base_url . "/gb/load-styles.php?c={$zip}&dir={$dir}&load=" . trim(gb_styles()->concat, ', ') . '&ver=' . $ver;
        echo "<link rel='stylesheet' href='" . esc_attr($href) . "' type='text/css' media='all' />\n";
        
        if (! empty(gb_styles()->print_code)) {
            echo "<style type='text/css'>\n";
            echo gb_styles()->print_code;
            echo "\n</style>\n";
        }
    }
    
    if (! empty(gb_styles()->print_html))
        echo gb_styles()->print_html;
}

/**
 * Determine the concatenation and compression settings for scripts and styles.
 *
 * @since 2.0.0
 */
function script_concat_settings()
{
    global $concatenate_scripts, $compress_scripts, $compress_css;
    
    $compressed_output = (ini_get('zlib.output_compression') || 'ob_gzhandler' == ini_get('output_handler'));
    
    if (! isset($concatenate_scripts)) {
        $concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;
        if( /*!is_admin() ||*/ (defined('GB_SCRIPT_DEBUG') && GB_SCRIPT_DEBUG))
            $concatenate_scripts = false;
    }
    
    if (! isset($compress_scripts)) {
        $compress_scripts = defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : true;
        if ($compress_scripts && ( /*!get_site_option('can_compress_scripts') ||*/ $compressed_output))
            $compress_scripts = false;
    }
    
    if (! isset($compress_css)) {
        $compress_css = defined('COMPRESS_CSS') ? COMPRESS_CSS : true;
        if ($compress_css && ( /*!get_site_option('can_compress_scripts') ||*/ $compressed_output))
            $compress_css = false;
    }
}

// TODO: actions
GB_Hooks::add_action('gb_default_scripts', 'gb_default_scripts');
// GB_Hooks::add_filter( 'gb_print_scripts', 'gb_just_in_time_script_localization' );
// GB_Hooks::add_filter( 'print_scripts_array', 'gb_prototype_before_jquery' );

GB_Hooks::add_action('gb_default_styles', 'gb_default_styles');
// GB_Hooks::add_filter( 'style_loader_src', 'gb_style_loader_src', 10, 2 );
