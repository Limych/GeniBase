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

/** BackPress: GeniBase Dependencies Class */
require_once(GB_INC_DIR . '/class.gb-dependencies.php');

/** BackPress: GeniBase Styles Class */
require_once(GB_INC_DIR . '/class.gb-styles.php');

/** BackPress: GeniBase Styles Functions */
require_once(GB_INC_DIR . '/functions.gb-styles.php');

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
 * @since	2.0.0
 */
function gb_default_styles() {
	if(!defined('GB_SCRIPT_DEBUG'))		define('GB_SCRIPT_DEBUG', GB_DEBUG);

// 	if ( ! $guessurl = site_url() )
// 		$guessurl = gb_guess_url();
	$guessurl = BASE_URL;

	_gb_styles()->base_url = $guessurl;
	_gb_styles()->content_url = defined('GB_CONTENT_URL')? GB_CONTENT_URL : '';
	_gb_styles()->default_version = get_siteinfo( 'version' );
	_gb_styles()->text_direction = function_exists( 'is_rtl' ) && is_rtl() ? 'rtl' : 'ltr';
	_gb_styles()->default_dirs = array('/gb-admin/css/', '/gb/css/');

	$suffix = GB_SCRIPT_DEBUG ? '' : '.min';

	// External libraries and friends
	_gb_styles()->add('normalize', "/gb/css/normalize.css");

	// External libraries and friends
	_gb_styles()->add('responsive-table', "/gb/css/responsive-table.css", array('normalize'));
}

/**
 * Prints the styles that were queued too late for the HTML head.
 *
 * @since	2.0.0
 */
function print_late_styles() {
	global $concatenate_scripts;

	_gb_styles()->do_concat = $concatenate_scripts;
	_gb_styles()->do_footer_items();

	/**
	 * Filter whether to print the styles queued too late for the HTML head.
	 *
	 * @since	2.0.0
	 *
	 * @param bool $print Whether to print the 'late' styles. Default true.
	 */
// 	if ( apply_filters( 'print_late_styles', true ) ) {
		_print_styles();
// 	}

	_gb_styles()->reset();
	return _gb_styles()->done;
}

/**
 * @internal use
 */
function _print_styles() {
	global $compress_css;

	$zip = $compress_css ? 1 : 0;
	if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
		$zip = 'gzip';

	if ( !empty(_gb_styles()->concat) ) {
		$dir = _gb_styles()->text_direction;
		$ver = _gb_styles()->default_version;
		$href = _gb_styles()->base_url . "/gb-admin/load-styles.php?c={$zip}&dir={$dir}&load=" . trim(_gb_styles()->concat, ', ') . '&ver=' . $ver;
		echo "<link rel='stylesheet' href='" . esc_attr($href) . "' type='text/css' media='all' />\n";

		if ( !empty(_gb_styles()->print_code) ) {
			echo "<style type='text/css'>\n";
			echo _gb_styles()->print_code;
			echo "\n</style>\n";
		}
	}

	if ( !empty(_gb_styles()->print_html) )
		echo _gb_styles()->print_html;
}

/**
 * Determine the concatenation and compression settings for scripts and styles.
 *
 * @since	2.0.0
 */
function script_concat_settings() {
	global $concatenate_scripts, $compress_scripts, $compress_css;

	$compressed_output = ( ini_get('zlib.output_compression') || 'ob_gzhandler' == ini_get('output_handler') );

	if ( ! isset($concatenate_scripts) ) {
		$concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;
		if ( ! is_admin() || ( defined('GB_SCRIPT_DEBUG') && GB_SCRIPT_DEBUG ) )
			$concatenate_scripts = false;
	}

	if ( ! isset($compress_scripts) ) {
		$compress_scripts = defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : true;
		if ( $compress_scripts && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_scripts = false;
	}

	if ( ! isset($compress_css) ) {
		$compress_css = defined('COMPRESS_CSS') ? COMPRESS_CSS : true;
		if ( $compress_css && ( ! get_site_option('can_compress_scripts') || $compressed_output ) )
			$compress_css = false;
	}
}

// add_action( 'gb_default_scripts', 'gb_default_scripts' );
// add_filter( 'gb_print_scripts', 'gb_just_in_time_script_localization' );
// add_filter( 'print_scripts_array', 'gb_prototype_before_jquery' );

// add_action( 'gb_default_styles', 'gb_default_styles' );
// add_filter( 'style_loader_src', 'gb_style_loader_src', 10, 2 );
